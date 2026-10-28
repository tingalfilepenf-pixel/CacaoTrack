<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\ScionVariety;
use App\Support\BatchTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BatchController extends Controller
{
    /**
     * FR-06 Batch listing (Operational → Batches, `/operational/batches`).
     *
     * A batch is the central operational record: one nullable
     * `scion_variety_id`, no variety/pot/monitoring columns, and many monthly
     * Inventory snapshots. Active (non-archived) batches only — owner-directed:
     * the Archived tab was removed and archive/restore replaced by a guarded
     * permanent Delete.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'scion_variety_id' => ['nullable', 'integer', 'exists:scion_varieties,scion_variety_id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Batch::STATUSES)],
        ]);

        $visible = Batch::query()
            ->active()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where('batch_code', 'like', "%{$search}%");
            })
            ->when($filters['scion_variety_id'] ?? null, function ($query, $scionVarietyId): void {
                $query->where('scion_variety_id', $scionVarietyId);
            })
            ->when($filters['status'] ?? null, function ($query, $status): void {
                $query->where('status', $status);
            });

        $batches = (clone $visible)
            ->with(['scionVariety', 'latestInventory', 'parent'])
            ->withCount(['mortalityRecords', 'regraftRecords', 'stockOutDetails'])
            // `inventories`/`activityLogs` are eager-loaded for the row Delete
            // button's dependency check (Batch::deletionBlockers()).
            ->withExists([...Batch::OPERATIONAL_RELATIONS, 'inventories', 'activityLogs'])
            ->get();

        // One family per page so a parent and its regraft sub-batches are never
        // split across pagination (Phase 7C nested display). Ordering/depth come
        // from the single tree helper (RULE 6).
        $families = collect(BatchTree::groups($batches));

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;

        $batches = new LengthAwarePaginator(
            $families->forPage($page, $perPage)->values(),
            $families->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
        $batches->withQueryString();

        // New-record selectors offer active scion varieties only, but an
        // archived variety already referenced by a batch stays selectable so
        // existing batches remain editable (ERD §9.8).
        $scionVarieties = ScionVariety::query()
            ->where(fn ($query) => $query->active()
                ->orWhereIn('scion_variety_id', Batch::query()->select('scion_variety_id')))
            ->orderBy('scion_variety_name')
            ->get();

        return view('operational.batches.index', [
            'batches' => $batches,
            'filters' => $filters,
            'scionVarieties' => $scionVarieties,
            // Preview only: the Add Batch form shows the code the server will
            // assign on save (the store action recomputes it).
            'nextBatchCode' => Batch::nextBatchCode(),
            'totalBatches' => (clone $visible)->count(),
            'activeBatches' => (clone $visible)->where('status', Batch::STATUS_ACTIVE)->count(),
            'inactiveBatches' => (clone $visible)->where('status', Batch::STATUS_INACTIVE)->count(),
            'batchesWithMortality' => (clone $visible)->has('mortalityRecords')->count(),
        ]);
    }

    public function store(StoreBatchRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            // FR-06: Batch Code and Grafted Date are system-assigned when a
            // batch is established — the next free root code and today's date
            // (see Batch::storeRules()). The operator supplies only the batch's
            // own choices (Scion Variety / Total Grafted / Status).
            $batch = Batch::create([
                'batch_code' => Batch::nextBatchCode(),
                'grafted_date' => now()->toDateString(),
                'scion_variety_id' => $validated['scion_variety_id'] ?? null,
                'total_grafted' => $validated['total_grafted'],
                'status' => $validated['status'],
                'user_id' => Auth::id(),
                // Provisional default 1 (pending PRD `OD-3`): a new batch
                // starts with its full grafted population available.
                'available_count' => $validated['total_grafted'],
                'created_at' => now(),
            ]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_BATCH_CREATED,
                "Created batch {$batch->batch_code}."
            );
        });

        return redirect()
            ->route('operational.batches')
            ->with('success', 'Batch added successfully.');
    }

    public function update(UpdateBatchRequest $request, Batch $batch): RedirectResponse
    {
        $used = $batch->hasOperationalRecords();

        $validated = $request->validated();

        DB::transaction(function () use ($batch, $validated, $used): void {
            $batch->fill($validated);

            if (! $used) {
                // Provisional default 1 (pending PRD `OD-3`): while the batch has
                // no operational records, its available balance tracks total grafted.
                $batch->available_count = $validated['total_grafted'];
            }

            $batch->save();

            ActivityLog::record(
                null,
                ActivityLog::TYPE_BATCH_UPDATED,
                "Updated batch {$batch->batch_code}."
            );
        });

        return redirect()
            ->route('operational.batches')
            ->with('success', 'Batch updated successfully.');
    }

    /**
     * Permanently delete a batch. Guarded by {@see Batch::deletionBlockers()}:
     * a batch with any dependent operational record (mortality, regraft /
     * sub-batches, stock-out lines, inventory snapshots or activity history) is
     * refused so its history is never destroyed. Replaces the former
     * archive/restore flow (owner-directed).
     */
    public function destroy(Batch $batch): RedirectResponse
    {
        $blockers = $batch->deletionBlockers();

        if ($blockers !== []) {
            return redirect()
                ->route('operational.batches')
                ->with('error', "Batch {$batch->batch_code} cannot be deleted because it has ".implode(', ', $blockers).'. Its history must be preserved.');
        }

        $batchCode = $batch->batch_code;

        DB::transaction(function () use ($batch): void {
            $batch->delete();

            ActivityLog::record(
                null,
                ActivityLog::TYPE_BATCH_DELETED,
                "Deleted batch {$batch->batch_code}."
            );
        });

        return redirect()
            ->route('operational.batches')
            ->with('success', "Batch {$batchCode} deleted permanently.");
    }
}
