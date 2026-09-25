<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventorySnapshotRequest;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\ScionVariety;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * FR-08 Inventory listing: the company's monthly snapshots of established
     * grafted cacao batches (many rows per batch — ERD §3.12).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'scion_variety_id' => ['nullable', 'integer', 'exists:scion_varieties,scion_variety_id'],
            'status' => ['nullable', 'in:active,depleted'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $base = Inventory::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas(
                'batch',
                fn ($batchQuery) => $batchQuery->where('batch_code', 'like', "%{$search}%"),
            ))
            ->when($filters['scion_variety_id'] ?? null, fn ($query, $scionVarietyId) => $query->whereHas(
                'batch',
                fn ($batchQuery) => $batchQuery->where('scion_variety_id', $scionVarietyId),
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('inventory_date', $year))
            ->when($filters['month'] ?? null, fn ($query, $month) => $query->whereMonth('inventory_date', $month));

        $inventories = (clone $base)
            ->with(['batch', 'batch.scionVariety'])
            ->orderByDesc('inventory_date')
            ->orderByDesc('inventory_id')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.index', [
            'inventories' => $inventories,
            'scionVarieties' => ScionVariety::orderBy('scion_variety_name')->get(),
            'batches' => Batch::active()->with('scionVariety')->orderBy('batch_code')->get(),
            'filters' => $filters,
            'totalGrafted' => (int) (clone $base)->sum('total_grafted'),
            'totalViable' => (int) (clone $base)->sum('viable'),
            'totalSnapshots' => (clone $base)->count(),
            'activeSnapshots' => (clone $base)->where('status', Inventory::STATUS_ACTIVE)->count(),
            'depletedSnapshots' => (clone $base)->where('status', Inventory::STATUS_DEPLETED)->count(),
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
                    'viable' => (int) $batch->viable_count,
                    'regraft' => $batch->totalReplanted(),
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
