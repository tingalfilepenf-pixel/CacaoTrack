<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place a Mortality_Record is written (RULE 6).
 *
 * Both the direct Mortality form and the proposed approval workflow call this
 * action, so the cap, lock, official row and Activity Log behaviour can never
 * drift between the two paths.
 */
class RecordMortality
{
    /**
     * @param  array<string, mixed>  $data  Validated mortality payload.
     */
    public function execute(array $data, int $actorId): MortalityRecord
    {
        return DB::transaction(function () use ($data, $actorId): MortalityRecord {
            $batch = Batch::query()->lockForUpdate()->findOrFail($data['batch_id']);

            $capacity = $batch->remainingMortalityCapacity();

            if ((int) $data['quantity'] > $capacity) {
                throw ValidationException::withMessages([
                    'quantity' => "Mortality cannot exceed the batch's remaining quantity of {$capacity} for {$batch->batch_code}.",
                ])->errorBag('mortality');
            }

            $cause = MortalityRecord::composeCause($data['cause'], $data['cause_other'] ?? null);

            $record = MortalityRecord::create([
                'batch_id' => $batch->batch_id,
                'user_id' => $actorId,
                'quantity' => $data['quantity'],
                'cause' => $cause,
                'recorded_at' => $data['recorded_at'],
            ]);

            ActivityLog::record(
                $batch->batch_id,
                ActivityLog::TYPE_MORTALITY_RECORDED,
                "Recorded {$data['quantity']} mortality for {$batch->batch_code} due to {$cause}.",
                $actorId,
                $data['recorded_at'],
            );

            return $record;
        });
    }
}
