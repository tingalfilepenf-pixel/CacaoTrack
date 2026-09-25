<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\Variety;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'variety_id' => ['nullable', 'integer', 'exists:varieties,variety_id'],
            'status' => ['nullable', 'in:active,depleted'],
        ]);

        $base = Inventory::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas(
                'batch',
                fn ($batchQuery) => $batchQuery->where('batch_code', 'like', "%{$search}%"),
            ))
            ->when($filters['variety_id'] ?? null, fn ($query, $varietyId) => $query->whereHas(
                'batch',
                fn ($batchQuery) => $batchQuery->where('variety_id', $varietyId),
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        $inventories = (clone $base)
            ->with(['batch.variety', 'batch.scion', 'batch.potType'])
            ->orderBy(Batch::query()
                ->select('batch_code')
                ->whereColumn('batches.batch_id', 'inventories.batch_id'))
            ->paginate(10)
            ->withQueryString();

        $totalAvailable = (int) (clone $base)->sum('current_quantity');
        $activeBatches = (clone $base)->where('status', Inventory::STATUS_ACTIVE)->count();
        $depletedBatches = (clone $base)->where('status', Inventory::STATUS_DEPLETED)->count();

        return view('inventory.index', [
            'inventories' => $inventories,
            'varieties' => Variety::orderBy('variety_name')->get(),
            'filters' => $filters,
            'totalAvailable' => $totalAvailable,
            'activeBatches' => $activeBatches,
            'depletedBatches' => $depletedBatches,
        ]);
    }
}
