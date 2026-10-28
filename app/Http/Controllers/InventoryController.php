<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventorySnapshotRequest;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\ScionVariety;
use App\Support\BatchTree;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * FR-08 Inventory listing (owner-directed template): one row per batch —
     * parent batches and their linked regraft sub-batches, nested through the
     * single BatchTree helper — showing the live counts derived from actual
     * operational records (Batch, Mortality, Regraft). `Available` goes
     * through the centralized Batch definitions (RULE 5/6).
     *
     * Owner-directed row action: each row opens the same record forms as the
     * operational pages — the Mortality form (`operational.mortality.records.
     * store`) and the Regraft form (`operational.regraft.records.store`) — in
     * modals hosted on this page, prefilled with the clicked batch and still
     * validated by their own Form Requests / controllers (RULE 1/6). The
     * modal dropdowns read the same centralized Batch definitions
     * ({@see Batch::quantityBreakdown()} / {@see Batch::survivingQuantity()})
     * as the operational pages. The read-only View modal also lists the
     * recorded mortality causes behind the batch's mortality figure
     * ({@see MortalityRecord::causeBreakdownByBatch()}, one grouped query).
     * The "Record Monthly Inventory" page/row action
     * stays removed; the FR-08 snapshot writer (`store()` +
     * `StoreInventorySnapshotRequest` + `inventory.snapshots.store`) is kept
     * for the backend.
     *
     * Nested sub-batches are collapsible through the same chevron toggle as the
     * Batches page: each node's ancestor ids and whether it has visible children
     * travel in `nodeMeta`, so a parent can hide its whole subtree.
     *
     * Filters: `search`/`scion_variety_id` apply to the batch; `status` filters
     * the derived lifecycle status through the same centralized definition as
     * the Status column ({@see Batch::scopeWhereLifecycleStatus()});
     * `month`/`year` filter the batch's `grafted_date` (the row's Batch Date).
     *
     * Summary cards (owner-directed): Total Mortality, Total Regrafted, Total
     * Active Inventory and Total Batches. Each card aggregates the SAME live
     * per-batch definitions shown on the rows, over the filtered set, so a card
     * can never disagree with its table (RULE 5/6).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'scion_variety_id' => ['nullable', 'integer', 'exists:scion_varieties,scion_variety_id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Batch::LIFECYCLE_STATUSES)],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $base = Batch::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('batch_code', 'like', "%{$search}%"))
            ->when($filters['scion_variety_id'] ?? null, fn ($query, $scionVarietyId) => $query->where('scion_variety_id', $scionVarietyId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereLifecycleStatus($status))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('grafted_date', $year))
            ->when($filters['month'] ?? null, fn ($query, $month) => $query->whereMonth('grafted_date', $month));

        $filtered = (clone $base)
            ->with(['scionVariety', 'parent'])
            ->withSum('mortalityRecords as mortality_sum', 'quantity')
            ->withMax('regraftRecordsAsParent as latest_regraft_date', 'regraft_date')
            ->get();

        // Nest the rows through the single tree helper (RULE 6); the flattened
        // order is the page order (a parent followed by its subtree). The
        // listing is newest-first (owner-directed): roots are ordered by
        // `grafted_date` descending, so a newly added batch lands at the top.
        // Each nested row carries its ancestor ids plus whether it has visible
        // children, so the table can use the same collapsible sub-batch toggle
        // as the Batches page (a parent's chevron collapses its whole subtree).
        $treeOrder = [];
        $ordered = collect();
        $nodes = BatchTree::flatten($filtered, descending: true);
        $parentIds = collect($nodes)->flatMap(fn (array $node) => $node['ancestors'])->unique();

        foreach ($nodes as $position => $node) {
            $treeOrder[$node['batch']->batch_id] = [
                'position' => $position,
                'depth' => $node['depth'],
                'parent_code' => $node['parent_code'],
                'parent_missing' => $node['parent_missing'],
                'ancestors' => $node['ancestors'],
                'has_children' => $parentIds->contains($node['batch']->batch_id),
            ];

            $ordered->push($node['batch']);
        }

        // Live per-batch counts. `mortality_sum` reuses the eager-loaded sum so
        // no batch issues an N+1 mortality query. `mortality_causes` is one grouped
        // query for the whole filtered set (RULE 6, no N+1) and feeds the
        // read-only View modal's Mortality Causes breakdown.
        $mortalityCauses = MortalityRecord::causeBreakdownByBatch($filtered->pluck('batch_id'));

        $summaries = $filtered->mapWithKeys(function (Batch $batch) use ($mortalityCauses): array {
            $mortality = (int) ($batch->mortality_sum ?? 0);
            $lifecycle = $batch->lifecycleStatus();

            return [$batch->batch_id => [
                'total_grafted' => (int) $batch->total_grafted,
                'regraft' => $batch->totalRegrafted(),
                // Most recent date the Regraft counter was increased (owner-
                // directed table layout: the Regraft quantity is shown with the
                // date its regraft was recorded); null when nothing was
                // regrafted out of the batch.
                'latest_regraft_date' => $batch->latest_regraft_date,
                'mortality' => $mortality,
                // The recorded causes behind the mortality figure (cause =>
                // quantity, ordered by quantity), shown in the View modal.
                'mortality_causes' => $mortalityCauses->get($batch->batch_id, collect())->all(),
                'available' => (int) $batch->available_count,
                // The row's Regraft action needs the same live cap the regraft
                // flow enforces, so the modal button is only offered when the
                // batch actually has a surviving quantity.
                'regraftable' => $batch->survivingQuantity($mortality),
                // Derived display status (owner-directed; stored `status` is
                // untouched) — see Batch::lifecycleStatus().
                'lifecycle_status' => $lifecycle,
                'lifecycle_label' => Batch::lifecycleLabels()[$lifecycle],
                'cycle_ready_date' => $batch->cycleReadyDate()?->toDateString(),
                'archived' => $batch->isArchived(),
            ]];
        });

        // Data for the record modals hosted on this page (owner-directed
        // shortcut, same pattern as the Add Batch modal): the Mortality form's
        // batch dropdown reads each active batch's original / Stock Out /
        // remaining figures through the centralized quantityBreakdown(), and
        // the Regraft form's source dropdown lists each active, regraftable
        // batch through the centralized survivingQuantity() — the same data
        // the operational pages pass, so the forms stay identical.
        $activeBatches = Batch::active()->with('scionVariety')->orderBy('batch_code')->get();

        $activeBatchBreakdowns = $activeBatches->mapWithKeys(
            fn (Batch $batch) => [$batch->batch_id => $batch->quantityBreakdown()],
        );

        $regraftableBatches = Batch::query()
            ->with('scionVariety')
            ->withSum('mortalityRecords as mortality_sum', 'quantity')
            ->orderBy('batch_code')
            ->get()
            ->map(fn (Batch $batch): array => [
                'batch' => $batch,
                'regraftable' => $batch->survivingQuantity((int) ($batch->mortality_sum ?? 0)),
            ])
            ->filter(fn (array $summary): bool => $summary['regraftable'] > 0 && ! $summary['batch']->isArchived())
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;

        $batches = new LengthAwarePaginator(
            $ordered->forPage($page, $perPage)->values(),
            $ordered->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
        $batches->withQueryString();

        return view('inventory.index', [
            'batches' => $batches,
            'summaries' => $summaries,
            'nodeMeta' => $treeOrder,
            'scionVarieties' => ScionVariety::orderBy('scion_variety_name')->get(),
            // Preview only for the hosted Add Batch form: the code the server
            // will assign on save (the store action recomputes it).
            'nextBatchCode' => Batch::nextBatchCode(),
            'activeBatches' => $activeBatches,
            'activeBatchBreakdowns' => $activeBatchBreakdowns,
            'regraftableBatches' => $regraftableBatches,
            'activeScionVarieties' => ScionVariety::active()->orderBy('scion_variety_name')->get(),
            'filters' => $filters,
            // Summary cards (owner-directed; filter-aware like the listing).
            // Every figure aggregates the same per-batch values the rows render:
            //  - Total Mortality: effective recorded mortality — strictly
            //    incrementing per batch (PRD §2.5/§11, ERD §3.10).
            //  - Total Regrafted: the cached `batches.regraft` counter
            //    (OD-12), reconciled against `regraft_records` by cacao:health.
            //  - Total Active Inventory: SUM(available_count) of ACTIVE batches
            //    only — the AnalyticsMetrics::totalActiveInventory() definition
            //    (archived excluded) applied to the filtered set.
            //  - Total Batches: count of the listed (filtered) batches.
            'totalMortality' => (int) $summaries->sum('mortality'),
            'totalRegrafted' => (int) $summaries->sum('regraft'),
            'totalActiveInventory' => (int) $summaries
                ->reject(fn (array $summary): bool => $summary['archived'])
                ->sum('available'),
            'totalBatches' => $summaries->count(),
        ]);
    }

    /**
     * "Record Monthly Inventory" (locked decision a): freeze one snapshot per
     * batch per calendar month. Every count is derived server-side from live
     * operational data; `status` is frozen by the Inventory model hook.
     *
     * Note: no Activity_Log row is written here. The centralized catalog
     * (`ActivityLog::TYPES`) has no `inventory_recorded` type and model edits
     * are out of scope for P3 — flagged for a follow-up (see handoff summary).
     */
    public function store(StoreInventorySnapshotRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $inventoryDate = empty($validated['inventory_date'])
            ? now()
            : Carbon::parse($validated['inventory_date']);

        $fingerprint = DuplicateSubmissionGuard::assertFresh('inventory_snapshot', $validated, 'inventory');

        try {
            DB::transaction(function () use ($validated, $inventoryDate): void {
                $batch = Batch::query()->lockForUpdate()->findOrFail($validated['batch_id']);

                $exists = Inventory::query()
                    ->where('batch_id', $batch->batch_id)
                    ->whereYear('inventory_date', $inventoryDate->year)
                    ->whereMonth('inventory_date', $inventoryDate->month)
                    ->exists();

                if ($exists) {
                    $this->failValidation([
                        'batch_id' => "An inventory snapshot for this batch already exists for {$inventoryDate->format('F Y')}.",
                    ], 'inventory');
                }

                Inventory::create([
                    'batch_id' => $batch->batch_id,
                    'inventory_date' => $inventoryDate->toDateString(),
                    'total_grafted' => (int) $batch->total_grafted,
                    'regraft' => $batch->totalRegrafted(),
                    'mortality' => $batch->effectiveMortalityQuantity(),
                    'last_update' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('inventory_snapshot', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Monthly inventory snapshot recorded successfully.');
    }
}
