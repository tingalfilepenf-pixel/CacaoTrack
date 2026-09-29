<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single, atomic regraft / sub-batch creation flow (Phase 7B).
 *
 * Client-approved process (PRD §2.4 / §8 "Regrafting / Sub-Batch Creation";
 * ERD §2 rules 5-8, §3.8, §3.10, §3.11): when a batch is regrafted, the moved
 * quantity is deducted from the parent batch's live `Available_Count`, the
 * parent `Regraft` counter is increased by the same quantity, and the moved
 * plants establish a linked sub-batch with its own independent 21-day cycle and
 * its own Scion Variety. `Total_Grafted` is a **cumulative, non-decreasing log**
 * (client-confirmed — see `docs/CacaoTrack_PRD.md` §2.4), so a regraft never
 * reduces it; the batch's still-attributable population is
 * `Total_Grafted − Regraft`.
 *
 * This class is the **only** place the regraft steps live (RULE 6). The
 * controller, the seeders and the tests all go through {@see execute()}.
 *
 * Regraft details implemented here:
 *  - the regraftable cap is `Batch::regraftableQuantity()`
 *    (`min(available_count, (total_grafted - regraft) - effective mortality)`);
 *  - the sub-batch code is `Batch::nextSubBatchCode()` (`B001` → `B001-R1`);
 *  - the {@see RegraftRecord} stores `batch_id` = the new sub-batch and
 *    `parent_batch_id` = the source batch;
 *  - `batches.regraft` is the authoritative per-batch regrafted total.
 */
class RegraftBatch
{
    /**
     * Atomically regraft `$quantity` plants from `$source` into a new linked
     * sub-batch and return the created sub-batch.
     *
     * Every step runs inside one DB transaction: if any step fails, the parent
     * update, the sub-batch and the regraft record all roll back together
     * (PRD §8).
     *
     * @throws ValidationException when the source, quantity, scion variety or date is invalid.
     */
    public function execute(
        Batch $source,
        int $quantity,
        int $scionVarietyId,
        string $regraftDate,
        ?string $remark = null,
        ?int $userId = null,
    ): Batch {
        $actorId = $userId ?? Auth::id();

        return DB::transaction(function () use ($source, $quantity, $scionVarietyId, $regraftDate, $remark, $actorId): Batch {
            $locked = Batch::query()->lockForUpdate()->findOrFail($source->batch_id);

            if ($locked->isArchived()) {
                $this->fail([
                    'batch_id' => "Batch {$locked->batch_code} is archived and cannot be regrafted.",
                ]);
            }

            $regraftable = $locked->regraftableQuantity();

            if ($quantity < 1 || $quantity > $regraftable) {
                $this->fail([
                    'quantity' => "Regraft quantity cannot exceed the {$regraftable} available for regrafting in {$locked->batch_code}.",
                ]);
            }

            $scionVariety = ScionVariety::query()
                ->whereKey($scionVarietyId)
                ->whereNull('archived_at')
                ->first();

            if ($scionVariety === null) {
                $this->fail([
                    'scion_variety_id' => 'The selected scion variety is not available.',
                ]);
            }

            $date = Carbon::parse($regraftDate)->startOfDay();
            $graftedDate = $locked->grafted_date?->copy()->startOfDay();

            if ($graftedDate !== null && $date->lt($graftedDate)) {
                $this->fail([
                    'regraft_date' => "The regraft date cannot be before the source batch's grafted date ({$graftedDate->format('M j, Y')}).",
                ]);
            }

            // 4. Parent batch: move the quantity out of the live available
            //    balance and into the regraft counter. `total_grafted` is a
            //    cumulative, non-decreasing log (client-confirmed), so it is
            //    deliberately NOT reduced here — the batch's still-attributable
            //    population is `total_grafted - regraft`.
            $locked->available_count = (int) $locked->available_count - $quantity;
            $locked->regraft = (int) $locked->regraft + $quantity;
            $locked->save();

            // 5. The new linked sub-batch with its own 21-day cycle and variety.
            $subBatch = Batch::create([
                'scion_variety_id' => $scionVariety->scion_variety_id,
                'parent_batch_id' => $locked->batch_id,
                'user_id' => $actorId,
                'batch_code' => $locked->nextSubBatchCode(),
                'total_grafted' => $quantity,
                'regraft' => 0,
                'available_count' => $quantity,
                'grafted_date' => $date->toDateString(),
                'created_at' => now(),
                'status' => Batch::STATUS_ACTIVE,
            ]);

            // 6. The regraft record: attached to the new sub-batch and linked
            //    back to the source (provisional reading of ERD §3.11).
            RegraftRecord::create([
                'batch_id' => $subBatch->batch_id,
                'parent_batch_id' => $locked->batch_id,
                'user_id' => $actorId,
                'quantity' => $quantity,
                'regraft_date' => $date->toDateString(),
                'remark' => $remark,
            ]);

            // 7. Traceable activity history for the sub-batch.
            ActivityLog::record(
                $subBatch->batch_id,
                ActivityLog::TYPE_REGRAFT_RECORDED,
                "Regrafted {$quantity} from {$locked->batch_code} into {$subBatch->batch_code} ({$scionVariety->scion_variety_name}).",
                $actorId,
                $date,
            );

            return $subBatch;
        });
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function fail(array $messages): never
    {
        throw ValidationException::withMessages($messages)->errorBag('regraft');
    }
}
