<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockInRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\ActivityLog;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockInController extends Controller
{
    /**
     * FR-09 Stock In listing. Stock In records the usable seedlings after the
     * five-day count; it has no `batch_id` (ERD §3.7).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,supplier_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $base = StockIn::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->whereHas('supplier', fn ($supplierQuery) => $supplierQuery
                        ->where('supplier_name', 'like', "%{$search}%"))
                        ->orWhere('origin', 'like', "%{$search}%");
                });
            })
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplierId) => $query
                ->where('supplier_id', $supplierId))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query
                ->whereDate('date_received', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query
                ->whereDate('date_received', '<=', $dateTo));

        $stockIns = (clone $base)
            ->with(['supplier', 'user'])
            ->orderByDesc('date_received')
            ->orderByDesc('stock_in_id')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.stock-in.index', [
            'stockIns' => $stockIns,
            'suppliers' => Supplier::active()->orderBy('supplier_name')->get(),
            'filters' => $filters,
            'totalReceived' => (int) (clone $base)->sum('quantity'),
            'totalViable' => (int) (clone $base)->sum('viable'),
            'totalTransactions' => (clone $base)->count(),
            'totalSuppliers' => (clone $base)->distinct()->count('supplier_id'),
        ]);
    }

    public function store(StoreStockInRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $fingerprint = DuplicateSubmissionGuard::assertFresh('stock_in', $validated, 'stockin');

        try {
            DB::transaction(function () use ($validated): void {
                StockIn::create([
                    'supplier_id' => $validated['supplier_id'],
                    'user_id' => Auth::id(),
                    'quantity' => $validated['quantity'],
                    'viable' => $validated['viable'],
                    'origin' => $validated['origin'],
                    'date_received' => $validated['date_received'],
                ]);

                $supplier = Supplier::find($validated['supplier_id']);

                ActivityLog::record(
                    null,
                    ActivityLog::TYPE_STOCK_IN_RECORDED,
                    "Received {$validated['quantity']} seedlings from "
                        .($supplier?->supplier_name ?? 'supplier')
                        ." (origin: {$validated['origin']}); viable {$validated['viable']}.",
                    null,
                    $validated['date_received'],
                );
            });
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_in', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', 'Stock-in transaction recorded successfully.');
    }

    public function storeSupplier(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', 'Supplier added successfully.')
            ->with('new_supplier_id', $supplier->supplier_id);
    }
}
