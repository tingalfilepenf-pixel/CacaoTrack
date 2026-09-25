<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\PotType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PotTypeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', 'string', 'in:active,archived'],
        ]);

        $status = ($filters['tab'] ?? 'active') === 'archived' ? 'archived' : 'active';

        $base = PotType::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('pot_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $archivedCount = (clone $base)->archived()->count();

        $visible = (clone $base)->when(
            $status === 'archived',
            fn ($query) => $query->archived(),
            fn ($query) => $query->active(),
        );

        $potTypes = (clone $visible)
            ->withCount('batches')
            ->orderBy('pot_name')
            ->paginate(10)
            ->withQueryString();

        $totalPotTypes = (clone $visible)->count();
        $potTypesWithBatches = (clone $visible)->has('batches')->count();
        $totalBatches = (int) Batch::query()
            ->whereIn('pot_id', (clone $visible)->select('pot_id'))
            ->count();

        return view('inventory.pot-types.index', [
            'potTypes' => $potTypes,
            'filters' => $filters,
            'status' => $status,
            'archivedCount' => $archivedCount,
            'totalPotTypes' => $totalPotTypes,
            'potTypesWithBatches' => $potTypesWithBatches,
            'totalBatches' => $totalBatches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), PotType::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.pot-types')
                ->withErrors($validator, 'pot')
                ->withInput();
        }

        PotType::create($validator->validated());

        return redirect()
            ->route('inventory.pot-types')
            ->with('success', 'Pot type added successfully.');
    }

    public function update(Request $request, PotType $potType): RedirectResponse
    {
        $validator = Validator::make($request->all(), PotType::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.pot-types')
                ->withErrors($validator, 'pot')
                ->withInput(['edit_pot_id' => $potType->pot_id]);
        }

        $potType->update($validator->validated());

        return redirect()
            ->route('inventory.pot-types')
            ->with('success', 'Pot type updated successfully.');
    }

    /**
     * Archive a pot type instead of deleting it (approved ERD revision §9.8:
     * archive/restore replaces hard deletion so history is never destroyed).
     */
    public function archive(PotType $potType): RedirectResponse
    {
        if ($potType->isArchived()) {
            return redirect()
                ->route('inventory.pot-types')
                ->with('error', 'This pot type is already archived.');
        }

        DB::transaction(function () use ($potType): void {
            $potType->update(['archived_at' => now()]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_POT_TYPE_ARCHIVED,
                "Archived pot type: {$potType->pot_name}."
            );
        });

        return redirect()
            ->route('inventory.pot-types')
            ->with('success', 'Pot type archived successfully.');
    }

    /**
     * Restore a previously archived pot type to the active directory.
     */
    public function restore(PotType $potType): RedirectResponse
    {
        if (! $potType->isArchived()) {
            return redirect()
                ->route('inventory.pot-types')
                ->with('error', 'This pot type is not archived.');
        }

        DB::transaction(function () use ($potType): void {
            $potType->update(['archived_at' => null]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_POT_TYPE_RESTORED,
                "Restored pot type: {$potType->pot_name}."
            );
        });

        return redirect()
            ->route('inventory.pot-types', ['tab' => 'archived'])
            ->with('success', 'Pot type restored successfully.');
    }
}
