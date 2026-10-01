<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\RegraftRecord;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7C: the seeded family's KPI population.
 *
 * Seed (DatabaseSeeder), under the CONFIRMED cumulative rule: `total_grafted`
 * never decreases, so B001 keeps 500 and B001-R1 keeps 100. The B001 family is
 * B001 500 + B001-R1 100 + B001-R1-R1 30 (raw sum 630), plus B002 150 and
 * B003 100 — a grand raw sum of 880 (KPI denominator 750 after removing the 130
 * regrafted plants). The still-attributable population (total_grafted - regraft)
 * is what is conserved at 500.
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

    public function test_crude_mortality_rate_denominator_is_the_still_attributable_population(): void
    {
        $components = AnalyticsMetrics::mortalityRateComponents();

        // Denominator = SUM(total_grafted - regraft) = (500-100) + (100-30) +
        // (30-0) + (150-0) + (100-0) = 400 + 70 + 30 + 150 + 100 = 750. The raw
        // cumulative sum (880) would double-count the 130 regrafted plants.
        $livePopulation = (int) Batch::query()
            ->get()
            ->sum(fn (Batch $batch) => (int) $batch->total_grafted - (int) $batch->regraft);

        $this->assertSame($livePopulation, $components['denominator']);
        $this->assertSame(750, $components['denominator']);

        // numerator 55 / 750 * 100 = 7.33%.
        $this->assertSame(55, $components['numerator']);
        $this->assertSame(7.33, AnalyticsMetrics::crudeMortalityRate());

        // total_grafted is a cumulative, non-decreasing log, so the family's raw
        // sum grows: 500 + 100 + 30 = 630. What is conserved is the still-
        // attributable population (total_grafted - regraft) = 400 + 70 + 30.
        $family = Batch::query()
            ->whereIn('batch_code', ['B001', 'B001-R1', 'B001-R1-R1'])
            ->get();

        $this->assertSame(630, (int) $family->sum('total_grafted'));
        $this->assertSame(
            500,
            (int) $family->sum(fn (Batch $batch) => (int) $batch->total_grafted - (int) $batch->regraft),
        );
    }

    public function test_the_seeded_family_regraft_counter_matches_its_records(): void
    {
        $regraft = (int) Batch::query()->sum('regraft');
        $records = (int) RegraftRecord::query()->sum('quantity');

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

    /**
     * Regression: the three cross-batch totals that previously summed the raw
     * cumulative `total_grafted` must now use `SUM(total_grafted - regraft)` so a
     * regrafted plant is counted once, not once per batch it has passed through.
     * The per-row `total_grafted` values stay raw (cumulative log).
     */
    public function test_cross_batch_totals_count_each_plant_once_not_the_raw_sum(): void
    {
        $rawSum = (int) Batch::query()->sum('total_grafted');
        $livePopulation = (int) Batch::query()
            ->get()
            ->sum(fn (Batch $batch) => (int) $batch->total_grafted - (int) $batch->regraft);

        $this->assertSame(880, $rawSum);
        $this->assertSame(750, $livePopulation);

        // 1. Stock Ledger nested Batch summary totals row.
        $batchTotals = AnalyticsMetrics::batchSummary()['totals'];
        $this->assertSame(750, $batchTotals['total_grafted']);
        $this->assertNotSame($rawSum, $batchTotals['total_grafted']);

        // 2. Gap Report expected total.
        $gapTotal = AnalyticsMetrics::gapReport()['totalExpected'];
        $this->assertSame(750, $gapTotal);
        $this->assertNotSame($rawSum, $gapTotal);

        // 3. Inventory snapshot total, both centralized and through the page.
        $snapshotTotal = (int) Inventory::query()->sum(DB::raw('total_grafted - regraft'));
        $this->assertSame(750, $snapshotTotal);
        $this->assertNotSame($rawSum, $snapshotTotal);

        $this->actingAs(User::where('email', 'admin@cacaotrack.test')->firstOrFail())
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('totalGrafted', 750);
    }

    public function test_ledger_summary_conserves_the_seeded_population(): void
    {
        $grid = AnalyticsMetrics::stockLedgerGrid();

        // Grafted = root batches only (B001 500 + B002 150 + B003 100).
        $this->assertSame(750, $grid['summary']['grafted']);
        // Ready to Plant (surviving sellable balance, mortality excluded) =
        // 375 + 50 + 30 + 135 + 90 = 680 (was 730 under the raw available_count).
        $this->assertSame(680, $grid['summary']['ready_to_plant']);
        // Pullout = cumulative all-time: the single B001-R1 release of 20.
        $this->assertSame(20, $grid['summary']['pullout']);
        $this->assertSame(130, $grid['summary']['regraft']);
        $this->assertSame(800, $grid['summary']['seedlings']);
    }
}
