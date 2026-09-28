<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\TaskComplete;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Backfills Activity_Log (FR-17 / PRD §24) from existing operational records.
 *
 * Every row is derived from a real mortality, regraft, stock or checklist
 * record — nothing is fabricated (RULE 5/12). The descriptions match the format
 * used by the live writers (ActivityLog::record()), so re-running the seeder is
 * idempotent via firstOrCreate().
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

        foreach (ReplantingRecord::with('batch')->get() as $record) {
            if (! $record->batch) {
                continue;
            }

            $this->log(
                $record->batch_id,
                ActivityLog::TYPE_REPLANTING_RECORDED,
                "Recorded {$record->quantity} regraft for {$record->batch->batch_code}.",
                (int) $record->user_id,
                $record->replanting_date,
            );
        }

        foreach (StockIn::with('supplier')->get() as $stockIn) {
            // Stock In has no batch (ERD §3.7), so the audit row is unbound.
            $this->log(
                null,
                ActivityLog::TYPE_STOCK_IN_RECORDED,
                "Received {$stockIn->quantity} seedlings from ".($stockIn->supplier?->supplier_name ?? 'supplier')
                    ." (origin: {$stockIn->origin}).",
                (int) $stockIn->user_id,
                $stockIn->date_received,
            );
        }

        foreach (StockOut::with(['client', 'details'])->get() as $stockOut) {
            $batchIds = $stockOut->details->pluck('batch_id')->unique();
            $batchId = $batchIds->count() === 1 ? (int) $batchIds->first() : null;
            $totalQuantity = (int) $stockOut->details->sum('quantity');
            $lineCount = $stockOut->details->count();

            $this->log(
                $batchId,
                ActivityLog::TYPE_STOCK_OUT_RECORDED,
                "Released {$totalQuantity} seedlings to ".($stockOut->client?->client_name ?? 'client')
                    ." across {$lineCount} line(s).",
                (int) $stockOut->user_id,
                $stockOut->release_date,
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
