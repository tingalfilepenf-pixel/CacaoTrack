<?php

namespace App\Support;

use App\Models\Analytic;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\Variety;
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
 *  - Total Active Inventory  -> Inventory::totalActiveQuantity()
 *  - Replanting Deficit      -> Batch::replantingDeficit()
 *  - Effective mortality     -> MortalityRecord::effectiveQuantity()
 *
 * Definitions (approved):
 *  - Crude Mortality Rate = effective mortality / total grafted * 100,
 *    for the filtered batches; the date range filters the mortality numerator.
 *  - Gap = max(0, Batch.Viable_Count - Inventory.Current_Quantity).
 *  - Age = reference date - Batch.Grafted_Date, counted in inventory quantity
 *    buckets 0-30 / 31-60 / 61-90 / 91+ days.
 */
class AnalyticsMetrics
{
    /**
     * Restrict a batch query to the selected population.
     */
    protected static function batchQuery(?int $batchId = null, ?int $varietyId = null): Builder
    {
        return Batch::query()
            ->when($batchId, fn (Builder $q) => $q->where('batch_id', $batchId))
            ->when($varietyId, fn (Builder $q) => $q->where('variety_id', $varietyId));
    }

    /**
     * Restrict a mortality query by population and recorded-at date range.
     */
    protected static function mortalityQuery(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): Builder {
        return MortalityRecord::query()
            ->when($batchId, fn (Builder $q) => $q->where('batch_id', $batchId))
            ->when($varietyId, fn (Builder $q) => $q->whereHas('batch', fn (Builder $b) => $b->where('variety_id', $varietyId)))
            ->when($from, fn (Builder $q) => $q->whereDate('recorded_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('recorded_at', '<=', $to));
    }

    /**
     * Effective mortality quantity (records + signed adjustments) for the
     * filtered population and period.
     */
    public static function effectiveMortality(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): int {
        return (int) self::mortalityQuery($batchId, $varietyId, $from, $to)
            ->with('adjustments')
            ->get()
            ->sum(fn (MortalityRecord $record) => $record->effectiveQuantity());
    }

    public static function totalActiveInventory(): int
    {
        return Inventory::totalActiveQuantity();
    }

    public static function totalReplantingDeficit(): int
    {
        return (int) Batch::query()
            ->get()
            ->sum(fn (Batch $batch) => $batch->replantingDeficit());
    }

    /**
     * Crude Mortality Rate numerator/denominator (PRD §12.2).
     *
     * @return array{numerator:int,denominator:int}
     */
    public static function mortalityRateComponents(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        return [
            'numerator' => self::effectiveMortality($batchId, $varietyId, $from, $to),
            'denominator' => (int) self::batchQuery($batchId, $varietyId)->sum('total_grafted'),
        ];
    }

    public static function crudeMortalityRate(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): float {
        $parts = self::mortalityRateComponents($batchId, $varietyId, $from, $to);

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
            ->with('adjustments')
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
     * Active inventory grouped by variety.
     *
     * @return array{labels:array<int,string>,values:array<int,int>}
     */
    public static function inventoryByVariety(): array
    {
        $rows = DB::table('inventories')
            ->join('batches', 'batches.batch_id', '=', 'inventories.batch_id')
            ->join('varieties', 'varieties.variety_id', '=', 'batches.variety_id')
            ->groupBy('varieties.variety_name')
            ->orderBy('varieties.variety_name')
            ->selectRaw('varieties.variety_name as name, COALESCE(SUM(inventories.current_quantity), 0) as value')
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'values' => $rows->pluck('value')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Replanting deficit grouped by batch.
     *
     * @return array{labels:array<int,string>,values:array<int,int>}
     */
    public static function replantingDeficitByBatch(): array
    {
        $batches = Batch::query()->orderBy('batch_code')->get();

        return [
            'labels' => $batches->pluck('batch_code')->all(),
            'values' => $batches->map(fn (Batch $batch) => $batch->replantingDeficit())->all(),
        ];
    }

    /**
     * Surviving vs mortality split based on viable counts.
     *
     * @return array{surviving:int,mortality:int,totalViable:int}
     */
    public static function survivalVsMortality(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        // `batch.viable_count` is now the current alive count: Mortality Tracking
        // already deducts recorded deaths from it (approved change — mortality is
        // the only operation that updates plant status). Surviving is therefore
        // the stored viable count; `totalViable` reconstructs the original viable
        // population (surviving + recorded mortality) so the two slices still sum
        // to the starting population (RULE 6).
        $viable = (int) self::batchQuery($batchId, $varietyId)->sum('viable_count');
        $mortality = self::effectiveMortality($batchId, $varietyId, $from, $to);
        $surviving = max(0, $viable);

        return [
            'surviving' => $surviving,
            'mortality' => $mortality,
            'totalViable' => $surviving + $mortality,
        ];
    }

    /**
     * Mortality root-cause matrix: variety rows by cause columns.
     *
     * Each cell is the **effective** mortality (raw `Quantity` + signed
     * `Adjustment_Quantity`) for that variety/cause, via the same
     * `MortalityRecord::effectiveQuantity()` the Mortality module uses
     * (RULE 6). The raw and adjustment components are returned alongside the
     * effective values so the Raw -> Adjustments -> Effective chain is
     * traceable on the page (PRD §13.2 / §25, RULE 12).
     *
     * @return array{varieties:array<int,string>,causes:array<int,string>,rows:array<int,array{variety:string,cells:array<string,int>,rawCells:array<string,int>,adjustmentCells:array<string,int>,total:int,rawTotal:int,adjustmentTotal:int}>,totalsByCause:array<string,int>,rawTotalsByCause:array<string,int>,adjustmentTotalsByCause:array<string,int>,rawTotal:int,adjustmentTotal:int,grandTotal:int}
     */
    public static function mortalityMatrix(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $records = self::mortalityQuery($batchId, $varietyId, $from, $to)
            ->with(['adjustments', 'batch.variety'])
            ->get();

        $byVariety = [];
        $causeSet = [];

        foreach ($records as $record) {
            $varietyName = $record->batch?->variety?->variety_name ?? 'Unassigned';
            $cause = $record->cause ?: 'Unspecified';
            $raw = (int) $record->quantity;
            $effective = $record->effectiveQuantity();

            $causeSet[$cause] = true;

            if (! isset($byVariety[$varietyName])) {
                $byVariety[$varietyName] = [
                    'variety' => $varietyName,
                    'cells' => [],
                    'rawCells' => [],
                    'total' => 0,
                    'rawTotal' => 0,
                ];
            }

            $byVariety[$varietyName]['cells'][$cause] = ($byVariety[$varietyName]['cells'][$cause] ?? 0) + $effective;
            $byVariety[$varietyName]['rawCells'][$cause] = ($byVariety[$varietyName]['rawCells'][$cause] ?? 0) + $raw;
            $byVariety[$varietyName]['total'] += $effective;
            $byVariety[$varietyName]['rawTotal'] += $raw;
        }

        ksort($byVariety);

        $causes = array_keys($causeSet);
        sort($causes);

        $rows = array_values($byVariety);

        $totalsByCause = [];
        $rawTotalsByCause = [];
        $adjustmentTotalsByCause = [];

        foreach ($rows as $index => $row) {
            $rows[$index]['adjustmentTotal'] = $row['total'] - $row['rawTotal'];
            $rows[$index]['adjustmentCells'] = [];

            foreach ($causes as $cause) {
                $effective = $row['cells'][$cause] ?? 0;
                $raw = $row['rawCells'][$cause] ?? 0;

                $rows[$index]['adjustmentCells'][$cause] = $effective - $raw;

                $totalsByCause[$cause] = ($totalsByCause[$cause] ?? 0) + $effective;
                $rawTotalsByCause[$cause] = ($rawTotalsByCause[$cause] ?? 0) + $raw;
                $adjustmentTotalsByCause[$cause] = ($adjustmentTotalsByCause[$cause] ?? 0) + ($effective - $raw);
            }
        }

        $rawTotal = (int) collect($rows)->sum('rawTotal');
        $grandTotal = (int) collect($rows)->sum('total');

        return [
            'varieties' => array_keys($byVariety),
            'causes' => $causes,
            'rows' => $rows,
            'totalsByCause' => $totalsByCause,
            'rawTotalsByCause' => $rawTotalsByCause,
            'adjustmentTotalsByCause' => $adjustmentTotalsByCause,
            'rawTotal' => $rawTotal,
            'adjustmentTotal' => $grandTotal - $rawTotal,
            'grandTotal' => $grandTotal,
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
     * Monthly mortality by variety for a calendar year (heatmap source).
     *
     * @return array{years:array<int,int>,selectedYear:int,months:array<int,string>,varieties:array<int,string>,data:array<int,array<int,int>>,max:int,total:int}
     */
    public static function seasonality(
        ?int $year = null,
        ?int $varietyId = null,
        ?int $batchId = null,
    ): array {
        $years = self::mortalityYears();
        $selectedYear = $year ?? ($years[0] ?? (int) now()->year);

        $varieties = Variety::query()
            ->when($varietyId, fn (Builder $q) => $q->where('variety_id', $varietyId))
            ->orderBy('variety_name')
            ->pluck('variety_name')
            ->all();

        $records = self::mortalityQuery($batchId, $varietyId)
            ->whereYear('recorded_at', $selectedYear)
            ->with(['adjustments', 'batch.variety'])
            ->get();

        $data = [];
        $max = 0;

        foreach ($records as $record) {
            $varietyName = $record->batch?->variety?->variety_name;
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
     * @return array{labels:array<int,string>,values:array<int,int>,total:int}
     */
    public static function ageProfile(
        ?int $batchId = null,
        ?int $varietyId = null,
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

        $batches = self::batchQuery($batchId, $varietyId)->with('inventory')->get();

        foreach ($batches as $batch) {
            if (! $batch->grafted_date) {
                continue;
            }

            $age = (int) $batch->grafted_date->copy()->startOfDay()->diffInDays($reference);
            $quantity = (int) ($batch->inventory?->current_quantity ?? 0);

            foreach ($buckets as $label => [$min, $max]) {
                if ($age >= $min && ($max === null || $age <= $max)) {
                    $totals[$label] += $quantity;
                    break;
                }
            }
        }

        return [
            'labels' => array_keys($buckets),
            'values' => array_values($totals),
            'total' => array_sum($totals),
        ];
    }

    /**
     * Gap report: expected viable vs actual inventory per batch.
     *
     * @return array{rows:array<int,array{batch:string,variety:string,expected:int,actual:int,gap:int}>,totalExpected:int,totalActual:int,totalGap:int}
     */
    public static function gapReport(?int $batchId = null, ?int $varietyId = null): array
    {
        $batches = self::batchQuery($batchId, $varietyId)
            ->with(['inventory', 'variety'])
            ->orderBy('batch_code')
            ->get();

        $rows = $batches->map(function (Batch $batch) {
            $expected = (int) $batch->viable_count;
            $actual = (int) ($batch->inventory?->current_quantity ?? 0);

            return [
                'batch' => $batch->batch_code,
                'variety' => $batch->variety?->variety_name ?? '—',
                'expected' => $expected,
                'actual' => $actual,
                'gap' => max(0, $expected - $actual),
            ];
        })->all();

        return [
            'rows' => $rows,
            'totalExpected' => (int) collect($rows)->sum('expected'),
            'totalActual' => (int) collect($rows)->sum('actual'),
            'totalGap' => (int) collect($rows)->sum('gap'),
        ];
    }

    /**
     * Stock ledger query (per-batch summary); the controller paginates it.
     */
    public static function stockLedgerQuery(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): Builder {
        return self::batchQuery($batchId, $varietyId)
            ->with(['variety', 'scion', 'potType', 'inventory'])
            ->when($from, fn (Builder $q) => $q->whereDate('grafted_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('grafted_date', '<=', $to))
            ->orderBy('batch_code');
    }

    /**
     * Totals for the Cacao Tree Stock Ledger (PRD §13.1) — the same figures the
     * printable ledger renders in its totals strip, centralized so the on-screen
     * ledger, the PDF and a generated report snapshot never diverge (RULE 6/25).
     *
     * @return array{batches:int,grafted:int,viable:int,available:int,mortality:int,replanting:int}
     */
    public static function stockLedgerSummary(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $batches = self::stockLedgerQuery($batchId, $varietyId, $from, $to)->get();

        return [
            'batches' => $batches->count(),
            'grafted' => (int) $batches->sum('total_grafted'),
            'viable' => (int) $batches->sum('viable_count'),
            'available' => (int) $batches->sum(fn (Batch $batch) => $batch->inventory?->current_quantity ?? $batch->available_count),
            'mortality' => (int) $batches->sum(fn (Batch $batch) => $batch->effectiveMortalityQuantity()),
            'replanting' => (int) $batches->sum(fn (Batch $batch) => $batch->totalReplanted()),
        ];
    }

    /**
     * Snapshot of the Mortality Root-Cause Matrix persisted with a generated
     * report (PRD §13.2). It carries the per-cause totals **and** the
     * per-variety cells plus the exact filters applied, so a later view can be
     * compared against it (RULE 12 traceability). Stored in the ERD `Analytics`
     * report history (ERD §9.4) — no new table/column.
     *
     * @return array{causes:array<int,string>,totalsByCause:array<string,int>,grandTotal:int,cellsByVariety:array<string,array<string,int>>,filters:array{batch_id:?int,variety_id:?int,date_from:?string,date_to:?string}}
     */
    public static function mortalityMatrixSummary(
        ?int $batchId = null,
        ?int $varietyId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $matrix = self::mortalityMatrix($batchId, $varietyId, $from, $to);

        return [
            'causes' => $matrix['causes'],
            'totalsByCause' => $matrix['totalsByCause'],
            'grandTotal' => $matrix['grandTotal'],
            'cellsByVariety' => collect($matrix['rows'])
                ->mapWithKeys(fn (array $row) => [$row['variety'] => $row['cells']])
                ->all(),
            'filters' => [
                'batch_id' => $batchId,
                'variety_id' => $varietyId,
                'date_from' => $from,
                'date_to' => $to,
            ],
        ];
    }

    /**
     * Compare the current Mortality Root-Cause Matrix against a previously
     * saved snapshot and report every place effective mortality has increased
     * (by any amount) — per cause and per variety×cause cell (PRD §13.2).
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

        $previousTotals = is_array($baseline['totalsByCause'] ?? null) ? $baseline['totalsByCause'] : [];
        $currentTotals = is_array($current['totalsByCause'] ?? null) ? $current['totalsByCause'] : [];

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

        $previousCells = is_array($baseline['cellsByVariety'] ?? null) ? $baseline['cellsByVariety'] : [];
        $cells = [];

        foreach ((array) ($current['rows'] ?? []) as $row) {
            $variety = (string) ($row['variety'] ?? '');

            foreach ((array) ($row['cells'] ?? []) as $cause => $currentValue) {
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
     * Compact summary persisted with a generated report (RULE 12 traceability).
     *
     * @param  array{date_from?:?string,date_to?:?string,variety_id?:?int,batch_id?:?int}  $filters
     * @return array<string,mixed>
     */
    public static function summaryFor(string $type, array $filters = []): array
    {
        $batchId = $filters['batch_id'] ?? null;
        $varietyId = $filters['variety_id'] ?? null;
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        return match ($type) {
            Analytic::TYPE_OPERATIONAL => [
                'total_active_inventory' => self::totalActiveInventory(),
                'crude_mortality_rate' => self::crudeMortalityRate($batchId, $varietyId, $from, $to),
                'total_replanting_deficit' => self::totalReplantingDeficit(),
            ],
            Analytic::TYPE_STOCK_LEDGER => self::stockLedgerSummary($batchId, $varietyId, $from, $to),
            Analytic::TYPE_MORTALITY_MATRIX => self::mortalityMatrixSummary($batchId, $varietyId, $from, $to),
            Analytic::TYPE_SEASONALITY => collect(self::seasonality(null, $varietyId, $batchId))
                ->only(['selectedYear', 'max', 'total'])
                ->all(),
            Analytic::TYPE_AGE_PROFILE => self::ageProfile($batchId, $varietyId),
            Analytic::TYPE_GAP_REPORT => collect(self::gapReport($batchId, $varietyId))
                ->only(['totalExpected', 'totalActual', 'totalGap'])
                ->all(),
            default => [],
        };
    }
}
