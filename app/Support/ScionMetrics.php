<?php

namespace App\Support;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\Scion;

/**
 * Centralized Scion Management card metrics (approved ERD revision §2.8/§9.10;
 * RULE 6 — one definition shared by the page and any report).
 *
 * Every figure is derived from real data only (RULE 5/12):
 *
 *  - available  = SUM(scions.quantity) — the stored scion stock balance. It
 *                 rises on every Receive Scions action and falls whenever a
 *                 replanting record is saved for one of the scion's batches.
 *  - re-grafted = SUM(replanting_records.quantity) over the scion's batches
 *  - rejected   = effective mortality over the scion's batches
 *                 (raw mortality + signed adjustments)
 *  - received   = available + re-grafted (total scions credited; receiving is
 *                 the only credit and replanting the only debit, so this stays
 *                 accurate as the balance changes).
 */
class ScionMetrics
{
    /**
     * @param  iterable<int, int|string>  $scionIds
     * @return array{received:int, regrafted:int, rejected:int, available:int}
     */
    public static function forScions(iterable $scionIds): array
    {
        $ids = collect($scionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($ids === []) {
            return ['received' => 0, 'regrafted' => 0, 'rejected' => 0, 'available' => 0];
        }

        $available = (int) Scion::query()->whereIn('scion_id', $ids)->sum('quantity');

        $batchIds = Batch::query()
            ->whereIn('scion_id', $ids)
            ->pluck('batch_id')
            ->all();

        $regrafted = ReplantingRecord::totalForBatches($batchIds);
        $rejected = MortalityRecord::effectiveQuantityForBatches($batchIds);

        return [
            'received' => $available + $regrafted,
            'regrafted' => $regrafted,
            'rejected' => $rejected,
            'available' => $available,
        ];
    }
}
