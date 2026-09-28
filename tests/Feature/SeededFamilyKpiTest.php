<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ReplantingRecord;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7C: the seeded family conserves its KPI population.
 *
 * Seed (DatabaseSeeder): the original B001 family of 500 is now B001 400 +
 * B001-R1 70 + B001-R1-R1 30 (conservation), plus B002 150 and B003 100.
 */
class SeededFamilyKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_total_active_inventory_equals_the_sum_of_available_count(): void
    {
        $this->assertSame(730, (int) Batch::query()->sum('available_count'));
        $this->assertSame(
            (int) Batch::query()->sum('available_count'),
            AnalyticsMetrics::totalActiveInventory(),
        );
    }

    public function test_crude_mortality_rate_denominator_is_the_sum_of_total_grafted(): void
    {
        $components = AnalyticsMetrics::mortalityRateComponents();

        $this->assertSame((int) Batch::query()->sum('total_grafted'), $components['denominator']);
        $this->assertSame(750, $components['denominator']);

        // The B001 family's original 500 is conserved across parent + sub-batches.
        $this->assertSame(
            500,
            (int) Batch::query()->whereIn('batch_code', ['B001', 'B001-R1', 'B001-R1-R1'])->sum('total_grafted'),
        );
    }

    public function test_the_seeded_family_regraft_counter_matches_its_records(): void
    {
        $regraft = (int) Batch::query()->sum('regraft');
        $records = (int) ReplantingRecord::query()->sum('quantity');

        $this->assertSame(130, $regraft);
        $this->assertSame($records, $regraft);
    }

    public function test_no_seeded_batch_has_available_above_total_grafted(): void
    {
        $this->assertSame(
            0,
            Batch::query()->whereColumn('available_count', '>', 'total_grafted')->count(),
        );
        $this->assertSame(
            0,
            Batch::query()->where('available_count', '<', 0)->count(),
        );
    }

    public function test_ledger_summary_conserves_the_seeded_population(): void
    {
        $grid = AnalyticsMetrics::stockLedgerGrid();

        // Grafted = root batches only; Available (Ready to Plant) = every batch.
        $this->assertSame(650, $grid['summary']['grafted']);
        $this->assertSame(730, $grid['summary']['ready_to_plant']);
        $this->assertSame(130, $grid['summary']['regraft']);
        $this->assertSame(800, $grid['summary']['seedlings']);
    }
}
