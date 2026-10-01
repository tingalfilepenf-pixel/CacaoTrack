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
 *  - Total Active Inventory  -> Batch::totalAvailableQuantity()
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
     * the live available balance summed across ALL batches (archived included —
     * the deliberate existing design decision). Replaces the removed
     * `Inventory::totalActiveQuantity()`.
     */
    public static function totalActiveInventory(): int
    {
        return Batch::totalAvailableQuantity();
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
     * @return array{numerator:int,denominator:int}
     */
    public static function mortalityRateComponents(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        return [
            'numerator' => self::effectiveMortality($batchId, $scionVarietyId, $from, $to),
            'denominator' => (int) self::batchQuery($batchId, $scionVarietyId)->sum(DB::raw('total_grafted - regraft')),
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
     * per-Scion-Variety columns (matches the client's real manual ledger, not
     * just PRD §9.3's looser column list).
     *
     * Each month holds dated event rows and a per-month TOTAL; a flat grand
     * total is returned alongside. Columns: Seedlings, Grafted, Mortality,
     * Re Graft, Ready to Plant and Pullout — the last five broken out per Scion
     * Variety.
     *
     * "Ready to Plant" (client terminology, kept verbatim — no rename) is the
     * batch's surviving/sellable balance (`Batch::survivingQuantity()`, so
     * recorded mortality is excluded) and "Pullout" is the batch's cumulative
     * all-time Stock Out total. Both are per-batch CURRENT-STATE figures stamped
     * on the batch's `grafted_date` row — not month-scoped activity flow.
     *
     * @return array{varieties:array<int,array{id:int,name:string}>,months:array<int,array{key:string,label:string,rows:array<int,array<string,mixed>>,totals:array<string,mixed>}>,grandTotals:array<string,mixed>,summary:array<string,int>,hasData:bool}
     */
    public static function stockLedgerGrid(
        ?int $batchId = null,
        ?int $scionVarietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $referenced = [];
        $events = [];

        // Grafted + Ready to Plant + Pullout: one current-state row per batch
        // (by grafted_date). Phase 7C: only ROOT batches contribute to the
        // Grafted column — a regraft sub-batch's plants are a movement of the
        // same population and appear under Regraft, so family grafted totals are
        // not inflated.
        // "Ready to Plant" is the batch's surviving/sellable balance and
        // "Pullout" is its cumulative all-time Stock Out total; both are
        // current-state, per-batch figures stamped on the grafted month.
        $batches = self::batchQuery($batchId, $scionVarietyId)
            ->when($from, fn (Builder $q) => $q->whereDate('grafted_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('grafted_date', '<=', $to))
            ->orderBy('grafted_date')
            ->get();

        // Cumulative Pullout per batch — every Stock Out ever recorded against
        // the batch (all-time, deliberately NOT month-filtered), gathered in one
        // batched query (no N+1).
        $batchIds = $batches->pluck('batch_id')->map(fn ($id) => (int) $id)->all();
        $pulloutByBatch = $batchIds === []
            ? collect()
            : StockOutDetail::query()
                ->whereIn('batch_id', $batchIds)
                ->selectRaw('batch_id, COALESCE(SUM(quantity), 0) as total')
                ->groupBy('batch_id')
                ->pluck('total', 'batch_id');

        foreach ($batches as $batch) {
            if (! $batch->grafted_date) {
                continue;
            }

            $varietyId = $batch->scion_variety_id ? (int) $batch->scion_variety_id : null;

            if ($varietyId !== null) {
                $referenced[$varietyId] = true;
            }

            $isRoot = $batch->parent_batch_id === null;

            $events[] = [
                'date' => $batch->grafted_date->toDateString(),
                'description' => $isRoot
                    ? 'Batch '.$batch->batch_code.' — established / grafted'
                    : 'Sub-batch '.$batch->batch_code.' — ready to plant',
                'seedlings' => 0,
                'grafted' => $isRoot && $varietyId !== null ? [$varietyId => (int) $batch->total_grafted] : [],
                'mortality' => [],
                'regraft' => [],
                'ready' => $varietyId !== null ? [$varietyId => (int) $batch->survivingQuantity()] : [],
                'pullout' => $varietyId !== null ? [$varietyId => (int) ($pulloutByBatch[$batch->batch_id] ?? 0)] : [],
            ];
        }

        // Seedlings: Stock In (variety-agnostic; only when no batch narrowing).
        // Phase 7C: only seedling-only receipts count — a scion-inclusive Stock In
        // row (`scion_variety_id` set) must not inflate the Seedlings column.
        if ($batchId === null) {
            $stockIns = StockIn::query()
                ->whereNull('scion_variety_id')
                ->when($from, fn (Builder $q) => $q->whereDate('date_received', '>=', $from))
                ->when($to, fn (Builder $q) => $q->whereDate('date_received', '<=', $to))
                ->with('supplier')
                ->orderBy('date_received')
                ->get();

            foreach ($stockIns as $stockIn) {
                $origin = $stockIn->origin ? ' ('.$stockIn->origin.')' : '';

                $events[] = [
                    'date' => $stockIn->date_received->toDateString(),
                    'description' => 'Stock In — '.($stockIn->supplier?->supplier_name ?? 'Supplier').$origin,
                    'seedlings' => (int) $stockIn->quantity,
                    'grafted' => [],
                    'mortality' => [],
                    'regraft' => [],
                    'ready' => [],
                    'pullout' => [],
                ];
            }
        }

        // Mortality per Scion Variety.
        $mortalityRecords = self::mortalityQuery($batchId, $scionVarietyId, $from, $to)
            ->with('batch')
            ->get();

        foreach ($mortalityRecords as $record) {
            if (! $record->recorded_at) {
                continue;
            }

            $varietyId = $record->batch?->scion_variety_id ? (int) $record->batch->scion_variety_id : null;

            if ($varietyId !== null) {
                $referenced[$varietyId] = true;
            }

            $events[] = [
                'date' => $record->recorded_at->toDateString(),
                'description' => 'Mortality — '.($record->cause ?: 'Unspecified').' ('.($record->batch?->batch_code ?? '—').')',
                'seedlings' => 0,
                'grafted' => [],
                'mortality' => $varietyId !== null ? [$varietyId => (int) $record->quantity] : [],
                'regraft' => [],
                'ready' => [],
                'pullout' => [],
            ];
        }

        // Re Graft per Scion Variety. Regraft and Replanting are the same
        // recorded procedure, so the ledger reads the real `regraft_records`
        // rows. Phase 7C: the
        // column is the SUB-BATCH's scion variety and the description names the
        // source → sub-batch move.
        $regraftRecords = RegraftRecord::query()
            ->when($batchId, fn (Builder $q) => $q->where('batch_id', $batchId))
            ->when($from, fn (Builder $q) => $q->whereDate('regraft_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('regraft_date', '<=', $to))
            ->when($scionVarietyId, fn (Builder $q) => $q->whereHas('batch', fn (Builder $b) => $b->where('scion_variety_id', $scionVarietyId)))
            ->with(['batch', 'parent'])
            ->orderBy('regraft_date')
            ->get();

        foreach ($regraftRecords as $record) {
            $varietyId = $record->batch?->scion_variety_id ? (int) $record->batch->scion_variety_id : null;

            if ($varietyId !== null) {
                $referenced[$varietyId] = true;
            }

            $sourceCode = $record->parent?->batch_code ?? $record->batch?->batch_code ?? '—';
            $subBatchCode = $record->batch?->batch_code ?? '—';

            $events[] = [
                'date' => $record->regraft_date->toDateString(),
                'description' => 'Regraft '.$sourceCode.' → '.$subBatchCode.($record->remark ? ' — '.$record->remark : ''),
                'seedlings' => 0,
                'grafted' => [],
                'mortality' => [],
                'regraft' => $varietyId !== null ? [$varietyId => (int) $record->quantity] : [],
                'ready' => [],
                'pullout' => [],
            ];
        }

        // Pullout is NOT a dated event row here: it is a cumulative, per-batch
        // figure stamped on the batch's `grafted_date` row above (same scope as
        // "Ready to Plant"), so it is intentionally not month-filtered.

        $varieties = self::ledgerVarietyColumns($referenced, $scionVarietyId);

        usort($events, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        $months = [];
        $grandTotals = self::emptyLedgerTotals();

        foreach ($events as $event) {
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
            'hasData' => $events !== [],
            'batchSummary' => self::batchSummary($batchId, $scionVarietyId, $from, $to),
        ];
    }

    /**
     * Nested per-batch summary for the Stock Ledger (PRD §9.3): one row per
     * batch/sub-batch with the parent link, ordered through the single
     * {@see BatchTree} helper so parent batches and their regraft sub-batches
     * are clearly distinguishable. Every value is computed from real records;
     * nothing is stored (Phase 7C).
     *
     * @return array{rows:list<array<string,mixed>>,totals:array{total_grafted:int,available:int,regraft:int,mortality:int,stock_out:int}}
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
