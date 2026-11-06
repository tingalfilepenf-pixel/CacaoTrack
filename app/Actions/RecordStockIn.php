<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * The single place official Stock_In receiving rows are written (RULE 6).
 *
 * Both the direct Stock In form and the proposed approval workflow call this
 * action, so the field mapping, transaction boundary and Activity Log wording
 * can never drift between the two paths. Stock In is receiving-only: it does
 * not change any Batch, available_count or Inventory balance (decision 9).
 */
class RecordStockIn
{
    /**
     * Write one official stock_ins row per populated entry, in a single
     * transaction.
     *
     * @param  array<string, array<string, mixed>>  $entries  Keyed by `seedling` and/or `scion`.
     * @return array<string, StockIn> Created rows, keyed by the same entry names.
     */
    public function execute(array $entries, int $actorId): array
    {
        return DB::transaction(function () use ($entries, $actorId): array {
            $saved = [];

            foreach (['seedling', 'scion'] as $tab) {
                $entry = $entries[$tab] ?? null;

                if (! is_array($entry)) {
                    continue;
                }

                $isScion = $tab === 'scion';

                $record = StockIn::create([
                    'supplier_id' => $entry['supplier_id'] ?? null,
                    'scion_variety_id' => $isScion ? $entry['scion_variety_id'] : null,
                    'user_id' => $actorId,
                    'quantity' => $entry['quantity'],
                    'unit_price' => $isScion ? $entry['unit_price'] : null,
                    'origin' => $entry['origin'],
                    'notes' => $entry['notes'] ?? null,
                    'date_received' => $entry['date_received'],
                ]);

                $this->recordReceiving($record, $entry, $isScion, $actorId);

                $saved[$tab] = $record;
            }

            return $saved;
        });
    }

    /**
     * Write the single centralized Activity_Log row for one receiving. The
     * wording matches the pre-grouping message so Activity History stays
     * consistent.
     *
     * @param  array<string, mixed>  $entry
     */
    private function recordReceiving(StockIn $record, array $entry, bool $isScion, int $actorId): void
    {
        $supplier = Supplier::find($entry['supplier_id'] ?? null);

        if ($isScion) {
            $scionVariety = ScionVariety::find($entry['scion_variety_id']);
            $description = "Received {$record->quantity} scion of "
                .($scionVariety?->scion_variety_name ?? 'scion variety')
                .' from '.($supplier?->supplier_name ?? 'supplier')
                ." (origin: {$record->origin}).";
        } else {
            $description = "Received {$record->quantity} seedlings from "
                .($supplier?->supplier_name ?? 'supplier')
                ." (origin: {$record->origin}).";
        }

        ActivityLog::record(
            null,
            ActivityLog::TYPE_STOCK_IN_RECORDED,
            $description,
            $actorId,
            $entry['date_received'],
        );
    }
}
