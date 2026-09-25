<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Variety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class VarietyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', 'string', 'in:active,archived'],
        ]);

        $status = ($filters['tab'] ?? 'active') === 'archived' ? 'archived' : 'active';

        $base = Variety::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('variety_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $archivedCount = (clone $base)->archived()->count();

        $visible = (clone $base)->when(
            $status === 'archived',
            fn ($query) => $query->archived(),
            fn ($query) => $query->active(),
        );

        $varieties = (clone $visible)
            ->withCount('batches')
            ->orderBy('variety_name')
            ->paginate(10)
            ->withQueryString();

        $totalVarieties = (clone $visible)->count();
        $varietiesWithBatches = (clone $visible)->has('batches')->count();
        $totalBatches = (int) Batch::query()
            ->whereIn('variety_id', (clone $visible)->select('variety_id'))
            ->count();

        return view('inventory.varieties.index', [
            'varieties' => $varieties,
            'filters' => $filters,
            'status' => $status,
            'archivedCount' => $archivedCount,
            'totalVarieties' => $totalVarieties,
            'varietiesWithBatches' => $varietiesWithBatches,
            'totalBatches' => $totalBatches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), Variety::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.varieties')
                ->withErrors($validator, 'variety')
                ->withInput();
        }

        Variety::create($validator->validated());

        return redirect()
            ->route('inventory.varieties')
            ->with('success', 'Variety added successfully.');
    }

    public function update(Request $request, Variety $variety): RedirectResponse
    {
        $validator = Validator::make($request->all(), Variety::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.varieties')
                ->withErrors($validator, 'variety')
                ->withInput(['edit_variety_id' => $variety->variety_id]);
        }

        $variety->update($validator->validated());

        return redirect()
            ->route('inventory.varieties')
            ->with('success', 'Variety updated successfully.');
    }

    /**
     * Archive a variety instead of deleting it (approved ERD revision §9.8:
     * archive/restore replaces hard deletion so history is never destroyed).
     */
    public function archive(Variety $variety): RedirectResponse
    {
        if ($variety->isArchived()) {
            return redirect()
                ->route('inventory.varieties')
                ->with('error', 'This variety is already archived.');
        }

        DB::transaction(function () use ($variety): void {
            $variety->update(['archived_at' => now()]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_VARIETY_ARCHIVED,
                "Archived variety: {$variety->variety_name}."
            );
        });

        return redirect()
            ->route('inventory.varieties')
            ->with('success', 'Variety archived successfully.');
    }

    /**
     * Restore a previously archived variety to the active directory.
     */
    public function restore(Variety $variety): RedirectResponse
    {
        if (! $variety->isArchived()) {
            return redirect()
                ->route('inventory.varieties')
                ->with('error', 'This variety is not archived.');
        }

        DB::transaction(function () use ($variety): void {
            $variety->update(['archived_at' => null]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_VARIETY_RESTORED,
                "Restored variety: {$variety->variety_name}."
            );
        });

        return redirect()
            ->route('inventory.varieties', ['tab' => 'archived'])
            ->with('success', 'Variety restored successfully.');
    }
}
