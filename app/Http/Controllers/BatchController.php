<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\ScionVariety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Inventory snapshots.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'scion_variety_id' => ['nullable', 'integer', 'exists:scion_varieties,scion_variety_id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Batch::STATUSES)],
            'tab' => ['nullable', 'string', 'in:active,archived'],
        ]);

        $tab = ($filters['tab'] ?? 'active') === 'archived' ? 'archived' : 'active';

        $base = Batch::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where('batch_code', 'like', "%{$search}%");
            })
            ->when($filters['scion_variety_id'] ?? null, function ($query, $scionVarietyId): void {
                $query->where('scion_variety_id', $scionVarietyId);
            })
            ->when($filters['status'] ?? null, function ($query, $status): void {
                $query->where('status', $status);
            });

        $archivedCount = (clone $base)->archived()->count();

        $visible = (clone $base)->when(
            $tab === 'archived',
            fn ($query) => $query->archived(),
            fn ($query) => $query->active(),
        );

        $batches = (clone $visible)
            ->with(['scionVariety', 'latestInventory'])
            ->withCount(['mortalityRecords', 'replantingRecords'])
            ->withExists(Batch::OPERATIONAL_RELATIONS)
            ->orderBy('batch_code')
            ->paginate(10)
            ->withQueryString();

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
            'status' => $tab,
            'archivedCount' => $archivedCount,
            'scionVarieties' => $scionVarieties,
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
            $batch = Batch::create([
                ...$validated,
                'user_id' => Auth::id(),
                'available_count' => $validated['viable_count'],
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
                $batch->available_count = $validated['viable_count'];
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
     * Archive a batch instead of deleting it (approved ERD revision §9.8:
     * archive/restore replaces hard deletion so inventory, mortality,
     * replanting, stock and activity history are never destroyed).
     */
    public function archive(Batch $batch): RedirectResponse
    {
        if ($batch->isArchived()) {
            return redirect()
                ->route('operational.batches')
                ->with('error', 'This batch is already archived.');
        }

        DB::transaction(function () use ($batch): void {
            $batch->update(['archived_at' => now()]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_BATCH_ARCHIVED,
                "Archived batch {$batch->batch_code}."
            );
        });

        return redirect()
            ->route('operational.batches')
            ->with('success', 'Batch archived successfully.');
    }

    /**
     * Restore a previously archived batch to the active directory.
     */
    public function restore(Batch $batch): RedirectResponse
    {
        if (! $batch->isArchived()) {
            return redirect()
                ->route('operational.batches')
                ->with('error', 'This batch is not archived.');
        }

        DB::transaction(function () use ($batch): void {
            $batch->update(['archived_at' => null]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_BATCH_RESTORED,
                "Restored batch {$batch->batch_code}."
            );
        });

        return redirect()
            ->route('operational.batches', ['tab' => 'archived'])
            ->with('success', 'Batch restored successfully.');
    }
}
