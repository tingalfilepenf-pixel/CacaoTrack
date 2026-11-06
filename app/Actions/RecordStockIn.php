<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $entries = $this->revalidateEntries($entries);
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
     * Revalidate every populated entry and lock the master records it names.
     * Running this before the first official write keeps the grouped request
     * all-or-nothing and closes the archive race at approval time.
     *
     * @param  array<string, mixed>  $entries
     * @return array<string, array<string, mixed>>
     */
    private function revalidateEntries(array $entries): array
    {
        $prepared = [];
        $supplierIds = [];
        $varietyIds = [];

        foreach (['seedling', 'scion'] as $tab) {
            $entry = $entries[$tab] ?? null;

            if ($entry === null) {
                continue;
            }

            $isScion = $tab === 'scion';
            $label = $isScion ? 'Scion' : 'Seedlings';

            if (! is_array($entry)) {
                $this->fail($tab, "{$label} entry is not valid.");
            }

            $supplierId = $entry['supplier_id'] ?? null;
            $supplierId = is_scalar($supplierId) && trim((string) $supplierId) !== ''
                ? (int) $supplierId
                : null;

            if (! $isScion && $supplierId === null) {
                $this->fail($tab, "{$label} entry: select a supplier.");
            }

            if ($supplierId !== null) {
                $supplierIds[] = $supplierId;
            }

            $quantity = (int) ($entry['quantity'] ?? 0);

            if ($quantity < 1) {
                $this->fail($tab, "{$label} entry: the quantity must be at least 1.");
            }

            $origin = trim((string) ($entry['origin'] ?? ''));

            if ($origin === '') {
                $this->fail($tab, "{$label} entry: enter the origin.");
            }

            $notes = $entry['notes'] ?? null;

            if ($notes !== null && mb_strlen((string) $notes) > 1000) {
                $this->fail($tab, "{$label} entry: the notes are too long.");
            }

            $date = $entry['date_received'] ?? null;

            if (! is_string($date) || trim($date) === '') {
                $this->fail($tab, "{$label} entry: enter the date received.");
            }

            try {
                $received = Carbon::parse($date)->startOfDay();
            } catch (\Throwable) {
                $this->fail($tab, "{$label} entry: enter a valid date received.");
            }

            if ($received->gt(now()->startOfDay())) {
                $this->fail($tab, "{$label} entry: the date received cannot be in the future.");
            }

            $varietyId = null;
            $unitPrice = null;

            if ($isScion) {
                $varietyId = (int) ($entry['scion_variety_id'] ?? 0);

                if ($varietyId < 1) {
                    $this->fail($tab, 'Scion entry: select a scion variety.');
                }

                $varietyIds[] = $varietyId;

                $unitPrice = $entry['unit_price'] ?? null;

                if ($unitPrice === null || $unitPrice === '' || ! is_numeric($unitPrice) || (float) $unitPrice < 0) {
                    $this->fail($tab, 'Scion entry: enter a unit price (0 is allowed).');
                }
            }

            $prepared[$tab] = [
                'supplier_id' => $supplierId,
                'quantity' => $quantity,
                'origin' => $origin,
                'notes' => $notes !== null && trim((string) $notes) !== '' ? (string) $notes : null,
                'date_received' => $received->toDateString(),
                'scion_variety_id' => $varietyId,
                'unit_price' => $unitPrice,
            ];
        }

        if ($prepared === []) {
            $this->fail('entries', 'Fill in the Seedlings tab, the Scion tab, or both.');
        }

        $suppliers = Supplier::query()
            ->whereIn('supplier_id', array_values(array_unique($supplierIds)))
            ->orderBy('supplier_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('supplier_id');

        foreach (array_unique($supplierIds) as $supplierId) {
            $supplier = $suppliers->get($supplierId);

            if ($supplier === null || $supplier->isArchived()) {
                $this->fail('supplier_id', 'The selected supplier is no longer active.');
            }
        }

        $varieties = ScionVariety::query()
            ->whereIn('scion_variety_id', array_values(array_unique($varietyIds)))
            ->orderBy('scion_variety_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('scion_variety_id');

        foreach (array_unique($varietyIds) as $varietyId) {
            $variety = $varieties->get($varietyId);

            if ($variety === null || $variety->isArchived()) {
                $this->fail('scion_variety_id', 'The selected scion variety is no longer active.');
            }
        }

        return $prepared;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message])->errorBag('stockin');
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
