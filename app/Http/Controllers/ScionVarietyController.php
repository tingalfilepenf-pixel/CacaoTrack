<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScionVarietyRequest;
use App\Http\Requests\UpdateScionVarietyRequest;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\ScionVariety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * FR-07 Scion Variety master data.
 *
 * The single retained scion module (ERD §3.9): it replaces the old `Variety`
 * master and the generic `Scions` stock pool. Active (non-archived) varieties
 * only — owner-directed: the Archived tab was removed and archive/restore
 * replaced by a guarded permanent Delete.
 */
class ScionVarietyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $visible = ScionVariety::query()
            ->active()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('scion_variety_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $scionVarieties = (clone $visible)
            // The BATCHES column shows `batches_count`; the other two relations
            // are only existence checks for the row Delete button's dependency
            // check (ScionVariety::deletionBlockers()). `withExists()` supplies
            // the `*_exists` attributes it reads, so no per-row queries run.
            ->withCount('batches')
            ->withExists(['batches', 'stockOutDetails', 'stockIns'])
            ->orderBy('scion_variety_name')
            ->paginate(10)
            ->withQueryString();

        return view('operational.scion-varieties.index', [
            'scionVarieties' => $scionVarieties,
            'filters' => $filters,
            'totalScionVarieties' => (clone $visible)->count(),
            'scionVarietiesWithBatches' => (clone $visible)->has('batches')->count(),
            'totalBatches' => (int) Batch::query()
                ->whereIn('scion_variety_id', (clone $visible)->select('scion_variety_id'))
                ->count(),
        ]);
    }

    public function store(StoreScionVarietyRequest $request): RedirectResponse
    {
        ScionVariety::create($request->validated());

        return redirect()
            ->route('operational.scion-varieties')
            ->with('success', 'Scion variety added successfully.');
    }

    public function update(UpdateScionVarietyRequest $request, ScionVariety $scionVariety): RedirectResponse
    {
        $scionVariety->update($request->validated());

        return redirect()
            ->route('operational.scion-varieties')
            ->with('success', 'Scion variety updated successfully.');
    }

    /**
     * Permanently delete a scion variety. Guarded by
     * {@see ScionVariety::deletionBlockers()}: a variety already referenced by
     * a batch, a stock receipt or a stock-out line is refused so operational
     * history is never destroyed. Replaces the former archive/restore flow
     * (owner-directed).
     */
    public function destroy(ScionVariety $scionVariety): RedirectResponse
    {
        $blockers = $scionVariety->deletionBlockers();

        if ($blockers !== []) {
            return redirect()
                ->route('operational.scion-varieties')
                ->with('error', "Scion variety {$scionVariety->scion_variety_name} cannot be deleted because it has ".implode(', ', $blockers).'. Its history must be preserved.');
        }

        $name = $scionVariety->scion_variety_name;

        DB::transaction(function () use ($scionVariety): void {
            $scionVariety->delete();

            ActivityLog::record(
                null,
                ActivityLog::TYPE_SCION_VARIETY_DELETED,
                "Deleted scion variety: {$scionVariety->scion_variety_name}."
            );
        });

        return redirect()
            ->route('operational.scion-varieties')
            ->with('success', "Scion variety \"{$name}\" deleted permanently.");
    }
}
