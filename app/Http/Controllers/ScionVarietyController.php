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
 * master and the generic `Scions` stock pool. Records archive/restore rather
 * than delete, matching the Archived_At pattern (ERD §9.8).
 */
class ScionVarietyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', 'string', 'in:active,archived'],
        ]);

        $status = ($filters['tab'] ?? 'active') === 'archived' ? 'archived' : 'active';

        $base = ScionVariety::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('scion_variety_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $archivedCount = (clone $base)->archived()->count();

        $visible = (clone $base)->when(
            $status === 'archived',
            fn ($query) => $query->archived(),
            fn ($query) => $query->active(),
        );

        $scionVarieties = (clone $visible)
            ->withCount('batches')
            ->orderBy('scion_variety_name')
            ->paginate(10)
            ->withQueryString();

        return view('operational.scion-varieties.index', [
            'scionVarieties' => $scionVarieties,
            'filters' => $filters,
            'status' => $status,
            'archivedCount' => $archivedCount,
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
     * Archive a scion variety instead of deleting it (approved ERD revision
     * §9.8: history is never destroyed).
     */
    public function archive(ScionVariety $scionVariety): RedirectResponse
    {
        if ($scionVariety->isArchived()) {
            return redirect()
                ->route('operational.scion-varieties')
                ->with('error', 'This scion variety is already archived.');
        }

        DB::transaction(function () use ($scionVariety): void {
            $scionVariety->update(['archived_at' => now()]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_SCION_VARIETY_ARCHIVED,
                "Archived scion variety: {$scionVariety->scion_variety_name}."
            );
        });

        return redirect()
            ->route('operational.scion-varieties')
            ->with('success', 'Scion variety archived successfully.');
    }

    /**
     * Restore a previously archived scion variety to the active directory.
     */
    public function restore(ScionVariety $scionVariety): RedirectResponse
    {
        if (! $scionVariety->isArchived()) {
            return redirect()
                ->route('operational.scion-varieties')
                ->with('error', 'This scion variety is not archived.');
        }

        DB::transaction(function () use ($scionVariety): void {
            $scionVariety->update(['archived_at' => null]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_SCION_VARIETY_RESTORED,
                "Restored scion variety: {$scionVariety->scion_variety_name}."
            );
        });

        return redirect()
            ->route('operational.scion-varieties', ['tab' => 'archived'])
            ->with('success', 'Scion variety restored successfully.');
    }
}
