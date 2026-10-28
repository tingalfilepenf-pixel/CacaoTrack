<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7C: the Batches and Inventory pages show parent batches and their
 * regraft sub-batches nested (ordered/depth from the single BatchTree helper).
 */
class NestedBatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ScionVariety $batangas;

    private ScionVariety $toledo;

    private ScionVariety $criollo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->batangas = ScionVariety::create(['scion_variety_name' => 'Batangas']);
        $this->toledo = ScionVariety::create(['scion_variety_name' => 'Toledo']);
        $this->criollo = ScionVariety::create(['scion_variety_name' => 'Criollo']);
    }

    private function batch(
        string $code,
        ScionVariety $variety,
        ?Batch $parent,
        string $graftedDate,
        int $total = 100,
        int $available = 100,
    ): Batch {
        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'parent_batch_id' => $parent?->batch_id,
            'user_id' => $this->user->id,
            'batch_code' => $code,
            'total_grafted' => $total,
            'regraft' => 0,
            'available_count' => $available,
            'grafted_date' => $graftedDate,
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return array{root:Batch,sub:Batch,grand:Batch}
     */
    private function family(): array
    {
        $root = $this->batch('B001', $this->batangas, null, '2026-07-15', 400, 400);
        $sub = $this->batch('B001-R1', $this->toledo, $root, '2026-09-05', 70, 50);
        $grand = $this->batch('B001-R1-R1', $this->criollo, $sub, '2026-09-08', 30, 30);

        return ['root' => $root, 'sub' => $sub, 'grand' => $grand];
    }

    public function test_batches_page_nests_sub_batches_in_tree_order(): void
    {
        $this->family();

        $response = $this->actingAs($this->user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertSee('↳')
            ->assertSee('Sub-batch')
            ->assertSeeInOrder(['B001', 'B001-R1', 'B001-R1-R1']);

        $response->assertViewHas('batches', function ($families) {
            $rows = collect($families->items())->flatMap(fn (array $family) => $family['rows']);

            $shape = $rows->map(fn (array $row) => [
                $row['batch']->batch_code,
                $row['depth'],
                $row['parent_code'],
            ])->all();

            return $shape === [
                ['B001', 0, null],
                ['B001-R1', 1, 'B001'],
                ['B001-R1-R1', 2, 'B001-R1'],
            ];
        });
    }

    public function test_batches_page_shows_the_parent_column_for_a_sub_batch(): void
    {
        ['sub' => $sub, 'root' => $root] = $this->family();

        $this->actingAs($this->user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertSee('PARENT BATCH')
            ->assertViewHas('batches', function ($families) use ($sub) {
                $rows = collect($families->items())->flatMap(fn (array $family) => $family['rows']);

                $subRow = $rows->firstWhere(fn (array $row) => $row['batch']->batch_id === $sub->batch_id);

                return $subRow !== null
                    && $subRow['parent_code'] === 'B001'
                    && $subRow['depth'] === 1
                    && $subRow['batch']->isSubBatch();
            });
    }

    public function test_batches_page_detail_shows_family_mortality_alongside_per_batch_mortality(): void
    {
        ['root' => $root, 'sub' => $sub] = $this->family();

        MortalityRecord::create([
            'batch_id' => $root->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        MortalityRecord::create([
            'batch_id' => $sub->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-02',
        ]);

        $this->assertSame(30, $root->refresh()->familyMortality());
        $this->assertSame(5, $sub->refresh()->familyMortality());

        $this->actingAs($this->user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertSee('Family Mortality (incl. sub-batches)', false)
            // The serialized details payload carries the rollup key alongside
            // the existing per-batch `mortality` key.
            ->assertSee('family_mortality', false);
    }

    public function test_inventory_page_nests_batches_in_tree_order(): void
    {
        ['sub' => $sub, 'grand' => $grand] = $this->family();

        $response = $this->actingAs($this->user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('↳')
            ->assertSee('Regrafted');

        $response->assertViewHas('batches', function ($batches) {
            return collect($batches->items())->map(fn (Batch $batch) => $batch->batch_code)->all()
                === ['B001', 'B001-R1', 'B001-R1-R1'];
        });

        $response->assertViewHas('nodeMeta', function (array $meta) use ($sub, $grand) {
            return $meta[$sub->batch_id]['depth'] === 1
                && $meta[$sub->batch_id]['parent_code'] === 'B001'
                && $meta[$sub->batch_id]['ancestors'] === [$sub->parent_batch_id]
                && $meta[$sub->batch_id]['has_children'] === true
                && $meta[$grand->batch_id]['depth'] === 2
                && $meta[$grand->batch_id]['parent_code'] === 'B001-R1'
                && $meta[$grand->batch_id]['ancestors'] === [$sub->parent_batch_id, $sub->batch_id]
                && $meta[$grand->batch_id]['has_children'] === false;
        });
    }
}
