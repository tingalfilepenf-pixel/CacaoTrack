<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\StockOut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockOutController extends Controller
{
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
                        ->orWhereHas('batch', fn ($batchQuery) => $batchQuery
                            ->where('batch_code', 'like', "%{$search}%"));
                });
            })
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query
                ->where('client_id', $clientId))
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query
                ->where('batch_id', $batchId))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query
                ->whereDate('release_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query
                ->whereDate('release_date', '<=', $dateTo));

        $stockOuts = (clone $base)
            ->with(['client', 'batch.variety', 'user'])
            ->orderByDesc('release_date')
            ->orderByDesc('stock_out_id')
            ->paginate(10)
            ->withQueryString();

        $totalReleased = (int) (clone $base)->sum('quantity');
        $totalTransactions = (clone $base)->count();
        $totalClients = (clone $base)->distinct()->count('client_id');
        $totalAmount = (float) (clone $base)->sum('total_amount');

        return view('inventory.stock-out.index', [
            'stockOuts' => $stockOuts,
            'clients' => Client::active()->orderBy('client_name')->get(),
            'batches' => Batch::active()->with(['variety', 'inventory'])->orderBy('batch_code')->get(),
            'filters' => $filters,
            'totalReleased' => $totalReleased,
            'totalTransactions' => $totalTransactions,
            'totalClients' => $totalClients,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => ['required', 'integer', Client::activeExistsRule('client_id')],
            'batch_id' => ['required', 'integer', Batch::activeExistsRule('batch_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'release_date' => ['required', 'date', 'before_or_equal:today'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.stock-out')
                ->withErrors($validator, 'stockout')
                ->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($validated, $validator): void {
                $batch = Batch::findOrFail($validated['batch_id']);

                $inventory = Inventory::query()
                    ->where('batch_id', $batch->batch_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    $inventory = Inventory::syncFromBatch($batch);
                }

                if ((int) $inventory->current_quantity < (int) $validated['quantity']) {
                    $validator->errors()->add(
                        'quantity',
                        'The release quantity cannot exceed the available inventory ('
                            .number_format((int) $inventory->current_quantity).').'
                    );

                    throw new ValidationException($validator, null, 'stockout');
                }

                StockOut::create([
                    'client_id' => $validated['client_id'],
                    'batch_id' => $batch->batch_id,
                    'user_id' => Auth::id(),
                    'quantity' => $validated['quantity'],
                    'total_amount' => $validated['total_amount'] ?? null,
                    'release_date' => $validated['release_date'],
                ]);

                $inventory->applyDelta(-1 * (int) $validated['quantity']);

                $client = Client::find($validated['client_id']);

                ActivityLog::record(
                    $batch->batch_id,
                    ActivityLog::TYPE_STOCK_OUT_RECORDED,
                    "Released {$validated['quantity']} trees to ".($client?->client_name ?? 'client')
                        ." from {$batch->batch_code}.",
                    null,
                    $validated['release_date'],
                );
            });
        } catch (ValidationException $exception) {
            return redirect()
                ->route('inventory.stock-out')
                ->withErrors($exception->validator, 'stockout')
                ->withInput();
        }

        return redirect()
            ->route('inventory.stock-out')
            ->with('success', 'Stock-out transaction recorded successfully.');
    }

    public function storeClient(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.stock-out')
                ->withErrors($validator, 'client')
                ->withInput();
        }

        $client = Client::create($validator->validated());

        return redirect()
            ->route('inventory.stock-out')
            ->with('success', 'Client added successfully.')
            ->with('new_client_id', $client->client_id);
    }
}
