<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7C: the Age Profile and Gap Report nest regraft sub-batches under their
 * parent and expose a Parent column. The Gap formula itself is unchanged.
 */
class HistoricalNestingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Batch $root;

    private Batch $sub;

    private Batch $grand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->role(Role::MANAGER)->create();

        $batangas = ScionVariety::create(['scion_variety_name' => 'Batangas']);
        $toledo = ScionVariety::create(['scion_variety_name' => 'Toledo']);
        $criollo = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        $this->root = $this->batch('B001', $batangas, null, '2026-07-15', 400, 400);
        $this->sub = $this->batch('B001-R1', $toledo, $this->root, '2026-09-05', 70, 50);
        $this->grand = $this->batch('B001-R1-R1', $criollo, $this->sub, '2026-09-08', 30, 30);

        MortalityRecord::create([
            'batch_id' => $this->root->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        MortalityRecord::create([
            'batch_id' => $this->sub->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-10',
        ]);
    }

    private function batch(
        string $code,
        ScionVariety $variety,
        ?Batch $parent,
        string $graftedDate,
        int $total,
        int $available,
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

    public function test_age_profile_lists_sub_batches_as_their_own_nested_rows(): void
    {
        $profile = AnalyticsMetrics::ageProfile(null, null, '2026-09-20');

        $this->assertSame(
            ['B001', 'B001-R1', 'B001-R1-R1'],
            array_column($profile['rows'], 'batch'),
        );
        $this->assertSame([0, 1, 2], array_column($profile['rows'], 'depth'));
        $this->assertSame([null, 'B001', 'B001-R1'], array_column($profile['rows'], 'parent'));

        // Each sub-batch uses its own grafted date for the age bucket.
        $byCode = collect($profile['rows'])->keyBy('batch');
        $this->assertSame(67, $byCode['B001']['age']);
        $this->assertSame('61-90 Days', $byCode['B001']['bucket']);
        $this->assertSame(15, $byCode['B001-R1']['age']);
        $this->assertSame('0-30 Days', $byCode['B001-R1']['bucket']);
        $this->assertSame(12, $byCode['B001-R1-R1']['age']);

        $this->actingAs($this->user)
            ->get(route('analytics.historical.age-profile', ['as_of' => '2026-09-20']))
            ->assertOk()
            ->assertSee('Parent Batch')
            ->assertSee('↳')
            ->assertSee('B001-R1-R1');
    }

    public function test_gap_report_nests_sub_batches_and_keeps_the_effective_mortality_formula(): void
    {
        $gap = AnalyticsMetrics::gapReport();

        $this->assertSame(
            ['B001', 'B001-R1', 'B001-R1-R1'],
            array_column($gap['rows'], 'batch'),
        );
        $this->assertSame([0, 1, 2], array_column($gap['rows'], 'depth'));
        $this->assertSame([null, 'B001', 'B001-R1'], array_column($gap['rows'], 'parent'));

        // Formula unchanged: gap = effective mortality per batch.
        $this->assertSame([25, 5, 0], array_column($gap['rows'], 'gap'));
        $this->assertSame(30, $gap['totalGap']);
    }

    public function test_gap_report_page_shows_the_parent_column_and_nested_rows(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report'))
            ->assertOk()
            ->assertSee('Parent Batch')
            ->assertSee('↳')
            ->assertSee('B001-R1-R1')
            ->assertSee('selecting a parent does not include its sub-batches', false);
    }

    public function test_batch_filter_options_label_sub_batches_with_their_parent(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report'))
            ->assertOk()
            ->assertSee('↳ B001-R1 (parent B001, Toledo)', false);

        $this->actingAs($this->user)
            ->get(route('analytics.historical.age-profile'))
            ->assertOk()
            ->assertSee('↳ B001-R1 (parent B001, Toledo)', false);
    }

    public function test_filtering_the_gap_report_by_a_parent_excludes_its_sub_batches(): void
    {
        $filtered = AnalyticsMetrics::gapReport($this->root->batch_id);

        $this->assertSame(['B001'], array_column($filtered['rows'], 'batch'));
        $this->assertSame(25, $filtered['totalGap']);

        $subOnly = AnalyticsMetrics::gapReport($this->grand->batch_id);

        $this->assertSame(['B001-R1-R1'], array_column($subOnly['rows'], 'batch'));
        $this->assertSame(0, $subOnly['totalGap']);
    }

    public function test_gap_report_csv_includes_the_parent_column_and_nested_label(): void
    {
        $csv = $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report.export.csv'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('Batch,"Parent Batch",Variety', $csv);
        // fputcsv quotes the indented nested labels (they contain spaces).
        $this->assertStringContainsString('B001-R1",B001,Toledo,70,50,0,5', $csv);
        $this->assertStringContainsString('B001-R1-R1",B001-R1,Criollo,30,30,0,0', $csv);
        $this->assertStringContainsString('Total,,,500,480,0,30', $csv);
    }
}
