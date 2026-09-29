<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StoreStockOutRequest;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockOutController extends Controller
{
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
            DB::transaction(function () use ($validated): void {
                $batches = $this->lockAndValidateBatches($validated['details']);

                $header = StockOut::create([
                    'client_id' => $validated['client_id'],
                    'user_id' => Auth::id(),
                    'release_date' => $validated['release_date'],
                    'total_amount' => null,
                ]);

                $totalAmount = 0.0;
                $hasAmount = false;

                foreach ($validated['details'] as $line) {
                    $unitPrice = $line['unit_price'] ?? null;
                    $amount = $unitPrice === null
                        ? null
                        : round((float) $unitPrice * (int) $line['quantity'], 2);

                    $header->details()->create([
                        'batch_id' => $line['batch_id'],
                        'quantity' => $line['quantity'],
                        'unit' => $line['unit'],
                        'unit_price' => $unitPrice,
                        'amount' => $amount,
                    ]);

                    if ($amount !== null) {
                        $hasAmount = true;
                        $totalAmount += $amount;
                    }
                }

                if ($hasAmount) {
                    $header->forceFill(['total_amount' => round($totalAmount, 2)])->save();
                }

                // Decrement every referenced batch's live available balance.
                foreach ($batches as $batchId => $batch) {
                    $released = (int) collect($validated['details'])
                        ->where('batch_id', $batchId)
                        ->sum('quantity');

                    $batch->decrement('available_count', $released);
                }

                $this->recordActivityLog($validated, $batches);
            });
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

    /**
     * Lock every referenced batch and validate all lines before any write.
     *
     * @param  array<int, array<string, mixed>>  $details
     * @return Collection<int, Batch> Keyed by batch_id.
     */
    private function lockAndValidateBatches(array $details): Collection
    {
        $lineIndexByBatch = [];

        foreach ($details as $index => $line) {
            $lineIndexByBatch[(int) $line['batch_id']] ??= $index;
        }

        $batches = Batch::query()
            ->whereIn('batch_id', array_keys($lineIndexByBatch))
            ->lockForUpdate()
            ->get()
            ->keyBy('batch_id');

        // Per-line check against that line's own batch.
        foreach ($details as $index => $line) {
            $batch = $batches->get((int) $line['batch_id']);

            if ($batch === null) {
                $this->failValidation([
                    "details.{$index}.batch_id" => 'The selected batch could not be found.',
                ], 'stockout');
            }

            $surviving = $batch->survivingQuantity();

            if ((int) $line['quantity'] > $surviving) {
                $this->failValidation([
                    "details.{$index}.quantity" => "Quantity exceeds the {$surviving} surviving plants available for {$batch->batch_code}.",
                ], 'stockout');
            }
        }

        // Aggregate check: the same batch may not be over-released across lines.
        foreach ($lineIndexByBatch as $batchId => $firstIndex) {
            $batch = $batches->get($batchId);

            $requested = (int) collect($details)
                ->where('batch_id', $batchId)
                ->sum('quantity');

            if ($batch->scion_variety_id === null) {
                $this->failValidation([
                    "details.{$firstIndex}.batch_id" => "Batch {$batch->batch_code} has no scion variety assigned, so it cannot be released.",
                ], 'stockout');
            }

            $surviving = $batch->survivingQuantity();

            if ($requested > $surviving) {
                $this->failValidation([
                    "details.{$firstIndex}.quantity" => "Quantity exceeds the {$surviving} surviving plants available for {$batch->batch_code}.",
                ], 'stockout');
            }
        }

        return $batches;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, Batch>  $batches
     */
    private function recordActivityLog(array $validated, Collection $batches): void
    {
        $client = Client::find($validated['client_id']);
        $totalQuantity = (int) collect($validated['details'])->sum('quantity');
        $lineCount = count($validated['details']);
        $batchId = $batches->count() === 1 ? $batches->keys()->first() : null;

        ActivityLog::record(
            $batchId,
            ActivityLog::TYPE_STOCK_OUT_RECORDED,
            "Released {$totalQuantity} seedlings to ".($client?->client_name ?? 'client')
                ." across {$lineCount} line(s).",
            null,
            $validated['release_date'],
        );
    }
}
