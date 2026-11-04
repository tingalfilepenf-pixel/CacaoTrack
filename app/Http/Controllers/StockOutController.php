<?php

namespace App\Http\Controllers;

use App\Actions\RecordStockOut;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StoreStockOutRequest;
use App\Models\Batch;
use App\Models\Client;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockOutController extends Controller
{
    public function __construct(private readonly RecordStockOut $stockOuts) {}

    /**
     * FR-10 Stock Out listing. A Stock Out is a header plus one detail line
     * per batch/scion variety (ERD §3.14/§3.15).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:clients,client_id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,batch_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $base = StockOut::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->whereHas('client', fn ($clientQuery) => $clientQuery
                        ->where('client_name', 'like', "%{$search}%"))
                        ->orWhereHas('details.batch', fn ($batchQuery) => $batchQuery
                            ->where('batch_code', 'like', "%{$search}%"));
                });
            })
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query
                ->where('client_id', $clientId))
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query
                ->whereHas('details', fn ($detailQuery) => $detailQuery->where('batch_id', $batchId)))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query
                ->whereDate('release_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query
                ->whereDate('release_date', '<=', $dateTo));

        $stockOuts = (clone $base)
            ->with(['client', 'user', 'details.batch.scionVariety', 'details.scionVariety'])
            ->orderByDesc('release_date')
            ->orderByDesc('stock_out_id')
            ->paginate(10)
            ->withQueryString();

        $totalReleased = (int) StockOutDetail::query()
            ->whereIn('stock_out_id', (clone $base)->select('stock_out_id'))
            ->sum('quantity');

        return view('inventory.stock-out.index', [
            'stockOuts' => $stockOuts,
            'clients' => Client::query()
                ->where(fn ($query) => $query->active()
                    ->orWhereIn('client_id', StockOut::query()->select('client_id')))
                ->orderBy('client_name')
                ->get(),
            'batches' => Batch::active()->with('scionVariety')->orderBy('batch_code')->get(),
            'filters' => $filters,
            'totalReleased' => $totalReleased,
            'totalTransactions' => (clone $base)->count(),
            'totalClients' => (clone $base)->distinct()->count('client_id'),
            'totalAmount' => (float) (clone $base)->sum('total_amount'),
        ]);
    }

    /**
     * Record a Stock Out header and all of its detail lines inside one DB
     * transaction (revised PRD §8). Availability is validated per line against
     * that line's own batch AND per batch for the summed lines *before* any
     * row is written, so a partial commit is impossible. The cap is the batch's
     * surviving quantity (`Batch::survivingQuantity()`), not the raw
     * `available_count`, so plants already recorded as mortality can never be
     * released. `scion_variety_id`, `amount` and `total_amount` are never read
     * from the request.
     */
    public function store(StoreStockOutRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $fingerprint = DuplicateSubmissionGuard::assertFresh('stock_out', $validated, 'stockout');

        try {
            $this->stockOuts->execute($validated, (int) Auth::id());
        } catch (\Throwable $exception) {
            DuplicateSubmissionGuard::release('stock_out', $fingerprint);

            throw $exception;
        }

        return redirect()
            ->route('inventory.stock-out')
            ->with('success', 'Stock-out transaction recorded successfully.');
    }

    public function storeClient(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        return redirect()
            ->route('inventory.stock-out')
            ->with('success', 'Client added successfully.')
            ->with('new_client_id', $client->client_id);
    }
}
