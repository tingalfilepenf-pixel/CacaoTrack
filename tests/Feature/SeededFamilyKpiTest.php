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

    /**
     * The Inventory summary cards read the same seeded live values as the
     * operational KPIs: mortality 55, regrafted 130, active inventory 730,
     * 5 batches.
     */
    public function test_inventory_summary_cards_match_the_seeded_live_kpis(): void
    {
        $this->actingAs(User::where('email', 'admin@cacaotrack.test')->firstOrFail())
            ->get(route('inventory.index'))
            ->assertOk()
            // Mortality: 25 + 5 + 15 + 10 = 55. Regrafted: B001 100 +
            // B001-R1 30 = 130. Active inventory: 400 + 50 + 30 + 150 + 100 =
            // 730. Batches: B001, B001-R1, B001-R1-R1, B002, B003.
            ->assertViewHas('totalMortality', 55)
            ->assertViewHas('totalRegrafted', 130)
            ->assertViewHas('totalActiveInventory', 730)
            ->assertViewHas('totalBatches', 5);
    }

    /**
     * An archived batch (legacy state — the archive route was replaced by a
     * guarded Delete) must move its available balance out of the active KPI and
     * into the archived KPI, so the two add back up to the gross all-batch total.
     */
    public function test_archiving_a_batch_moves_its_inventory_from_active_to_archived(): void
    {
        $batch = Batch::where('batch_code', 'B003')->firstOrFail();

        // Baseline: every seeded batch is active, so the archived KPI is 0 and
        // the active KPI is the full 730.
        $this->assertNull($batch->archived_at);
        $this->assertSame(730, AnalyticsMetrics::totalActiveInventory());
        $this->assertSame(0, AnalyticsMetrics::totalArchivedInventory());

        $batch->update(['archived_at' => now()]);
        $batch->refresh();
        $this->assertNotNull($batch->archived_at);

        $moved = (int) $batch->available_count;
        $this->assertSame(100, $moved);

        // The active figure drops by exactly this batch's available balance and
        // the archived figure rises by the same amount.
        $this->assertSame(730 - $moved, AnalyticsMetrics::totalActiveInventory());
        $this->assertSame(630, AnalyticsMetrics::totalActiveInventory());
        $this->assertSame($moved, AnalyticsMetrics::totalArchivedInventory());
        $this->assertSame(100, AnalyticsMetrics::totalArchivedInventory());

        // Conservation: active + archived still equals the gross all-batch sum.
        $this->assertSame(
            (int) Batch::query()->sum('available_count'),
            AnalyticsMetrics::totalActiveInventory() + AnalyticsMetrics::totalArchivedInventory(),
        );
    }

    /**
     * Date-filter asymmetry fix: with a date range active the denominator must
     * be the population present during that same window, not the all-time
     * population. Rule: full inclusion when `grafted_date <= to` (a plant that
     * existed at any point in the window contributes); a batch grafted after the
     * window is excluded.
     */
    public function test_date_filtered_mortality_rate_scopes_the_denominator_to_batches_present_in_the_window(): void
    {
        // B003's only mortality record was entered on 2026-08-28. Filtering to
        // that single day counts 10 deaths over the population that already
        // existed by 2026-08-28: B001 (400) + B002 (150) + B003 (100) = 650. The
        // two regraft sub-batches (B001-R1 70, grafted 2026-09-05; B001-R1-R1 30,
        // grafted 2026-09-08) did not exist yet and are excluded.
        $filtered = AnalyticsMetrics::mortalityRateComponents(null, null, '2026-08-28', '2026-08-28');

        $this->assertSame(10, $filtered['numerator']);
        $this->assertSame(650, $filtered['denominator']);
        $this->assertNotSame(
            AnalyticsMetrics::mortalityRateComponents()['denominator'],
            $filtered['denominator'],
        );
        $this->assertSame(1.54, AnalyticsMetrics::crudeMortalityRate(null, null, '2026-08-28', '2026-08-28'));

        // A wider window ending 2026-09-01 still excludes the September-grafted
        // sub-batches: B003 10 + B001 25 + B002 15 = 50 deaths over the same 650
        // (the pre-fix code wrongly divided by the all-time 750).
        $wider = AnalyticsMetrics::mortalityRateComponents(null, null, '2026-08-28', '2026-09-01');
        $this->assertSame(50, $wider['numerator']);
        $this->assertSame(650, $wider['denominator']);
        $this->assertNotSame(750, $wider['denominator']);

        // Once the window reaches the sub-batches' grafted dates they are
        // included again and the denominator is back to the full 750.
        $late = AnalyticsMetrics::mortalityRateComponents(null, null, '2026-09-10', '2026-09-10');
        $this->assertSame(5, $late['numerator']);
        $this->assertSame(750, $late['denominator']);
    }

    /**
     * The fix only changes behaviour when a date range is active: the unfiltered
     * rate stays exactly 55 / 750 = 7.33%, and an open-ended `from`-only filter
     * (no end date) still counts every batch.
     */
    public function test_unfiltered_mortality_rate_remains_the_all_time_baseline(): void
    {
        $components = AnalyticsMetrics::mortalityRateComponents();

        $this->assertSame(55, $components['numerator']);
        $this->assertSame(750, $components['denominator']);
        $this->assertSame(7.33, AnalyticsMetrics::crudeMortalityRate());

        $fromOnly = AnalyticsMetrics::mortalityRateComponents(null, null, '2026-09-01', null);
        $this->assertSame(750, $fromOnly['denominator']);
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

        // 3. Inventory snapshot total (centralized). The Inventory page now
        // shows live per-batch summary cards, not this snapshot total.
        $snapshotTotal = (int) Inventory::query()->sum(DB::raw('total_grafted - regraft'));
        $this->assertSame(750, $snapshotTotal);
        $this->assertNotSame($rawSum, $snapshotTotal);

        // 4. Dashboard "Total Grafted" KPI (centralized AnalyticsMetrics).
        $this->assertSame(750, AnalyticsMetrics::totalGrafted());
        $this->assertNotSame($rawSum, AnalyticsMetrics::totalGrafted());
    }

    public function test_ledger_summary_conserves_the_seeded_population(): void
    {
        $grid = AnalyticsMetrics::stockLedgerGrid();

        // Grafted = root batches only (B001 500 + B002 150 + B003 100).
        $this->assertSame(750, $grid['summary']['grafted']);
        // Ready to Plant (surviving sellable balance: mortality AND stock-out
        // excluded) = 375 + 45 + 30 + 135 + 90 = 675. B001-R1 is 45 (was 50
        // under the old one-sided cap that only subtracted the larger of
        // mortality / stock-out), reflecting the approved gate alignment.
        $this->assertSame(675, $grid['summary']['ready_to_plant']);
        // Pullout now posts one dated row per Stock Out release; the total is
        // unchanged at the single B001-R1 release of 20.
        $this->assertSame(20, $grid['summary']['pullout']);
        $this->assertSame(130, $grid['summary']['regraft']);
        $this->assertSame(800, $grid['summary']['seedlings']);
    }

    /**
     * Family mortality rolls up a batch plus every descendant sub-batch
     * (recursively), while the per-batch effective mortality stays untouched.
     */
    public function test_family_mortality_rolls_up_descendant_sub_batches(): void
    {
        $family = Batch::query()
            ->whereIn('batch_code', ['B001', 'B001-R1', 'B001-R1-R1', 'B002', 'B003'])
            ->get()
            ->keyBy('batch_code');

        // Descendant closure of B001 is the whole B001 family.
        $this->assertEqualsCanonicalizing(
            [
                $family['B001']->batch_id,
                $family['B001-R1']->batch_id,
                $family['B001-R1-R1']->batch_id,
            ],
            $family['B001']->familyBatchIds(),
        );

        // B001: own mortality 25 + B001-R1 5 + B001-R1-R1 0 = 30.
        $this->assertSame(25, $family['B001']->effectiveMortalityQuantity());
        $this->assertSame(30, $family['B001']->familyMortality());

        // B001-R1: own 5 + descendant B001-R1-R1 0 = 5.
        $this->assertSame(5, $family['B001-R1']->effectiveMortalityQuantity());
        $this->assertSame(5, $family['B001-R1']->familyMortality());

        // B001-R1-R1: leaf — no descendants, no mortality.
        $this->assertSame(0, $family['B001-R1-R1']->effectiveMortalityQuantity());
        $this->assertSame(0, $family['B001-R1-R1']->familyMortality());

        // No sub-batches: familyMortality === effectiveMortality (unchanged).
        foreach (['B002' => 15, 'B003' => 10] as $code => $expected) {
            $this->assertSame($expected, $family[$code]->effectiveMortalityQuantity());
            $this->assertSame(
                $family[$code]->effectiveMortalityQuantity(),
                $family[$code]->familyMortality(),
            );
        }
    }

    /**
     * Item 4: the family rollup is ADDITIONAL only — the Crude Mortality Rate
     * numerator and the Gap Report keep summing per-batch effective mortality
     * across the full batch list (each batch once), so they are unchanged.
     */
    public function test_family_mortality_rollup_does_not_change_the_headline_kpis(): void
    {
        $batches = Batch::query()->get();

        $perBatchTotal = (int) $batches->sum(
            fn (Batch $batch) => $batch->effectiveMortalityQuantity(),
        );
        $this->assertSame(55, $perBatchTotal);

        // Summing familyMortality over EVERY row double-counts descendants
        // (B001 30 + B001-R1 5 + B001-R1-R1 0 + B002 15 + B003 10 = 60). The
        // KPIs must NOT equal this.
        $familySumOverAllRows = (int) $batches->sum(
            fn (Batch $batch) => $batch->familyMortality(),
        );
        $this->assertSame(60, $familySumOverAllRows);
        $this->assertNotSame($perBatchTotal, $familySumOverAllRows);

        // Crude Mortality Rate numerator/denominator unchanged: 55 / 750 = 7.33%.
        $components = AnalyticsMetrics::mortalityRateComponents();
        $this->assertSame(55, $components['numerator']);
        $this->assertSame($perBatchTotal, $components['numerator']);
        $this->assertSame(750, $components['denominator']);
        $this->assertSame(7.33, AnalyticsMetrics::crudeMortalityRate());

        // Gap Report total unchanged (per-batch effective mortality = 55).
        $gap = AnalyticsMetrics::gapReport();
        $this->assertSame(55, $gap['totalGap']);
        $this->assertSame($perBatchTotal, $gap['totalGap']);
    }
}
