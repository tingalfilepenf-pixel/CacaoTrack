<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\MortalityAdjustment;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\StockReversal;
use App\Models\TaskComplete;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Backfills Activity_Log (FR-17 / PRD §24) from existing operational records.
 *
 * Every row is derived from a real mortality, replanting, stock, reversal or
 * checklist record — nothing is fabricated (RULE 5/12). The descriptions match
 * the format used by the live writers so re-running is idempotent.
 */
class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MortalityRecord::with('batch')->get() as $record) {
            if (! $record->batch) {
                continue;
            }

            $this->log(
                $record->batch_id,
                ActivityLog::TYPE_MORTALITY_RECORDED,
                "Recorded {$record->quantity} mortality for {$record->batch->batch_code} due to {$record->cause}.",
                (int) $record->user_id,
                $record->recorded_at,
            );
        }

        foreach (MortalityAdjustment::with('mortalityRecord.batch')->get() as $adjustment) {
            $record = $adjustment->mortalityRecord;

            if (! $record || ! $record->batch) {
                continue;
            }

            $delta = (int) $adjustment->adjustment_quantity;
            $formattedDelta = ($delta > 0 ? '+' : '').$delta;

            $this->log(
                $record->batch_id,
                ActivityLog::TYPE_MORTALITY_ADJUSTED,
                "Adjusted mortality record #{$record->mortality_id} by {$formattedDelta} ({$adjustment->reason}).",
                (int) $adjustment->approved_by_id,
                $adjustment->adjusted_at,
            );
        }

        foreach (ReplantingRecord::with('batch')->get() as $record) {
            if (! $record->batch) {
                continue;
            }

            $this->log(
                $record->batch_id,
                ActivityLog::TYPE_REPLANTING_RECORDED,
                "Recorded {$record->quantity} replanting for {$record->batch->batch_code}.",
                (int) $record->user_id,
                $record->replanting_date,
            );
        }

        foreach (StockIn::with(['supplier', 'batch'])->get() as $stockIn) {
            if (! $stockIn->batch) {
                continue;
            }

            $this->log(
                $stockIn->batch_id,
                ActivityLog::TYPE_STOCK_IN_RECORDED,
                "Received {$stockIn->quantity} trees from ".($stockIn->supplier?->supplier_name ?? 'supplier')
                    ." for {$stockIn->batch->batch_code}.",
                (int) $stockIn->user_id,
                $stockIn->date_received,
            );
        }

        foreach (StockOut::with(['client', 'batch'])->get() as $stockOut) {
            if (! $stockOut->batch) {
                continue;
            }

            $this->log(
                $stockOut->batch_id,
                ActivityLog::TYPE_STOCK_OUT_RECORDED,
                "Released {$stockOut->quantity} trees to ".($stockOut->client?->client_name ?? 'client')
                    ." from {$stockOut->batch->batch_code}.",
                (int) $stockOut->user_id,
                $stockOut->release_date,
            );
        }

        foreach (StockReversal::with(['stockIn.batch', 'stockOut.batch'])->get() as $reversal) {
            $batch = $reversal->sourceBatch();
            $reference = $reversal->stock_in_id
                ? 'stock-in #'.$reversal->stock_in_id
                : 'stock-out #'.$reversal->stock_out_id;

            $this->log(
                $batch?->batch_id,
                ActivityLog::TYPE_STOCK_REVERSAL_RECORDED,
                "Reversed {$reversal->quantity} from {$reference} for ".($batch?->batch_code ?? '—')
                    ." ({$reversal->reason}).",
                (int) $reversal->user_id,
                $reversal->reversed_at,
            );
        }

        foreach (TaskComplete::with('task')->get() as $completion) {
            $this->log(
                null,
                ActivityLog::TYPE_TASK_COMPLETED,
                'Completed task: '.($completion->task?->task_name ?? 'task').'.',
                (int) $completion->user_id,
                $completion->completion_date,
            );
        }
    }

    private function log(?int $batchId, string $type, string $description, int $userId, mixed $date): void
    {
        ActivityLog::firstOrCreate([
            'user_id' => $userId,
            'batch_id' => $batchId,
            'activity_type' => $type,
            'description' => Str::limit($description, 255),
            'activity_date' => Carbon::parse($date),
        ]);
    }
}
