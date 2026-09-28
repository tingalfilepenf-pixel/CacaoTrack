<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\User;
use App\Support\BatchTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7C: the single tree helper (RULE 6) that every nested view reads.
 */
class BatchTreeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ScionVariety $variety;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->variety = ScionVariety::create(['scion_variety_name' => 'Batangas']);
    }

    private function batch(
        string $code,
        ?Batch $parent = null,
        string $graftedDate = '2026-07-15',
        string $varietyName = 'Batangas',
    ): Batch {
        $variety = $varietyName === 'Batangas'
            ? $this->variety
            : ScionVariety::create(['scion_variety_name' => $varietyName]);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'parent_batch_id' => $parent?->batch_id,
            'user_id' => $this->user->id,
            'batch_code' => $code,
            'total_grafted' => 100,
            'regraft' => 0,
            'available_count' => 100,
            'grafted_date' => $graftedDate,
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_it_orders_a_depth_three_family_with_roots_by_date_then_code(): void
    {
        $b002 = $this->batch('B002', null, '2026-08-01', 'Trinitario');
        $b001 = $this->batch('B001', null, '2026-07-15');
        $b001r1 = $this->batch('B001-R1', $b001, '2026-09-05', 'Toledo');
        $b001r1r1 = $this->batch('B001-R1-R1', $b001r1, '2026-09-10', 'Criollo');

        $nodes = BatchTree::flatten(Batch::query()->with('parent')->get());

        $this->assertSame(
            ['B001', 'B001-R1', 'B001-R1-R1', 'B002'],
            array_map(fn (array $node) => $node['batch']->batch_code, $nodes),
        );
        $this->assertSame([0, 1, 2, 0], array_map(fn (array $node) => $node['depth'], $nodes));

        $this->assertSame($b001->batch_id, $nodes[0]['batch']->batch_id);
        $this->assertSame($b001r1->batch_id, $nodes[1]['batch']->batch_id);
        $this->assertSame($b001r1r1->batch_id, $nodes[2]['batch']->batch_id);
        $this->assertSame($b002->batch_id, $nodes[3]['batch']->batch_id);
    }

    public function test_children_are_ordered_by_batch_code(): void
    {
        $root = $this->batch('B001');
        $this->batch('B001-R3', $root);
        $this->batch('B001-R10', $root);
        $this->batch('B001-R2', $root);

        $nodes = BatchTree::flatten(Batch::query()->get());

        $this->assertSame(
            ['B001', 'B001-R10', 'B001-R2', 'B001-R3'],
            array_map(fn (array $node) => $node['batch']->batch_code, $nodes),
        );
        $this->assertSame([0, 1, 1, 1], array_map(fn (array $node) => $node['depth'], $nodes));
    }

    public function test_a_filtered_out_parent_is_flagged_and_never_dropped(): void
    {
        $root = $this->batch('B001');
        $child = $this->batch('B001-R1', $root, '2026-09-05');

        // Only the child is present (the parent is filtered out).
        $nodes = BatchTree::flatten(Batch::query()->whereKey($child->batch_id)->get());

        $this->assertCount(1, $nodes);
        $this->assertSame('B001-R1', $nodes[0]['batch']->batch_code);
        $this->assertSame(0, $nodes[0]['depth']);
        $this->assertTrue($nodes[0]['parent_missing']);
        $this->assertSame('B001', $nodes[0]['parent_code']);
    }

    public function test_missing_parent_codes_are_resolved_without_n_plus_one(): void
    {
        $root = $this->batch('B001');
        $other = $this->batch('B002', null, '2026-08-01', 'Trinitario');
        $this->batch('B001-R1', $root, '2026-09-05', 'Toledo');
        $this->batch('B002-R1', $other, '2026-09-06', 'Forastero');

        $loaded = Batch::query()->get();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $nodes = BatchTree::flatten($loaded);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(4, $nodes);
        // Every parent is in the collection, so no extra parent lookups happen.
        $this->assertSame(0, $queries, 'BatchTree must not issue a query per child.');
    }

    public function test_orphans_resolve_parent_codes_in_a_single_query(): void
    {
        $root = $this->batch('B001');
        $other = $this->batch('B002', null, '2026-08-01', 'Trinitario');
        $first = $this->batch('B001-R1', $root, '2026-09-05', 'Toledo');
        $second = $this->batch('B002-R1', $other, '2026-09-06', 'Forastero');

        // Both children are present but both parents are filtered out.
        $loaded = Batch::query()->whereIn('batch_id', [$first->batch_id, $second->batch_id])->get();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $nodes = BatchTree::flatten($loaded);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(2, $nodes);
        $this->assertSame(1, $queries, 'Filtered-out parent codes must be resolved in one batched query.');
        $this->assertSame(['B001', 'B002'], array_column($nodes, 'parent_code'));
    }
}
