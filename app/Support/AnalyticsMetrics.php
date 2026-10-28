<?php

namespace App\Support;

use App\Models\Analytic;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\StockOutDetail;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centralized analytics calculations (RULE 6).
 *
 * Every dashboard/report/chart value must trace back to actual database rows
 * through one of these definitions (RULE 12). Formulas that already exist on
 * the models are reused rather than re-implemented:
 *  - Total Active Inventory  -> SUM(batches.available_count) of ACTIVE batches
 *  - Total Archived Inventory -> SUM(batches.available_count) of archived batches
 *  - Regraft Deficit         -> Batch::regraftDeficit()
 *  - Effective mortality     -> MortalityRecord::effectiveQuantity()
 *
 * Definitions (approved, revised 15-entity model):
 *  - Crude Mortality Rate = effective mortality / total grafted * 100, for the
 *    filtered batches; the date range filters the mortality numerator.
 *  - Gap = Batch::effectiveMortalityQuantity() (recorded mortality not yet
 *    reflected in the live `available_count` balance).
 *  - Age = reference date - Batch.Grafted_Date, counted in inventory quantity
 *    buckets 0-30 / 31-60 / 61-90 / 91+ days.
 */
class AnalyticsMetrics
{
    /**
     * Restrict a batch query to the selected population.
     */
    protected static function batchQuery(?int $batchId = null, ?int $scionVarietyId = null): Builder
    {
        return Batch::query()
            ->when($batchId, fn (Builder $q) => $q->where('batch_id', $batchId))
            ->when($scionVarietyId, fn (Builder $q) => $q->where('scion_variety_id', $scionVarietyId));
    }

    /**
     * Restrict a mortality query by population and recorded-at date range.
     */
    protected static function mortalityQuery(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): Builder {
        return MortalityRecord::query()
            ->when($batchId, fn (Builder $q) => $q->where('batch_id', $batchId))
            ->when($scionVarietyId, fn (Builder $q) => $q->whereHas('batch', fn (Builder $b) => $b->where('scion_variety_id', $scionVarietyId)))
            ->when($from, fn (Builder $q) => $q->whereDate('recorded_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('recorded_at', '<=', $to));
    }

    /**
     * Effective mortality quantity (raw recorded quantity — adjustments were
     * removed with the revised model) for the filtered population and period.
     */
    public static function effectiveMortality(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): int {
        return (int) self::mortalityQuery($batchId, $scionVarietyId, $from, $to)
            ->get()
            ->sum(fn (MortalityRecord $record) => $record->effectiveQuantity());
    }

    /**
     * Total Active Inventory (the dashboard "Current Inventory" KPI, PRD §10):
     * the live available balance summed across ACTIVE batches only.
     *
     * Archived batches (`archived_at IS NOT NULL`) are EXCLUDED so an archived
     * batch no longer inflates the live figure; the archived portion is reported
     * separately by {@see self::totalArchivedInventory()}. Together the two
     * figures equal the gross `SUM(batches.available_count)` across every batch.
     * Replaces the removed `Inventory::totalActiveQuantity()`.
     */
    public static function totalActiveInventory(): int
    {
        return (int) Batch::query()->active()->sum('available_count');
    }

    /**
     * Total Archived Inventory: the live available balance held by archived
     * batches (`archived_at IS NOT NULL`). This is the counterpart of
     * {@see self::totalActiveInventory()} — it is shown beside the active figure
     * so nothing silently disappears when a batch is archived.
     */
    public static function totalArchivedInventory(): int
    {
        return (int) Batch::query()->archived()->sum('available_count');
    }

    /**
     * Total Grafted (the dashboard KPI, PRD §11 Total Grafted / ERD §3.8): the
     * cross-batch grafted population with each physical plant counted exactly
     * once, `SUM(total_grafted - regraft)`. Raw `SUM(total_grafted)` is a
     * cumulative log, so it double-counts every regrafted plant (once in the
     * source batch, again in its sub-batch, and again if that sub-batch is
     * itself regrafted) — see PRD §11. This is the same basis as the Inventory
     * page's "Total Grafted" card and {@see self::survivalVsMortality()}.
     *
     * ALL batches are included (active and archived): `total_grafted` is a
     * cumulative log that does not shrink when a batch is archived.
     */
    public static function totalGrafted(): int
    {
        return (int) Batch::query()->sum(DB::raw('total_grafted - regraft'));
    }

    public static function totalRegraftDeficit(): int
    {
        return (int) Batch::query()
            ->get()
            ->sum(fn (Batch $batch) => $batch->regraftDeficit());
    }

    /**
     * Crude Mortality Rate numerator/denominator (PRD §10 Dashboard / §11 Data
     * Consistency).
     *
     * Denominator = `SUM(total_grafted - regraft)` across the filtered batches,
     * the client-confirmed still-attributable/live population ("Grafted minus
     * Regraft = mortality or yield for the parent batch").
     *
     * Why not the raw `SUM(total_grafted)`: a batch's `total_grafted` is a
     * cumulative, non-decreasing log, so raw `SUM(total_grafted)` double-counts
     * every regrafted plant — once in the source batch's total, again in the
     * sub-batch's total, and a third time if that sub-batch is itself regrafted.
     * `SUM(total_grafted - regraft)` counts each physical plant exactly once, at
     * wherever it currently is.
     *
     * Date-filter symmetry (period rate): the NUMERATOR is the mortality
     * recorded within `[from, to]`, so when a date range is active the
     * DENOMINATOR must be the population present during that same period or the
     * rate is meaningless (period deaths ÷ all-time population). The rule chosen
     * here is the simplest defensible one: a batch contributes its FULL live
     * population when `grafted_date <= to`, because a plant grafted at or before
     * the end of the window existed for at least part of the window. A batch
     * grafted AFTER the window is excluded (it did not exist yet), and a batch
     * grafted before `from` is still included in full (it was present throughout
     * the window). No lower bound on `grafted_date` is applied — a plant already
     * standing when the window opens is exactly part of that period's population.
     * When no end date is supplied (no filter, or an open-ended `from`-only
     * filter) every batch counts, so the unfiltered rate is unchanged.
     *
     * @return array{numerator:int,denominator:int}
     */
    public static function mortalityRateComponents(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $denominator = self::batchQuery($batchId, $scionVarietyId);

        // Period denominator: only batches that already existed by the end of
        // the filter window (grafted_date is NOT NULL, so no null-handling).
        if ($to !== null) {
            $denominator->whereDate('grafted_date', '<=', $to);
        }

        return [
            'numerator' => self::effectiveMortality($batchId, $scionVarietyId, $from, $to),
            'denominator' => (int) $denominator->sum(DB::raw('total_grafted - regraft')),
        ];
    }

    public static function crudeMortalityRate(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): float {
        $parts = self::mortalityRateComponents($batchId, $scionVarietyId, $from, $to);

        if ($parts['denominator'] <= 0) {
            return 0.0;
        }

        return round($parts['numerator'] / $parts['denominator'] * 100, 2);
    }

    /**
     * Monthly effective mortality for the trailing N months.
     *
     * @return array{labels:array<int,string>,values:array<int,int>,total:int}
     */
    public static function mortalityTrend(int $months = 6, ?CarbonInterface $asOf = null): array
    {
        $end = $asOf ? Carbon::parse($asOf) : now();
        $start = $end->copy()->startOfMonth()->subMonths($months - 1);

        $sumByMonth = self::mortalityQuery(null, null, $start->toDateString(), $end->toDateString())
            ->get()
            ->groupBy(fn (MortalityRecord $record) => $record->recorded_at->format('Y-m'))
            ->map(fn (Collection $records) => (int) $records->sum(fn (MortalityRecord $r) => $r->effectiveQuantity()));

        $labels = [];
        $values = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $labels[] = $month->format('M Y');
            $values[] = (int) ($sumByMonth[$month->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values, 'total' => array_sum($values)];
    }

    /**
     * Live available inventory grouped by Scion Variety (PRD §11 "Available").
     *
     * @return array{labels:array<int,string>,values:array<int,int>}
     */
    public static function inventoryByScionVariety(): array
    {
        $rows = DB::table('batches')
            ->leftJoin('scion_varieties', 'scion_varieties.scion_variety_id', '=', 'batches.scion_variety_id')
            ->groupBy('scion_varieties.scion_variety_name')
            ->orderBy('scion_varieties.scion_variety_name')
            ->selectRaw("COALESCE(scion_varieties.scion_variety_name, 'Unassigned') as name, COALESCE(SUM(batches.available_count), 0) as value")
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'values' => $rows->pluck('value')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Regraft deficit grouped by batch.
     *
     * @return array{labels:array<int,string>,values:array<int,int>}
     */
    public static function regraftDeficitByBatch(): array
    {
        $batches = Batch::query()->orderBy('batch_code')->get();

        return [
            'labels' => $batches->pluck('batch_code')->all(),
            'values' => $batches->map(fn (Batch $batch) => $batch->regraftDeficit())->all(),
        ];
    }

    /**
     * Surviving vs mortality split based on the still-attributable population.
     *
     * The population basis is `SUM(total_grafted - regraft)` across the filtered
     * batches (client-confirmed: a batch's live population is
     * `total_grafted - regraft`). Raw `SUM(total_grafted)` double-counts every
     * regrafted plant (source + sub-batch, again if nested), so the two slices
     * would not match the real plant count. Mortality never mutates
     * `available_count`, so surviving = max(0, population - recorded mortality)
     * and the two slices sum to the live population (RULE 6).
     *
     * @return array{surviving:int,mortality:int,totalGrafted:int}
     */
    public static function survivalVsMortality(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $population = (int) self::batchQuery($batchId, $scionVarietyId)->sum(DB::raw('total_grafted - regraft'));
        $mortality = self::effectiveMortality($batchId, $scionVarietyId, $from, $to);

        return [
            'surviving' => max(0, $population - $mortality),
            'mortality' => $mortality,
            'totalGrafted' => $population,
        ];
    }

    /**
     * Mortality root-cause matrix: variety rows by cause columns.
     *
     * Each cell is the effective mortality (the raw recorded `Quantity`, since
     * adjustments were removed with the revised model) for that scion variety
     * and cause, via the same `MortalityRecord::effectiveQuantity()` the
     * Mortality module uses (RULE 6). Dimensions: cause, batch, scion variety,
     * quantity and time period (PRD §9.4).
     *
     * @return array{varieties:array<int,string>,causes:array<int,string>,rows:array<int,array{variety:string,cells:array<string,int>,total:int}>,totalsByCause:array<string,int>,grandTotal:int}
     */
    public static function mortalityMatrix(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $records = self::mortalityQuery($batchId, $scionVarietyId, $from, $to)
            ->with('batch.scionVariety')
            ->get();

        $byVariety = [];
        $causeSet = [];

        foreach ($records as $record) {
            $varietyName = $record->batch?->scionVariety?->scion_variety_name ?? 'Unassigned';
            // `Others` is a single reporting category: every `Others: <description>`
            // row collapses to `Others` so a spec does not split the column (RULE 6).
            $cause = MortalityRecord::normalizeCause($record->cause);
            $effective = $record->effectiveQuantity();

            $causeSet[$cause] = true;

            if (! isset($byVariety[$varietyName])) {
                $byVariety[$varietyName] = [
                    'variety' => $varietyName,
                    'cells' => [],
                    'total' => 0,
                ];
            }

            $byVariety[$varietyName]['cells'][$cause] = ($byVariety[$varietyName]['cells'][$cause] ?? 0) + $effective;
            $byVariety[$varietyName]['total'] += $effective;
        }

        ksort($byVariety);

        $causes = array_keys($causeSet);
        sort($causes);

        $rows = array_values($byVariety);

        $totalsByCause = [];

        foreach ($rows as $row) {
            foreach ($causes as $cause) {
                $effective = $row['cells'][$cause] ?? 0;
                $totalsByCause[$cause] = ($totalsByCause[$cause] ?? 0) + $effective;
            }
        }

        return [
            'varieties' => array_keys($byVariety),
            'causes' => $causes,
            'rows' => $rows,
            'totalsByCause' => $totalsByCause,
            'grandTotal' => (int) collect($rows)->sum('total'),
        ];
    }

    /**
     * Years that contain mortality records (for the seasonality filter).
     *
     * @return array<int,int>
     */
    public static function mortalityYears(): array
    {
        return MortalityRecord::query()
            ->selectRaw('YEAR(recorded_at) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }

    /**
     * Monthly mortality by Scion Variety for a calendar year (heatmap source).
     *
     * @return array{years:array<int,int>,selectedYear:int,months:array<int,string>,varieties:array<int,string>,data:array<int,array<int,int>>,max:int,total:int}
     */
    public static function seasonality(
        ?int $year = null,
        ?int $scionVarietyId = null,
        ?int $batchId = null,
    ): array {
        $years = self::mortalityYears();
        $selectedYear = $year ?? ($years[0] ?? (int) now()->year);

        $varieties = ScionVariety::query()
            ->when($scionVarietyId, fn (Builder $q) => $q->where('scion_variety_id', $scionVarietyId))
            ->orderBy('scion_variety_name')
            ->pluck('scion_variety_name')
            ->all();

        $records = self::mortalityQuery($batchId, $scionVarietyId)
            ->whereYear('recorded_at', $selectedYear)
            ->with('batch.scionVariety')
            ->get();

        $data = [];
        $max = 0;

        foreach ($records as $record) {
            $varietyName = $record->batch?->scionVariety?->scion_variety_name;
            $varietyIndex = array_search($varietyName, $varieties, true);

            if ($varietyIndex === false) {
                continue;
            }

            $monthIndex = (int) $record->recorded_at->format('n') - 1;
            $key = $monthIndex.'-'.$varietyIndex;
            $data[$key] = ($data[$key] ?? 0) + $record->effectiveQuantity();
            $max = max($max, $data[$key]);
        }

        $matrix = collect($data)
            ->map(function (int $value, string $key) {
                [$month, $variety] = array_map('intval', explode('-', $key));

                return [$month, $variety, $value];
            })
            ->values()
            ->all();

        return [
            'years' => $years,
            'selectedYear' => $selectedYear,
            'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'varieties' => $varieties,
            'data' => $matrix,
            'max' => $max,
            'total' => array_sum($data),
        ];
    }

    /**
     * Inventory quantity grouped by batch age bucket.
     *
     * Uses the live `batches.available_count` balance (the batch's current
     * available inventory), bucketed by the age derived from `grafted_date`.
     * Phase 7C also returns one nested row per batch/sub-batch (ordered through
     * the single {@see BatchTree} helper) with a Parent link, so a sub-batch is
     * shown as its own row with its own grafted date.
     *
     * @return array{labels:array<int,string>,values:array<int,int>,total:int,rows:list<array<string,mixed>>}
     */
    public static function ageProfile(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        CarbonInterface|string|null $asOf = null,
    ): array {
        $buckets = [
            '0-30 Days' => [0, 30],
            '31-60 Days' => [31, 60],
            '61-90 Days' => [61, 90],
            '91+ Days' => [91, null],
        ];

        $reference = ($asOf ? Carbon::parse($asOf) : now())->startOfDay();
        $totals = array_fill_keys(array_keys($buckets), 0);

        $batches = self::batchQuery($batchId, $scionVarietyId)->with('scionVariety')->get();

        $rows = [];

        foreach (BatchTree::flatten($batches) as $node) {
            $batch = $node['batch'];
            $quantity = (int) $batch->available_count;
            $age = null;
            $bucket = null;

            if ($batch->grafted_date) {
                $age = (int) $batch->grafted_date->copy()->startOfDay()->diffInDays($reference);

                foreach ($buckets as $label => [$min, $max]) {
                    if ($age >= $min && ($max === null || $age <= $max)) {
                        $bucket = $label;
                        $totals[$label] += $quantity;
                        break;
                    }
                }
            }

            $rows[] = [
                'batch' => $batch->batch_code,
                'parent' => $node['parent_code'],
                'parent_missing' => $node['parent_missing'],
                'depth' => $node['depth'],
                'variety' => $batch->scionVariety?->scion_variety_name ?? '—',
                'grafted_date' => $batch->grafted_date?->format('Y-m-d'),
                'age' => $age,
                'available' => $quantity,
                'bucket' => $bucket,
            ];
        }

        return [
            'labels' => array_keys($buckets),
            'values' => array_values($totals),
            'total' => array_sum($totals),
            'rows' => $rows,
        ];
    }

    /**
     * Gap report: per batch, the recorded mortality still unreflected in the
     * live available balance.
     *
     * Corrected Gap formula: gap = Batch::effectiveMortalityQuantity().
     *
     * Why: `available_count` already has every Stock Out fully netted out at
     * the time of release (`StockOutController` decrements it, and
     * `StoreStockOutRequest` + the controller's per-line/per-batch checks
     * enforce quantity <= `available_count` on every current, validated write).
     * The previous `max(0, viable_count - available_count - stock_out)` is
     * therefore a mathematical identity that always resolves to 0 regardless of
     * the data. Mortality is the only quantity currently unreflected in
     * `available_count`, so it is the actual gap: how much of the live
     * "available" balance is potentially overstated because recorded deaths have
     * not been reconciled out of it yet. `expected` (total_grafted), `actual`
     * (available_count) and `pulled_out` (summed Stock_Out_Detail quantity) are
     * retained as context columns only.
     *
     * `totalExpected` is the cross-batch total: it uses `SUM(total_grafted -
     * regraft)` so each physical plant is counted once, not once per batch it has
     * passed through via regraft (same basis as the Crude Mortality Rate
     * denominator, :122). The per-row `expected` values stay raw `total_grafted`.
     *
     * @return array{rows:array<int,array{batch:string,parent:?string,parent_missing:bool,depth:int,variety:string,expected:int,actual:int,pulled_out:int,gap:int}>,totalExpected:int,totalActual:int,totalPulledOut:int,totalGap:int}
     */
    public static function gapReport(?int $batchId = null, ?int $scionVarietyId = null): array
    {
        $batches = self::batchQuery($batchId, $scionVarietyId)
            ->with('scionVariety')
            ->get();

        $stockOutByBatch = StockOutDetail::query()
            ->when($batchId, fn (Builder $q) => $q->where('batch_id', $batchId))
            ->selectRaw('batch_id, COALESCE(SUM(quantity), 0) as total')
            ->groupBy('batch_id')
            ->pluck('total', 'batch_id');

        $rows = [];
        $livePopulation = 0;

        // Phase 7C: sub-batch rows are nested under their parent through the
        // single tree helper; the formula itself is unchanged (gap = effective
        // mortality).
        foreach (BatchTree::flatten($batches) as $node) {
            $batch = $node['batch'];
            $expected = (int) $batch->total_grafted;
            $actual = (int) $batch->available_count;
            $pulledOut = (int) ($stockOutByBatch[$batch->batch_id] ?? 0);

            // Cross-batch total: `total_grafted` is a cumulative log, so the raw
            // per-row sum double-counts every regrafted plant; count the live
            // population once instead (total_grafted - regraft).
            $livePopulation += $expected - (int) $batch->regraft;

            $rows[] = [
                'batch' => $batch->batch_code,
                'parent' => $node['parent_code'],
                'parent_missing' => $node['parent_missing'],
                'depth' => $node['depth'],
                'variety' => $batch->scionVariety?->scion_variety_name ?? '—',
                'expected' => $expected,
                'actual' => $actual,
                'pulled_out' => $pulledOut,
                'gap' => $batch->effectiveMortalityQuantity(),
            ];
        }

        return [
            'rows' => $rows,
            'totalExpected' => (int) $livePopulation,
            'totalActual' => (int) collect($rows)->sum('actual'),
            'totalPulledOut' => (int) collect($rows)->sum('pulled_out'),
            'totalGap' => (int) collect($rows)->sum('gap'),
        ];
    }

    /**
     * Cacao Tree Stock Ledger as a MONTHLY GRID grouped by month, with dynamic
     * per-Scion-Variety columns (matches the client's real manual ledger).
     *
     * The grid is a true chronological transaction ledger: every row is a real
     * dated operational event — batch establishment, each mortality record, each
     * regraft-out, and each Stock Out detail line (Pullout posts one dated row
     * per release, matching the client's spreadsheet) — plus the seedling Stock
     * In receipts.
     *
     * A running balance is still tracked per batch in true chronological order and
     * reconciled against the live surviving quantity as an internal integrity
     * guard, but it is no longer rendered on the page, PDF or CSV (owner-directed,
     * Session EA — the client's own ledger has no such column):
     *
     *   remaining(as of event N) = total_grafted_at_establishment
     *       - regraft_out_to_date - effective_mortality_to_date - stock_out_to_date
     *
     * The three outflows are gathered independently from the real records (never
     * read back from a value that already nets some of them out), so the LAST row
     * for a batch equals that batch's live {@see Batch::survivingQuantity()}. The
     * two are reconciled for every batch and any mismatch fails loudly
     * ({@see self::assertLedgerRunningBalanceReconciles()}).
     *
     * "Ready to Plant" (client terminology, kept verbatim — no rename) remains the
     * batch's current surviving balance, stamped on its establishment row.
     *
     * @return array{varieties:array<int,array{id:int,name:string}>,months:array<int,array{key:string,label:string,rows:array<int,array<string,mixed>>,totals:array<string,mixed>}>,grandTotals:array<string,mixed>,summary:array<string,int>,hasData:bool,batchSummary:array<string,mixed>}
     */
    public static function stockLedgerGrid(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        // Build the FULL chronological event log for every batch (all time) so
        // the running balance never depends on the display filters, then
        // reconcile every batch's final balance against the authoritative live
        // surviving quantity.
        $allBatches = Batch::query()
            ->with('scionVariety')
            ->orderBy('grafted_date')
            ->orderBy('batch_code')
            ->get();

        $events = self::ledgerEvents($allBatches);

        // True chronological order; the establishment precedes withdrawals that
        // share its date.
        usort($events, fn (array $a, array $b): int => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);

        $balances = [];
        $referenced = [];
        $displayed = [];

        // Pass 1 — running balance for EVERY event, in chronological order.
        foreach ($events as $index => $event) {
            $balanceBatchId = $event['balance_batch_id'];

            if ($balanceBatchId === null) {
                continue;
            }

            $balances[$balanceBatchId] = ($balances[$balanceBatchId] ?? 0) + (int) $event['balance_delta'];
            $events[$index]['remaining'] = $balances[$balanceBatchId];
        }

        self::assertLedgerRunningBalanceReconciles($balances, $allBatches);

        // Pass 2 — apply the display filters (batch / variety / date).
        foreach ($events as $event) {
            if ($event['seedling']) {
                if ($batchId !== null) {
                    continue;
                }
            } else {
                if ($batchId !== null && (int) $event['display_batch_id'] !== $batchId) {
                    continue;
                }

                if ($scionVarietyId !== null && (int) $event['display_variety_id'] !== $scionVarietyId) {
                    continue;
                }
            }

            if ($from !== null && $event['date'] < $from) {
                continue;
            }

            if ($to !== null && $event['date'] > $to) {
                continue;
            }

            foreach (['grafted', 'mortality', 'regraft', 'ready', 'pullout'] as $dimension) {
                foreach (array_keys($event[$dimension]) as $varietyId) {
                    $referenced[(int) $varietyId] = true;
                }
            }

            $displayed[] = $event;
        }

        $varieties = self::ledgerVarietyColumns($referenced, $scionVarietyId);

        $months = [];
        $grandTotals = self::emptyLedgerTotals();

        foreach ($displayed as $event) {
            $key = substr($event['date'], 0, 7);

            $months[$key] ??= [
                'key' => $key,
                'label' => Carbon::parse($event['date'].'-01')->format('F Y'),
                'rows' => [],
                'totals' => self::emptyLedgerTotals(),
            ];

            $months[$key]['rows'][] = $event;
            self::accumulateLedgerTotals($months[$key]['totals'], $event);
            self::accumulateLedgerTotals($grandTotals, $event);
        }

        ksort($months);

        $summary = [
            'seedlings' => $grandTotals['seedlings'],
            'grafted' => array_sum($grandTotals['grafted']),
            'mortality' => array_sum($grandTotals['mortality']),
            'regraft' => array_sum($grandTotals['regraft']),
            'ready_to_plant' => array_sum($grandTotals['ready']),
            'pullout' => array_sum($grandTotals['pullout']),
        ];

        return [
            'varieties' => $varieties,
            'months' => array_values($months),
            'grandTotals' => $grandTotals,
            'summary' => $summary,
            'hasData' => $displayed !== [],
            'batchSummary' => self::batchSummary($batchId, $scionVarietyId, $from, $to),
        ];
    }

    /**
     * Build the full, unfiltered chronological event log for the given batches.
     * Each event carries its date, the per-variety quantity dimensions the grid
     * renders, the batch/variety it is attributed to for filtering, and the batch
     * whose running balance it moves (with the signed delta).
     *
     * @param  Collection<int,Batch>  $batches
     * @return list<array<string,mixed>>
     */
    protected static function ledgerEvents(Collection $batches): array
    {
        $events = [];

        // Establishment — one current-state row per batch on its grafted date.
        // Only ROOT batches contribute to the Grafted column (a regraft
        // sub-batch's plants are a movement of the same population and appear
        // under Regraft); "Ready to Plant" stays a per-batch current-state figure.
        foreach ($batches as $batch) {
            if (! $batch->grafted_date) {
                continue;
            }

            $varietyId = $batch->scion_variety_id ? (int) $batch->scion_variety_id : null;
            $isRoot = $batch->parent_batch_id === null;

            $events[] = self::ledgerEvent([
                'date' => $batch->grafted_date->toDateString(),
                'sort' => 0,
                'description' => $isRoot
                    ? 'Batch '.$batch->batch_code.' — established / grafted'
                    : 'Sub-batch '.$batch->batch_code.' — ready to plant',
                'seedlings' => 0,
                'grafted' => $isRoot && $varietyId !== null ? [$varietyId => (int) $batch->total_grafted] : [],
                'ready' => $varietyId !== null ? [$varietyId => (int) $batch->survivingQuantity()] : [],
                'display_batch_id' => (int) $batch->batch_id,
                'display_variety_id' => $varietyId,
                'balance_batch_id' => (int) $batch->batch_id,
                'balance_delta' => (int) $batch->total_grafted,
            ]);
        }

        // Seedlings — variety-agnostic Stock In receipts (seedling-only rows).
        foreach (StockIn::query()->whereNull('scion_variety_id')->with('supplier')->get() as $stockIn) {
            if (! $stockIn->date_received) {
                continue;
            }

            $origin = $stockIn->origin ? ' ('.$stockIn->origin.')' : '';

            $events[] = self::ledgerEvent([
                'date' => $stockIn->date_received->toDateString(),
                'sort' => 1,
                'description' => 'Stock In — '.($stockIn->supplier?->supplier_name ?? 'Supplier').$origin,
                'seedling' => true,
                'seedlings' => (int) $stockIn->quantity,
                'display_batch_id' => null,
                'display_variety_id' => null,
                'balance_batch_id' => null,
                'balance_delta' => 0,
            ]);
        }

        // Mortality — a dated outflow per record.
        foreach (MortalityRecord::query()->with('batch')->get() as $record) {
            if (! $record->recorded_at) {
                continue;
            }

            $varietyId = $record->batch?->scion_variety_id ? (int) $record->batch->scion_variety_id : null;

            $events[] = self::ledgerEvent([
                'date' => $record->recorded_at->toDateString(),
                'sort' => 1,
                'description' => 'Mortality — '.($record->cause ?: 'Unspecified').' ('.($record->batch?->batch_code ?? '—').')',
                'mortality' => $varietyId !== null ? [$varietyId => (int) $record->quantity] : [],
                'display_batch_id' => (int) $record->batch_id,
                'display_variety_id' => $varietyId,
                'balance_batch_id' => (int) $record->batch_id,
                'balance_delta' => -(int) $record->quantity,
            ]);
        }

        // Regraft — a dated outflow from the SOURCE batch, shown (and filtered)
        // against the sub-batch it created, under the sub-batch's variety.
        foreach (RegraftRecord::query()->with(['batch', 'parent'])->get() as $record) {
            if (! $record->regraft_date) {
                continue;
            }

            $subBatch = $record->batch;
            $parent = $record->parent;
            $varietyId = $subBatch?->scion_variety_id ? (int) $subBatch->scion_variety_id : null;
            $sourceCode = $parent?->batch_code ?? $subBatch?->batch_code ?? '—';
            $subBatchCode = $subBatch?->batch_code ?? '—';

            $events[] = self::ledgerEvent([
                'date' => $record->regraft_date->toDateString(),
                'sort' => 1,
                'description' => 'Regraft '.$sourceCode.' → '.$subBatchCode.($record->remark ? ' — '.$record->remark : ''),
                'regraft' => $varietyId !== null ? [$varietyId => (int) $record->quantity] : [],
                'display_batch_id' => (int) $record->batch_id,
                'display_variety_id' => $varietyId,
                'balance_batch_id' => $parent ? (int) $parent->batch_id : null,
                'balance_delta' => -(int) $record->quantity,
            ]);
        }

        // Pullout — one dated row per Stock Out detail line (per the client's
        // spreadsheet), reducing the released batch's running balance.
        foreach (StockOutDetail::query()->with(['stockOut.client', 'batch'])->get() as $detail) {
            $releaseDate = $detail->stockOut?->release_date;

            if (! $releaseDate) {
                continue;
            }

            $batch = $detail->batch;
            $varietyId = $detail->scion_variety_id
                ? (int) $detail->scion_variety_id
                : ($batch?->scion_variety_id ? (int) $batch->scion_variety_id : null);
            $quantity = (int) $detail->quantity;
            $batchCode = $batch?->batch_code ?? '—';
            $clientName = $detail->stockOut?->client?->client_name ?? 'Client';

            $events[] = self::ledgerEvent([
                'date' => $releaseDate->toDateString(),
                'sort' => 1,
                'description' => 'Pullout '.$batchCode.' → '.$clientName.' ('.number_format($quantity).' '.$detail->unit.')',
                'pullout' => $varietyId !== null ? [$varietyId => $quantity] : [],
                'display_batch_id' => (int) $detail->batch_id,
                'display_variety_id' => $varietyId,
                'balance_batch_id' => (int) $detail->batch_id,
                'balance_delta' => -$quantity,
            ]);
        }

        return $events;
    }

    /**
     * Normalise a partial ledger event into the full shape the grid expects.
     *
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>
     */
    protected static function ledgerEvent(array $event): array
    {
        return array_merge([
            'seedling' => false,
            'seedlings' => 0,
            'grafted' => [],
            'mortality' => [],
            'regraft' => [],
            'ready' => [],
            'pullout' => [],
            'remaining' => null,
        ], $event);
    }

    /**
     * Reconcile every batch's final running balance against the authoritative
     * live {@see Batch::survivingQuantity()}. A disagreement means the ledger's
     * independent event mathematics and the live balance have diverged (e.g. a
     * drifted `batches.regraft` counter or `available_count`) — fail loudly
     * rather than render a plausible-looking but wrong figure.
     *
     * @param  array<int,int>  $balances
     * @param  Collection<int,Batch>  $batches
     */
    protected static function assertLedgerRunningBalanceReconciles(array $balances, Collection $batches): void
    {
        foreach ($batches as $batch) {
            if (! $batch->grafted_date) {
                continue;
            }

            $final = (int) ($balances[(int) $batch->batch_id] ?? 0);
            $surviving = $batch->survivingQuantity();

            if ($final !== $surviving) {
                throw new \RuntimeException(sprintf(
                    'Stock Ledger running balance does not reconcile for batch %s: running balance %d, Batch::survivingQuantity() %d.',
                    $batch->batch_code,
                    $final,
                    $surviving,
                ));
            }
        }
    }

    /**
     * Nested per-batch summary for the Stock Ledger (PRD §9.3): one row per
     * batch/sub-batch with the parent link, ordered through the single
     * {@see BatchTree} helper so parent batches and their regraft sub-batches
     * are clearly distinguishable. Every value is computed from real records;
     * nothing is stored (Phase 7C).
     *
     * @return array{rows:list<array<string,mixed>>,totals:array{total_grafted:int,available:int,regraft:int,mortality:int,family_mortality:int,stock_out:int}}
     */
    public static function batchSummary(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $batches = self::batchQuery($batchId, $scionVarietyId)
            ->when($from, fn (Builder $q) => $q->whereDate('grafted_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('grafted_date', '<=', $to))
            ->with('scionVariety')
            ->get();

        $batchIds = $batches->pluck('batch_id')->map(fn ($id) => (int) $id)->all();

        $mortalityByBatch = MortalityRecord::effectiveQuantityByBatch($batchIds);
        $stockOutByBatch = $batchIds === []
            ? collect()
            : StockOutDetail::query()
                ->whereIn('batch_id', $batchIds)
                ->selectRaw('batch_id, COALESCE(SUM(quantity), 0) as total')
                ->groupBy('batch_id')
                ->pluck('total', 'batch_id');

        $rows = [];

        foreach (BatchTree::flatten($batches) as $node) {
            $batch = $node['batch'];

            $rows[] = [
                'batch' => $batch->batch_code,
                'parent' => $node['parent_code'],
                'parent_missing' => $node['parent_missing'],
                'depth' => $node['depth'],
                'variety' => $batch->scionVariety?->scion_variety_name ?? '—',
                'total_grafted' => (int) $batch->total_grafted,
                'available' => (int) $batch->available_count,
                'regraft' => (int) $batch->regraft,
                'mortality' => (int) ($mortalityByBatch[$batch->batch_id] ?? 0),
                // Additional display figure: this batch plus all of its
                // descendant sub-batches. The per-batch `mortality` above is
                // unchanged (single-batch effective mortality).
                'family_mortality' => $batch->familyMortality(),
                'stock_out' => (int) ($stockOutByBatch[$batch->batch_id] ?? 0),
                'grafted_date' => $batch->grafted_date?->format('Y-m-d'),
            ];
        }

        return [
            'rows' => $rows,
            'totals' => [
                // Cross-batch total counts each physical plant once:
                // `total_grafted` is a cumulative, non-decreasing log, so summing
                // the raw per-row values would double-count every regrafted plant
                // (same basis as the Crude Mortality Rate denominator, :122).
                // The per-row `total_grafted` values above stay raw.
                'total_grafted' => (int) collect($rows)->sum(
                    fn (array $row): int => (int) $row['total_grafted'] - (int) $row['regraft'],
                ),
                'available' => (int) collect($rows)->sum('available'),
                'regraft' => (int) collect($rows)->sum('regraft'),
                'mortality' => (int) collect($rows)->sum('mortality'),
                // Family mortality double-counts by construction (a sub-batch
                // appears in its own row AND in each ancestor's rollup), so the
                // total sums ROOT rows only — each family counted once. This
                // equals the per-batch `mortality` total (both = global recorded
                // mortality), never the sum of the visible column.
                'family_mortality' => (int) collect($rows)
                    ->where('depth', 0)
                    ->sum('family_mortality'),
                'stock_out' => (int) collect($rows)->sum('stock_out'),
            ],
        ];
    }

    /**
     * Resolve the ordered Scion Variety column list for the stock ledger grid:
     * all active varieties plus any variety referenced by an event (so no data
     * silently disappears from the grid).
     *
     * @param  array<int,bool>  $referenced
     * @return array<int,array{id:int,name:string}>
     */
    protected static function ledgerVarietyColumns(array $referenced, ?int $onlyVarietyId = null): array
    {
        $names = ScionVariety::query()->pluck('scion_variety_name', 'scion_variety_id');

        foreach (array_keys($referenced) as $id) {
            if (! $names->has($id)) {
                $name = ScionVariety::query()->whereKey($id)->value('scion_variety_name');
                $names[$id] = $name ?? 'Unassigned';
            }
        }

        $columns = $names
            ->map(fn ($name, $id) => ['id' => (int) $id, 'name' => (string) $name])
            ->values();

        if ($onlyVarietyId !== null) {
            $columns = $columns->filter(fn (array $column) => $column['id'] === (int) $onlyVarietyId)->values();
        }

        return $columns
            ->sortBy(fn (array $column) => $column['name'])
            ->values()
            ->all();
    }

    /**
     * @return array{seedlings:int,grafted:array<int,int>,mortality:array<int,int>,regraft:array<int,int>,ready:array<int,int>,pullout:array<int,int>}
     */
    protected static function emptyLedgerTotals(): array
    {
        return [
            'seedlings' => 0,
            'grafted' => [],
            'mortality' => [],
            'regraft' => [],
            'ready' => [],
            'pullout' => [],
        ];
    }

    /**
     * @param  array<string,mixed>  $totals
     * @param  array<string,mixed>  $event
     */
    protected static function accumulateLedgerTotals(array &$totals, array $event): void
    {
        $totals['seedlings'] += (int) $event['seedlings'];

        foreach (['grafted', 'mortality', 'regraft', 'ready', 'pullout'] as $dimension) {
            foreach ((array) $event[$dimension] as $varietyId => $quantity) {
                $totals[$dimension][(int) $varietyId] = ($totals[$dimension][(int) $varietyId] ?? 0) + (int) $quantity;
            }
        }
    }

    /**
     * Totals for the Cacao Tree Stock Ledger — the same figures the printable
     * ledger renders in its totals strip, centralized so the on-screen grid,
     * the PDF and a generated report snapshot never diverge (RULE 6).
     *
     * @return array{batches:int,seedlings:int,grafted:int,mortality:int,regraft:int,ready_to_plant:int,pullout:int}
     */
    public static function stockLedgerSummary(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $grid = self::stockLedgerGrid($batchId, $scionVarietyId, $from, $to);

        return array_merge(
            ['batches' => self::batchQuery($batchId, $scionVarietyId)->count()],
            $grid['summary'],
        );
    }

    /**
     * Snapshot of the Mortality Root-Cause Matrix persisted with a generated
     * report (PRD §9.4). It carries the per-cause totals **and** the
     * per-variety cells plus the exact filters applied, so a later view can be
     * compared against it (RULE 12 traceability). Stored in the ERD `Analytics`
     * report history (ERD §9.4) — no new table/column.
     *
     * @return array{causes:array<int,string>,totalsByCause:array<string,int>,grandTotal:int,cellsByVariety:array<string,array<string,int>>,filters:array{batch_id:?int,scion_variety_id:?int,date_from:?string,date_to:?string}}
     */
    public static function mortalityMatrixSummary(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $matrix = self::mortalityMatrix($batchId, $scionVarietyId, $from, $to);

        return [
            'causes' => $matrix['causes'],
            'totalsByCause' => $matrix['totalsByCause'],
            'grandTotal' => $matrix['grandTotal'],
            'cellsByVariety' => collect($matrix['rows'])
                ->mapWithKeys(fn (array $row) => [$row['variety'] => $row['cells']])
                ->all(),
            'filters' => [
                'batch_id' => $batchId,
                'scion_variety_id' => $scionVarietyId,
                'date_from' => $from,
                'date_to' => $to,
            ],
        ];
    }

    /**
     * Compare the current Mortality Root-Cause Matrix against a previously
     * saved snapshot and report every place effective mortality has increased
     * (by any amount) — per cause and per variety×cause cell (PRD §9.4).
     *
     * The comparison is one centralized definition (RULE 6); the page alert and
     * cell/cause highlighting both read this result.
     *
     * @param  array<string,mixed>  $current
     * @param  array<string,mixed>|null  $baseline
     * @return array{hasBaseline:bool,causes:array<int,array{cause:string,previous:int,current:int,increase:int}>,cells:array<int,array{variety:string,cause:string,previous:int,current:int,increase:int}>}
     */
    public static function mortalityMatrixRegressions(array $current, ?array $baseline): array
    {
        if ($baseline === null || $baseline === []) {
            return ['hasBaseline' => false, 'causes' => [], 'cells' => []];
        }

        // Older snapshots may still hold a split `Others: <description>` key; roll
        // those into the single `Others` category before comparing so a stored
        // spec never reads as a brand-new cause that "increased" (RULE 6).
        $previousTotals = self::normalizeCauseMap(is_array($baseline['totalsByCause'] ?? null) ? $baseline['totalsByCause'] : []);
        $currentTotals = self::normalizeCauseMap(is_array($current['totalsByCause'] ?? null) ? $current['totalsByCause'] : []);

        $causes = [];

        foreach ($currentTotals as $cause => $currentValue) {
            $currentValue = (int) $currentValue;
            $previousValue = (int) ($previousTotals[$cause] ?? 0);

            if ($currentValue > $previousValue) {
                $causes[] = [
                    'cause' => (string) $cause,
                    'previous' => $previousValue,
                    'current' => $currentValue,
                    'increase' => $currentValue - $previousValue,
                ];
            }
        }

        $previousCells = collect(is_array($baseline['cellsByVariety'] ?? null) ? $baseline['cellsByVariety'] : [])
            ->map(fn ($rowCells) => self::normalizeCauseMap(is_array($rowCells) ? $rowCells : []))
            ->all();
        $cells = [];

        foreach ((array) ($current['rows'] ?? []) as $row) {
            $variety = (string) ($row['variety'] ?? '');

            foreach (self::normalizeCauseMap((array) ($row['cells'] ?? [])) as $cause => $currentValue) {
                $currentValue = (int) $currentValue;
                $previousValue = (int) ($previousCells[$variety][$cause] ?? 0);

                if ($currentValue > $previousValue) {
                    $cells[] = [
                        'variety' => $variety,
                        'cause' => (string) $cause,
                        'previous' => $previousValue,
                        'current' => $currentValue,
                        'increase' => $currentValue - $previousValue,
                    ];
                }
            }
        }

        return ['hasBaseline' => true, 'causes' => $causes, 'cells' => $cells];
    }

    /**
     * Re-key a cause=>quantity map by the shared reporting category
     * ({@see MortalityRecord::normalizeCause()}), summing any values that
     * collapse together. Keeps the `Others` roll-up in one place (RULE 6).
     *
     * @param  array<string|int,int|string>  $map
     * @return array<string,int>
     */
    protected static function normalizeCauseMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $cause => $value) {
            $category = MortalityRecord::normalizeCause((string) $cause);
            $normalized[$category] = ($normalized[$category] ?? 0) + (int) $value;
        }

        return $normalized;
    }

    /**
     * Compact summary persisted with a generated report (RULE 12 traceability).
     *
     * @param  array{date_from?:?string,date_to?:?string,scion_variety_id?:?int,batch_id?:?int,variety_id?:?int}  $filters
     * @return array<string,mixed>
     */
    public static function summaryFor(string $type, array $filters = []): array
    {
        $batchId = $filters['batch_id'] ?? null;
        $scionVarietyId = $filters['scion_variety_id'] ?? ($filters['variety_id'] ?? null);
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        return match ($type) {
            Analytic::TYPE_OPERATIONAL => [
                'total_active_inventory' => self::totalActiveInventory(),
                'total_archived_inventory' => self::totalArchivedInventory(),
                'crude_mortality_rate' => self::crudeMortalityRate($batchId, $scionVarietyId, $from, $to),
                'total_regraft_deficit' => self::totalRegraftDeficit(),
            ],
            Analytic::TYPE_STOCK_LEDGER => self::stockLedgerSummary($batchId, $scionVarietyId, $from, $to),
            Analytic::TYPE_MORTALITY_MATRIX => self::mortalityMatrixSummary($batchId, $scionVarietyId, $from, $to),
            Analytic::TYPE_SEASONALITY => collect(self::seasonality(null, $scionVarietyId, $batchId))
                ->only(['selectedYear', 'max', 'total'])
                ->all(),
            Analytic::TYPE_AGE_PROFILE => self::ageProfile($batchId, $scionVarietyId),
            Analytic::TYPE_GAP_REPORT => collect(self::gapReport($batchId, $scionVarietyId))
                ->only(['totalExpected', 'totalActual', 'totalPulledOut', 'totalGap'])
                ->all(),
            default => [],
        };
    }
}
