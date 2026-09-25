<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\Variety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class BatchController extends Controller
{
    /**
     * FR-16 Batch Management listing. Readable and mutable by any
     * authenticated user (consistent with the other Management pages).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'variety_id' => ['nullable', 'integer', 'exists:varieties,variety_id'],
            'scion_id' => ['nullable', 'integer', 'exists:scions,scion_id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Batch::STATUSES)],
            'tab' => ['nullable', 'string', 'in:active,archived'],
        ]);

        $tab = ($filters['tab'] ?? 'active') === 'archived' ? 'archived' : 'active';

        $base = Batch::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where('batch_code', 'like', "%{$search}%");
            })
            ->when($filters['variety_id'] ?? null, function ($query, $varietyId): void {
                $query->where('variety_id', $varietyId);
            })
            ->when($filters['scion_id'] ?? null, function ($query, $scionId): void {
                $query->where('scion_id', $scionId);
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
            ->with(['variety', 'scion', 'potType', 'inventory'])
            ->withCount(['mortalityRecords', 'replantingRecords'])
            ->withExists(Batch::OPERATIONAL_RELATIONS)
            ->orderBy('batch_code')
            ->paginate(10)
            ->withQueryString();

        // New-record selectors offer active master data only, but an archived
        // master that is already referenced by a batch must stay selectable so
        // that existing batches remain editable (ERD §9.8).
        $varieties = Variety::query()
            ->where(fn ($query) => $query->active()->orWhereIn('variety_id', Batch::query()->select('variety_id')))
            ->orderBy('variety_name')
            ->get();

        $scions = Scion::query()
            ->where(fn ($query) => $query->active()->orWhereIn('scion_id', Batch::query()->select('scion_id')))
            ->orderBy('scion_name')
            ->get();

        $potTypes = PotType::query()
            ->where(fn ($query) => $query->active()->orWhereIn('pot_id', Batch::query()->select('pot_id')))
            ->orderBy('pot_name')
            ->get();

        return view('inventory.batches.index', [
            'batches' => $batches,
            'filters' => $filters,
            'status' => $tab,
            'archivedCount' => $archivedCount,
            'varieties' => $varieties,
            'scions' => $scions,
            'potTypes' => $potTypes,
            'totalBatches' => (clone $visible)->count(),
            'activeBatches' => (clone $visible)->where('status', Batch::STATUS_ACTIVE)->count(),
            'inactiveBatches' => (clone $visible)->where('status', Batch::STATUS_INACTIVE)->count(),
            'batchesWithMortality' => (clone $visible)->has('mortalityRecords')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), Batch::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.batches')
                ->withErrors($validator, 'batch')
                ->withInput();
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated): void {
            $batch = Batch::create([
                ...$validated,
                'user_id' => Auth::id(),
                'available_count' => $validated['viable_count'],
                'created_at' => now(),
            ]);

            Inventory::syncFromBatch($batch, true);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_BATCH_CREATED,
                "Created batch {$batch->batch_code}."
            );
        });

        return redirect()
            ->route('inventory.batches')
            ->with('success', 'Batch added successfully.');
    }

    public function update(Request $request, Batch $batch): RedirectResponse
    {
        $used = $batch->hasOperationalRecords();

        $rules = Batch::rules($batch->batch_id);

        if ($used) {
            unset($rules['total_grafted'], $rules['viable_count']);
        }

        // An existing (possibly archived) master reference stays valid, so only
        // a *switch* to a different archived master is rejected (ERD §9.8).
        foreach (['variety_id' => 'varieties', 'scion_id' => 'scions', 'pot_id' => 'pot_types'] as $field => $table) {
            if ((int) $request->input($field) === (int) $batch->{$field}) {
                $rules[$field] = ['required', 'integer', 'exists:'.$table.','.$field];
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.batches')
                ->withErrors($validator, 'batch')
                ->withInput(['edit_batch_id' => $batch->batch_id]);
        }

        if ($used && $this->countsChanged($batch, $request)) {
            return redirect()
                ->route('inventory.batches')
                ->with('error', 'This batch already has operational records, so total grafted and viable count can no longer be changed.')
                ->withInput(['edit_batch_id' => $batch->batch_id]);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($batch, $validated, $used): void {
            $batch->fill($validated);

            if (! $used) {
                $batch->available_count = $validated['viable_count'];
            }

            $batch->save();

            if (! $used) {
                Inventory::syncFromBatch($batch, true);
            }

            ActivityLog::record(
                null,
                ActivityLog::TYPE_BATCH_UPDATED,
                "Updated batch {$batch->batch_code}."
            );
        });

        return redirect()
            ->route('inventory.batches')
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
                ->route('inventory.batches')
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
            ->route('inventory.batches')
            ->with('success', 'Batch archived successfully.');
    }

    /**
     * Restore a previously archived batch to the active directory.
     */
    public function restore(Batch $batch): RedirectResponse
    {
        if (! $batch->isArchived()) {
            return redirect()
                ->route('inventory.batches')
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
            ->route('inventory.batches', ['tab' => 'archived'])
            ->with('success', 'Batch restored successfully.');
    }

    /**
     * Detect an attempt to change a locked count (used batches only).
     * The edit form disables these inputs, so an absent field is not a change.
     */
    private function countsChanged(Batch $batch, Request $request): bool
    {
        foreach (['total_grafted', 'viable_count'] as $field) {
            if ($request->has($field) && (int) $request->input($field) !== (int) $batch->{$field}) {
                return true;
            }
        }

        return false;
    }
}
