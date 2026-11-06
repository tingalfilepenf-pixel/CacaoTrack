<?php

namespace App\Http\Controllers;

use App\Actions\RecordStockIn;
use App\Http\Requests\StoreStockInRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Support\DuplicateSubmissionGuard;
use App\Support\OperationalSubmissionVisibility;
use App\Support\SubmissionWorkflowSwitch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockInController extends Controller
{
    public function __construct(private readonly RecordStockIn $stockIns) {}

    /**
     * FR-09 Stock In listing. Stock In records a receiving event and has no
     * `batch_id` (ERD §3.7). Since the client-confirmed merge of Scion_Receiving
     * into Stock_In, a single list shows both seedling-only and scion-inclusive
     * rows: rows with a `scion_variety_id` are the scion ones (see
     * `StockIn::isScionEntry()`), surfaced via the scion-variety column.
     */
    public function index(Request $request): View
    {
        if (SubmissionWorkflowSwitch::stockInEnabled()
            && ! OperationalSubmissionVisibility::canViewAll($request->user())) {
            abort(403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,supplier_id'],
            'type' => ['nullable', 'in:'.StockIn::TYPE_SEEDLING.','.StockIn::TYPE_SCION],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        // The entry type is derived from `scion_variety_id` (ERD §3.7): a scion
        // row has it set, a seedling-only row leaves it null.
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
            ->when($filters['type'] ?? null, function ($query, string $type): void {
                $type === StockIn::TYPE_SCION
                    ? $query->whereNotNull('scion_variety_id')
                    : $query->whereNull('scion_variety_id');
            })
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
            'totalReceivedScion' => (int) (clone $base)->whereNotNull('scion_variety_id')->sum('quantity'),
            'totalTransactions' => (clone $base)->count(),
            'totalSuppliers' => (clone $base)->distinct()->count('supplier_id'),
        ]);
    }

    public function store(StoreStockInRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $seedling = $validated['seedling'] ?? null;
        $scion = $validated['scion'] ?? null;

        // Fingerprint only the filled entries, without blank values or the
        // prefilled date, so an untouched tab can never change the fingerprint.
        $fingerprint = DuplicateSubmissionGuard::assertFresh(
            'stock_in',
            $request->duplicateFingerprintPayload(),
            'stockin',
        );

        $entries = array_filter(
            ['seedling' => $seedling, 'scion' => $scion],
            fn ($entry): bool => is_array($entry),
        );

        try {
            $saved = $this->stockIns->execute($entries, (int) Auth::id());
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_in', $fingerprint);

            throw $exception;
        }

        $parts = [];

        if (isset($saved['seedling'])) {
            $parts[] = 'Seedlings '.(int) $saved['seedling']->quantity;
        }

        if (isset($saved['scion'])) {
            $parts[] = 'Scion '.(int) $saved['scion']->quantity;
        }

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', 'Saved: '.implode(' and ', $parts).'.');
    }

    public function storeSupplier(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create(
            $request->safe()->only(['supplier_name', 'contact_number', 'address'])
        );

        if ($request->input('return_to') === 'stock_in_submission') {
            $resubmissionId = $request->integer('resubmission_of');

            $destination = $resubmissionId > 0
                ? route('operational.submissions.resubmit.create', $resubmissionId)
                : route('operational.submissions.stock-in.create');

            return redirect($destination)
                ->with('success', 'Supplier added successfully.')
                ->with('new_supplier_id', $supplier->supplier_id);
        }

        return redirect()
            ->route('inventory.stock-in')
            ->with('success', 'Supplier added successfully.')
            ->with('new_supplier_id', $supplier->supplier_id);
    }
}
