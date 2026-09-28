<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockInRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\ActivityLog;
use App\Models\ScionVariety;
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
     * FR-09 Stock In listing. Stock In records a receiving event and has no
     * `batch_id` (ERD §3.7). Since the client-confirmed merge of Scion_Receiving
     * into Stock_In, a single list shows both seedling-only and scion-inclusive
     * rows: rows with a `scion_variety_id` are the scion ones (see
     * `StockIn::isScionEntry()`), surfaced via the scion-variety column.
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
                        ->orWhereHas('scionVariety', fn ($varietyQuery) => $varietyQuery
                            ->where('scion_variety_name', 'like', "%{$search}%"))
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
            ->with(['supplier', 'scionVariety', 'user'])
            ->orderByDesc('date_received')
            ->orderByDesc('stock_in_id')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.stock-in.index', [
            'stockIns' => $stockIns,
            'suppliers' => Supplier::active()->orderBy('supplier_name')->get(),
            'scionVarieties' => ScionVariety::query()->active()->orderBy('scion_variety_name')->get(),
            'filters' => $filters,
            'totalReceived' => (int) (clone $base)->sum('quantity'),
            'totalReceivedSeedlings' => (int) (clone $base)->whereNull('scion_variety_id')->sum('quantity'),
            'totalTransactions' => (clone $base)->count(),
            'totalSuppliers' => (clone $base)->distinct()->count('supplier_id'),
        ]);
    }

    public function store(StoreStockInRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // A row includes scion when `scion_variety_id` is present (there is no
        // "type" column). The received `quantity` is recorded immediately for
        // both kinds of receiving — there is no five-day viability recount.
        $isScion = ! empty($validated['scion_variety_id']);

        $fingerprint = DuplicateSubmissionGuard::assertFresh('stock_in', $validated, 'stockin');

        try {
            DB::transaction(function () use ($validated, $isScion): void {
                StockIn::create([
                    'supplier_id' => $validated['supplier_id'],
                    'scion_variety_id' => $validated['scion_variety_id'] ?? null,
                    'user_id' => Auth::id(),
                    'quantity' => $validated['quantity'],
                    'unit_price' => $validated['unit_price'] ?? null,
                    'origin' => $validated['origin'],
                    'notes' => $validated['notes'] ?? null,
                    'date_received' => $validated['date_received'],
                ]);

                $supplier = Supplier::find($validated['supplier_id']);

                if ($isScion) {
                    $scionVariety = ScionVariety::find($validated['scion_variety_id']);
                    $description = "Received {$validated['quantity']} scion of "
                        .($scionVariety?->scion_variety_name ?? 'scion variety')
                        .' from '.($supplier?->supplier_name ?? 'supplier')
                        ." (origin: {$validated['origin']}).";
                } else {
                    $description = "Received {$validated['quantity']} seedlings from "
                        .($supplier?->supplier_name ?? 'supplier')
                        ." (origin: {$validated['origin']}).";
                }

                ActivityLog::record(
                    null,
                    ActivityLog::TYPE_STOCK_IN_RECORDED,
                    $description,
                    null,
                    $validated['date_received'],
                );
            });
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_in', $fingerprint);

            throw $exception;
        }

        $message = $isScion
            ? 'Scion receiving recorded successfully.'
            : 'Stock-in transaction recorded successfully.';

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', $message);
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
