<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\StockReversal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockReversalController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:in,out'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $base = StockReversal::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('stockIn.batch', fn ($batchQuery) => $batchQuery
                            ->where('batch_code', 'like', "%{$search}%"))
                        ->orWhereHas('stockOut.batch', fn ($batchQuery) => $batchQuery
                            ->where('batch_code', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['type'] ?? null) === StockReversal::TYPE_IN, fn ($query) => $query
                ->whereNotNull('stock_in_id'))
            ->when(($filters['type'] ?? null) === StockReversal::TYPE_OUT, fn ($query) => $query
                ->whereNotNull('stock_out_id'))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query
                ->whereDate('reversed_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query
                ->whereDate('reversed_at', '<=', $dateTo));

        $reversals = (clone $base)
            ->with([
                'stockIn.supplier',
                'stockIn.batch.variety',
                'stockOut.client',
                'stockOut.batch.variety',
                'user',
            ])
            ->orderByDesc('reversed_at')
            ->orderByDesc('reversal_id')
            ->paginate(10)
            ->withQueryString();

        $totalReversed = (int) (clone $base)->sum('quantity');
        $totalReversals = (clone $base)->count();
        $stockInReversals = (clone $base)->whereNotNull('stock_in_id')->count();
        $stockOutReversals = (clone $base)->whereNotNull('stock_out_id')->count();

        return view('inventory.stock-reversal.index', [
            'reversals' => $reversals,
            'reversibleOptions' => $this->reversibleOptions(),
            'filters' => $filters,
            'totalReversed' => $totalReversed,
            'totalReversals' => $totalReversals,
            'stockInReversals' => $stockInReversals,
            'stockOutReversals' => $stockOutReversals,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:'.StockReversal::TYPE_IN.','.StockReversal::TYPE_OUT],
            'source_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'reversed_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('inventory.stock-reversal')
                ->withErrors($validator, 'reversal')
                ->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($validated, $validator): void {
                if ($validated['type'] === StockReversal::TYPE_IN) {
                    $source = StockIn::query()->lockForUpdate()->find($validated['source_id']);
                } else {
                    $source = StockOut::query()->lockForUpdate()->find($validated['source_id']);
                }

                if (! $source) {
                    $validator->errors()->add('source_id', 'The selected transaction could not be found.');

                    throw new ValidationException($validator, null, 'reversal');
                }

                $reversible = $source->reversibleQuantity();

                if ($reversible <= 0) {
                    $validator->errors()->add(
                        'source_id',
                        'This transaction has already been fully reversed.'
                    );

                    throw new ValidationException($validator, null, 'reversal');
                }

                if ((int) $validated['quantity'] > $reversible) {
                    $validator->errors()->add(
                        'quantity',
                        'The reversal quantity cannot exceed the reversible quantity ('
                            .number_format($reversible).').'
                    );

                    throw new ValidationException($validator, null, 'reversal');
                }

                $inventory = Inventory::query()
                    ->where('batch_id', $source->batch_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    $batch = $source->batch;

                    if (! $batch) {
                        $validator->errors()->add('source_id', 'The selected transaction has no batch.');

                        throw new ValidationException($validator, null, 'reversal');
                    }

                    $inventory = Inventory::syncFromBatch($batch);
                }

                if ($source instanceof StockIn) {
                    if ((int) $inventory->current_quantity < (int) $validated['quantity']) {
                        $validator->errors()->add(
                            'quantity',
                            'This stock-in cannot be reversed by that quantity because only '
                                .number_format((int) $inventory->current_quantity)
                                .' unit(s) remain in inventory.'
                        );

                        throw new ValidationException($validator, null, 'reversal');
                    }

                    StockReversal::create([
                        'stock_in_id' => $source->stock_in_id,
                        'stock_out_id' => null,
                        'user_id' => Auth::id(),
                        'quantity' => $validated['quantity'],
                        'reason' => $validated['reason'],
                        'reversed_at' => $validated['reversed_at'],
                    ]);

                    $inventory->applyDelta(-1 * (int) $validated['quantity']);
                } else {
                    StockReversal::create([
                        'stock_in_id' => null,
                        'stock_out_id' => $source->stock_out_id,
                        'user_id' => Auth::id(),
                        'quantity' => $validated['quantity'],
                        'reason' => $validated['reason'],
                        'reversed_at' => $validated['reversed_at'],
                    ]);

                    $inventory->applyDelta((int) $validated['quantity']);
                }

                $reference = $source instanceof StockIn
                    ? 'stock-in #'.$source->stock_in_id
                    : 'stock-out #'.$source->stock_out_id;

                ActivityLog::record(
                    $source->batch_id,
                    ActivityLog::TYPE_STOCK_REVERSAL_RECORDED,
                    "Reversed {$validated['quantity']} from {$reference} for "
                        .($source->batch?->batch_code ?? '—')." ({$validated['reason']}).",
                    null,
                    $validated['reversed_at'],
                );
            });
        } catch (ValidationException $exception) {
            return redirect()
                ->route('inventory.stock-reversal')
                ->withErrors($exception->validator, 'reversal')
                ->withInput();
        }

        return redirect()
            ->route('inventory.stock-reversal')
            ->with('success', 'Stock reversal recorded successfully.');
    }

    /**
     * Reversible transaction options for the Create Reversal modal.
     *
     * A stock-in reversal is additionally bounded by the batch's current
     * inventory (the balance cannot go negative); a stock-out reversal is
     * bounded only by the reversible quantity (FR-10).
     *
     * @return array{in: array<int, array{id:int,label:string,max:int}>, out: array<int, array{id:int,label:string,max:int}>}
     */
    private function reversibleOptions(): array
    {
        $stockIns = StockIn::with(['batch.inventory'])
            ->get()
            ->map(function (StockIn $stockIn): ?array {
                $reversible = $stockIn->reversibleQuantity();

                if ($reversible <= 0) {
                    return null;
                }

                $batch = $stockIn->batch;
                $onHand = (int) ($batch?->inventory?->current_quantity ?? $batch?->available_count ?? 0);
                $max = min($reversible, $onHand);

                if ($max <= 0) {
                    return null;
                }

                return [
                    'id' => $stockIn->stock_in_id,
                    'label' => $stockIn->reference()
                        .' — '.($batch?->batch_code ?? '—')
                        .' — '.number_format((int) $stockIn->quantity).' received'
                        .' (reversible '.number_format($max).')',
                    'max' => $max,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $stockOuts = StockOut::with(['batch'])
            ->get()
            ->map(function (StockOut $stockOut): ?array {
                $reversible = $stockOut->reversibleQuantity();

                if ($reversible <= 0) {
                    return null;
                }

                return [
                    'id' => $stockOut->stock_out_id,
                    'label' => $stockOut->reference()
                        .' — '.($stockOut->batch?->batch_code ?? '—')
                        .' — '.number_format((int) $stockOut->quantity).' released'
                        .' (reversible '.number_format($reversible).')',
                    'max' => $reversible,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'in' => $stockIns,
            'out' => $stockOuts,
        ];
    }
}
