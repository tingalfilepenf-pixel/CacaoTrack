<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\StockIn;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', 'string', 'in:active,archived'],
        ]);

        $status = ($filters['tab'] ?? 'active') === 'archived' ? 'archived' : 'active';

        $base = Supplier::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('supplier_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            });

        $archivedCount = (clone $base)->archived()->count();

        $visible = (clone $base)->when(
            $status === 'archived',
            fn ($query) => $query->archived(),
            fn ($query) => $query->active(),
        );

        $suppliers = (clone $visible)
            ->withCount('stockIns')
            ->orderBy('supplier_name')
            ->paginate(10)
            ->withQueryString();

        $totalSuppliers = (clone $visible)->count();
        $suppliersWithActivity = (clone $visible)->has('stockIns')->count();
        $totalStockIns = (int) StockIn::query()
            ->whereIn('supplier_id', (clone $visible)->select('supplier_id'))
            ->count();

        return view('inventory.suppliers.index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'status' => $status,
            'archivedCount' => $archivedCount,
            'totalSuppliers' => $totalSuppliers,
            'suppliersWithActivity' => $suppliersWithActivity,
            'totalStockIns' => $totalStockIns,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), Supplier::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.suppliers')
                ->withErrors($validator, 'supplier')
                ->withInput();
        }

        Supplier::create($validator->validated());

        return redirect()
            ->route('inventory.suppliers')
            ->with('success', 'Supplier added successfully.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validator = Validator::make($request->all(), Supplier::rules());

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.suppliers')
                ->withErrors($validator, 'supplier')
                ->withInput(['edit_supplier_id' => $supplier->supplier_id]);
        }

        $supplier->update($validator->validated());

        return redirect()
            ->route('inventory.suppliers')
            ->with('success', 'Supplier updated successfully.');
    }

    /**
     * Archive a supplier instead of deleting it (approved ERD revision §9.8:
     * archive/restore replaces hard deletion so history is never destroyed).
     */
    public function archive(Supplier $supplier): RedirectResponse
    {
        if ($supplier->isArchived()) {
            return redirect()
                ->route('inventory.suppliers')
                ->with('error', 'This supplier is already archived.');
        }

        DB::transaction(function () use ($supplier): void {
            $supplier->update(['archived_at' => now()]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_SUPPLIER_ARCHIVED,
                "Archived supplier: {$supplier->supplier_name}."
            );
        });

        return redirect()
            ->route('inventory.suppliers')
            ->with('success', 'Supplier archived successfully.');
    }

    /**
     * Restore a previously archived supplier to the active directory.
     */
    public function restore(Supplier $supplier): RedirectResponse
    {
        if (! $supplier->isArchived()) {
            return redirect()
                ->route('inventory.suppliers')
                ->with('error', 'This supplier is not archived.');
        }

        DB::transaction(function () use ($supplier): void {
            $supplier->update(['archived_at' => null]);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_SUPPLIER_RESTORED,
                "Restored supplier: {$supplier->supplier_name}."
            );
        });

        return redirect()
            ->route('inventory.suppliers', ['tab' => 'archived'])
            ->with('success', 'Supplier restored successfully.');
    }
}
