<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\StockOut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place a Stock_Out header and its detail lines are written
 * (RULE 6). Both the direct Stock Out form and the proposed approval workflow
 * call this action, so the per-line/aggregate caps, lock order, balance
 * decrement and Activity Log behaviour stay identical.
 */
class RecordStockOut
{
    /**
     * @param  array<string, mixed>  $data  Validated stock-out payload.
     */
    public function execute(array $data, int $actorId): StockOut
    {
        return DB::transaction(function () use ($data, $actorId): StockOut {
            $batches = $this->lockAndValidateBatches($data['details']);

            $header = StockOut::create([
                'client_id' => $data['client_id'],
                'user_id' => $actorId,
                'release_date' => $data['release_date'],
                'total_amount' => null,
            ]);

            $totalAmount = 0.0;
            $hasAmount = false;

            foreach ($data['details'] as $line) {
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

            foreach ($batches as $batchId => $batch) {
                $released = (int) collect($data['details'])
                    ->where('batch_id', $batchId)
                    ->sum('quantity');

                $batch->decrement('available_count', $released);
            }

            $this->recordActivityLog($data, $batches, $actorId);

            return $header;
        });
    }

    /**
     * Lock every referenced batch (ascending id) and validate all lines before
     * any write.
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
            ->orderBy('batch_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('batch_id');

        foreach ($details as $index => $line) {
            $batch = $batches->get((int) $line['batch_id']);

            if ($batch === null) {
                $this->fail([
                    "details.{$index}.batch_id" => 'The selected batch could not be found.',
                ]);
            }

            $surviving = $batch->survivingQuantity();

            if ((int) $line['quantity'] > $surviving) {
                $this->fail([
                    "details.{$index}.quantity" => "Quantity exceeds the {$surviving} surviving plants available for {$batch->batch_code}.",
                ]);
            }
        }

        foreach ($lineIndexByBatch as $batchId => $firstIndex) {
            $batch = $batches->get($batchId);

            $requested = (int) collect($details)
                ->where('batch_id', $batchId)
                ->sum('quantity');

            if ($batch->scion_variety_id === null) {
                $this->fail([
                    "details.{$firstIndex}.batch_id" => "Batch {$batch->batch_code} has no scion variety assigned, so it cannot be released.",
                ]);
            }

            $surviving = $batch->survivingQuantity();

            if ($requested > $surviving) {
                $this->fail([
                    "details.{$firstIndex}.quantity" => "Quantity exceeds the {$surviving} surviving plants available for {$batch->batch_code}.",
                ]);
            }
        }

        return $batches;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Batch>  $batches
     */
    private function recordActivityLog(array $data, Collection $batches, int $actorId): void
    {
        $client = Client::find($data['client_id']);
        $totalQuantity = (int) collect($data['details'])->sum('quantity');
        $lineCount = count($data['details']);
        $batchId = $batches->count() === 1 ? $batches->keys()->first() : null;

        ActivityLog::record(
            $batchId,
            ActivityLog::TYPE_STOCK_OUT_RECORDED,
            "Released {$totalQuantity} seedlings to ".($client?->client_name ?? 'client')
                ." across {$lineCount} line(s).",
            $actorId,
            $data['release_date'],
        );
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function fail(array $messages): never
    {
        throw ValidationException::withMessages($messages)->errorBag('stockout');
    }
}
