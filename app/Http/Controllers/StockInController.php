<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\StockIn;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class StockInController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,supplier_id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $base = StockIn::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->whereHas('supplier', fn ($supplierQuery) => $supplierQuery
                        ->where('supplier_name', 'like', "%{$search}%"))
                        ->orWhereHas('batch', fn ($batchQuery) => $batchQuery
                            ->where('batch_code', 'like', "%{$search}%"));
                });
            })
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplierId) => $query
                ->where('supplier_id', $supplierId))
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query
                ->where('batch_id', $batchId))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query
                ->whereDate('date_received', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query
                ->whereDate('date_received', '<=', $dateTo));

        $stockIns = (clone $base)
            ->with(['supplier', 'batch.variety', 'user'])
            ->orderByDesc('date_received')
            ->orderByDesc('stock_in_id')
            ->paginate(10)
            ->withQueryString();

        $totalReceived = (int) (clone $base)->sum('quantity');
        $totalTransactions = (clone $base)->count();
        $totalSuppliers = (clone $base)->distinct()->count('supplier_id');

        return view('inventory.stock-in.index', [
            'stockIns' => $stockIns,
            'suppliers' => Supplier::active()->orderBy('supplier_name')->get(),
            'batches' => Batch::active()->with('variety')->orderBy('batch_code')->get(),
            'filters' => $filters,
            'totalReceived' => $totalReceived,
            'totalTransactions' => $totalTransactions,
            'totalSuppliers' => $totalSuppliers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => ['required', 'integer', Supplier::activeExistsRule('supplier_id')],
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.stock-in')
                ->withErrors($validator, 'stockin')
                ->withInput();
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated): void {
            $batch = Batch::findOrFail($validated['batch_id']);

            $inventory = Inventory::query()
                ->where('batch_id', $batch->batch_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = Inventory::syncFromBatch($batch);
            }

            StockIn::create([
                'supplier_id' => $validated['supplier_id'],
                'batch_id' => $batch->batch_id,
                'user_id' => Auth::id(),
                'quantity' => $validated['quantity'],
                'date_received' => $validated['date_received'],
            ]);

            $inventory->applyDelta((int) $validated['quantity']);

            $supplier = Supplier::find($validated['supplier_id']);

            ActivityLog::record(
                $batch->batch_id,
                ActivityLog::TYPE_STOCK_IN_RECORDED,
                "Received {$validated['quantity']} trees from ".($supplier?->supplier_name ?? 'supplier')
                    ." for {$batch->batch_code}.",
                null,
                $validated['date_received'],
            );
        });

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', 'Stock-in transaction recorded successfully.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.stock-in')
                ->withErrors($validator, 'supplier')
                ->withInput();
        }

        $supplier = Supplier::create($validator->validated());

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', 'Supplier added successfully.')
            ->with('new_supplier_id', $supplier->supplier_id);
    }
}
