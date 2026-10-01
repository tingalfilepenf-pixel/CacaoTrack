<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\StockOut;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place a previously recorded operational entry is corrected
 * (RULE 6 — no new table and no revival of the removed Mortality_Adjustments /
 * Stock_Reversal concepts).
 *
 * A correction re-runs the **same live-state business rules** as the original
 * entry. It never bypasses validation: the record is locked, the affected
 * batches are locked, the rule is re-checked against the current data, and the
 * live balances are adjusted atomically. Every correction then writes one
 * `record_corrected` Activity_Log row carrying the old and new value of every
 * changed field (see {@see ActivityLog::recordCorrection()}).
 *
 * The controller delegates here so the page, the tests and any future caller
 * all share the one implementation.
 */
class CorrectRecord
{
    /**
     * The specific rejection shown when a regraft cannot be corrected because
     * the sub-batch it created already has dependent activity (the same rule
     * that locks a regraft transaction once its sub-batch has operational
     * records).
     */
    public static function dependentSubBatchMessage(string $subBatchCode): string
    {
        return "This regraft cannot be corrected because the sub-batch it created ({$subBatchCode}) already has operational records (mortality, stock-out or further regraft).";
    }

    /**
     * Correct a Mortality_Record: quantity, cause and date recorded. The batch
     * is immutable on the record (a correction never moves mortality between
     * batches). The quantity is re-validated against the batch's remaining
     * mortality capacity **excluding this record's own current quantity**, so a
     * reduced or unchanged value is always allowed while a value that would
     * over-record mortality is rejected exactly like a new entry.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function mortality(MortalityRecord $record, array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($record, $data, $actorId): array {
            $locked = MortalityRecord::query()->lockForUpdate()->findOrFail($record->mortality_id);
            $batch = Batch::query()->lockForUpdate()->findOrFail($locked->batch_id);

            $newQuantity = (int) $data['quantity'];

            // Capacity available to THIS record: the batch's still-attributable
            // population minus every OTHER mortality record.
            $capacity = ((int) $batch->total_grafted - (int) $batch->regraft)
                - MortalityRecord::effectiveQuantityForBatch($batch->batch_id, $locked->mortality_id);

            if ($newQuantity > $capacity) {
                $this->fail('mortality_correction', [
                    'quantity' => "Mortality cannot exceed the batch's remaining mortality capacity of {$capacity} for {$batch->batch_code}.",
                ]);
            }

            $cause = MortalityRecord::composeCause($data['cause'], $data['cause_other'] ?? null);
            $recordedAt = Carbon::parse($data['recorded_at']);

            $changes = $this->diff([
                'quantity' => [(int) $locked->quantity, $newQuantity],
                'cause' => [$locked->cause, $cause],
                'recorded_at' => [$locked->recorded_at?->toDateString(), $recordedAt->toDateString()],
            ]);

            $this->assertChanged('mortality_correction', $changes);

            $locked->quantity = $newQuantity;
            $locked->cause = $cause;
            $locked->recorded_at = $recordedAt;
            $locked->save();

            ActivityLog::recordCorrection(
                'mortality record',
                $locked->mortality_id,
                $batch->batch_id,
                $changes,
                $actorId,
            );

            return $changes;
        });
    }

    /**
     * Correct a Regraft_Record: quantity, sub-batch scion variety, regraft date
     * and remark. The source batch's live balance and regraft counter and the
     * linked sub-batch's counts are adjusted atomically so the invariants keep
     * holding. The new quantity is re-validated against the source's
     * surviving cap as if the original regraft had never happened.
     *
     * Blocked outright when the created sub-batch already has dependent activity
     * (mortality, stock-out or a further regraft), matching the rule that locks
     * a regraft transaction once its sub-batch has operational records.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function regraft(RegraftRecord $record, array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($record, $data, $actorId): array {
            $locked = RegraftRecord::query()->lockForUpdate()->findOrFail($record->regraft_id);

            if ($locked->parent_batch_id === null) {
                $this->fail('regraft_correction', [
                    'record' => 'This regraft record has no source batch and cannot be corrected.',
                ]);
            }

            $source = Batch::query()->lockForUpdate()->findOrFail($locked->parent_batch_id);
            $subBatch = Batch::query()->lockForUpdate()->findOrFail($locked->batch_id);

            // Rule 4: a regraft whose sub-batch already has operational records
            // is locked and must not be rewritten.
            if ($subBatch->hasDependentActivity()) {
                $this->fail('regraft_correction', [
                    'record' => self::dependentSubBatchMessage($subBatch->batch_code),
                ]);
            }

            $oldQuantity = (int) $locked->quantity;
            $newQuantity = (int) $data['quantity'];

            // Regraftable cap with this record's own quantity treated as
            // returned to the source, then re-applied.
            $availableWithReturn = (int) $source->available_count + $oldQuantity;
            $liveWithReturn = ((int) $source->total_grafted - ((int) $source->regraft - $oldQuantity))
                - $source->effectiveMortalityQuantity();
            $regraftable = max(0, min($availableWithReturn, $liveWithReturn));

            if ($newQuantity > $regraftable) {
                $this->fail('regraft_correction', [
                    'quantity' => "Regraft quantity cannot exceed the {$regraftable} available for regrafting in {$source->batch_code}.",
                ]);
            }

            $scionVariety = ScionVariety::query()
                ->whereKey($data['scion_variety_id'])
                ->whereNull('archived_at')
                ->first();

            if ($scionVariety === null) {
                $this->fail('regraft_correction', [
                    'scion_variety_id' => 'The selected scion variety is not available.',
                ]);
            }

            $date = Carbon::parse($data['regraft_date'])->startOfDay();
            $graftedDate = $source->grafted_date?->copy()->startOfDay();

            if ($graftedDate !== null && $date->lt($graftedDate)) {
                $this->fail('regraft_correction', [
                    'regraft_date' => "The regraft date cannot be before the source batch's grafted date ({$graftedDate->format('M j, Y')}).",
                ]);
            }

            $remark = $data['remark'] ?? null;

            $changes = $this->diff([
                'quantity' => [$oldQuantity, $newQuantity],
                'scion_variety_id' => [$subBatch->scion_variety_id, $scionVariety->scion_variety_id],
                'regraft_date' => [$locked->regraft_date?->toDateString(), $date->toDateString()],
                'remark' => [$locked->remark, $remark],
            ]);

            $this->assertChanged('regraft_correction', $changes);

            // Source: return the old quantity then move the new quantity out.
            $source->available_count = (int) $source->available_count + $oldQuantity - $newQuantity;
            $source->regraft = (int) $source->regraft - $oldQuantity + $newQuantity;
            $source->save();

            // The sub-batch is guaranteed to carry exactly the regrafted
            // quantity (no operational records, so nothing else has moved it).
            $subBatch->total_grafted = $newQuantity;
            $subBatch->available_count = $newQuantity;
            $subBatch->scion_variety_id = $scionVariety->scion_variety_id;
            $subBatch->grafted_date = $date->toDateString();
            $subBatch->save();

            $locked->quantity = $newQuantity;
            $locked->regraft_date = $date->toDateString();
            $locked->remark = $remark;
            $locked->save();

            ActivityLog::recordCorrection(
                'regraft record',
                $locked->regraft_id,
                $subBatch->batch_id,
                $changes,
                $actorId,
            );

            return $changes;
        });
    }

    /**
     * Correct a Stock_In receiving record. Stock In holds no live batch balance
     * (ERD §3.7), so the correction is a straight re-validated update of the
     * receiving fields; the scion/seedling "both or neither" rule is enforced by
     * the form request exactly as for a new entry.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function stockIn(StockIn $record, array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($record, $data, $actorId): array {
            $locked = StockIn::query()->lockForUpdate()->findOrFail($record->stock_in_id);

            $changes = $this->diff([
                'supplier_id' => [$locked->supplier_id, $data['supplier_id']],
                'scion_variety_id' => [$locked->scion_variety_id, $data['scion_variety_id'] ?? null],
                'quantity' => [(int) $locked->quantity, (int) $data['quantity']],
                'unit_price' => [$locked->unit_price, $data['unit_price'] ?? null],
                'origin' => [$locked->origin, $data['origin']],
                'notes' => [$locked->notes, $data['notes'] ?? null],
                'date_received' => [$locked->date_received?->toDateString(), Carbon::parse($data['date_received'])->toDateString()],
            ]);

            $this->assertChanged('stockin_correction', $changes);

            $locked->supplier_id = $data['supplier_id'];
            $locked->scion_variety_id = $data['scion_variety_id'] ?? null;
            $locked->quantity = (int) $data['quantity'];
            $locked->unit_price = $data['unit_price'] ?? null;
            $locked->origin = $data['origin'];
            $locked->notes = $data['notes'] ?? null;
            $locked->date_received = Carbon::parse($data['date_received']);
            $locked->save();

            ActivityLog::recordCorrection(
                'stock-in record',
                $locked->stock_in_id,
                null,
                $changes,
                $actorId,
            );

            return $changes;
        });
    }

    /**
     * Correct a Stock_Out header and its detail lines. Each line's released
     * quantity is returned to its batch's live balance first, the edited lines
     * are re-validated against the same `Batch::survivingQuantity()` cap a new
     * release uses, and only then is the new release applied — all in one
     * transaction, so a rejected correction leaves the balances untouched.
     *
     * Lines are edited in place (identified by `stock_out_detail_id`); lines are
     * neither added nor removed, so the transaction's shape is preserved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function stockOut(StockOut $record, array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($record, $data, $actorId): array {
            $header = StockOut::query()->lockForUpdate()->findOrFail($record->stock_out_id);
            $details = $header->details()->orderBy('stock_out_detail_id')->get();

            $oldByBatch = [];
            foreach ($details as $detail) {
                $oldByBatch[(int) $detail->batch_id] = ($oldByBatch[(int) $detail->batch_id] ?? 0) + (int) $detail->quantity;
            }

            $newByBatch = [];
            foreach ($data['details'] as $line) {
                $newByBatch[(int) $line['batch_id']] = ($newByBatch[(int) $line['batch_id']] ?? 0) + (int) $line['quantity'];
            }

            $batchIds = array_values(array_unique(array_merge(array_keys($oldByBatch), array_keys($newByBatch))));

            $batches = Batch::query()->whereIn('batch_id', $batchIds)->lockForUpdate()->get()->keyBy('batch_id');

            // Return every quantity this stock-out released, so validation runs
            // against the balance as if this transaction had never happened.
            foreach ($oldByBatch as $batchId => $quantity) {
                $batch = $batches->get($batchId);

                if ($batch === null) {
                    continue;
                }

                $batch->available_count = (int) $batch->available_count + $quantity;
            }

            // Re-validate the edited lines with the same cap and messages as a
            // new release.
            foreach ($newByBatch as $batchId => $quantity) {
                $batch = $batches->get($batchId);

                if ($batch === null) {
                    $this->fail('stockout_correction', ['details' => 'A selected batch could not be found.']);
                }

                if ($batch->scion_variety_id === null) {
                    $this->fail('stockout_correction', [
                        'details' => "Batch {$batch->batch_code} has no scion variety assigned, so it cannot be released.",
                    ]);
                }

                $surviving = $batch->survivingQuantity();

                if ($quantity > $surviving) {
                    $this->fail('stockout_correction', [
                        'details' => "Quantity exceeds the {$surviving} surviving plants available for {$batch->batch_code}.",
                    ]);
                }
            }

            // Apply the new release.
            foreach ($newByBatch as $batchId => $quantity) {
                $batch = $batches->get($batchId);
                $batch->available_count = (int) $batch->available_count - $quantity;
            }

            foreach ($batches as $batch) {
                $batch->save();
            }

            $client = Client::find($data['client_id']);
            $releaseDate = Carbon::parse($data['release_date']);

            $changes = $this->diff([
                'client' => [$header->client?->client_name, $client?->client_name],
                'release_date' => [$header->release_date?->toDateString(), $releaseDate->toDateString()],
            ]);

            $totalAmount = 0.0;
            $hasAmount = false;
            $lineChanges = [];

            foreach ($data['details'] as $line) {
                $detail = $details->firstWhere('stock_out_detail_id', (int) $line['id']);

                if ($detail === null) {
                    $this->fail('stockout_correction', ['details' => 'A stock-out line could not be found.']);
                }

                $unitPrice = $line['unit_price'] ?? null;
                $amount = $unitPrice === null ? null : round((float) $unitPrice * (int) $line['quantity'], 2);

                $lineChanges[(string) $detail->stock_out_detail_id] = $this->diff([
                    'batch' => [$detail->batch?->batch_code, Batch::query()->whereKey($line['batch_id'])->value('batch_code')],
                    'quantity' => [$detail->quantity, (int) $line['quantity']],
                    'unit' => [$detail->unit, $line['unit']],
                    'unit_price' => [$detail->unit_price, $unitPrice],
                ]);

                $detail->batch_id = $line['batch_id'];
                $detail->quantity = (int) $line['quantity'];
                $detail->unit = $line['unit'];
                $detail->unit_price = $unitPrice;
                $detail->amount = $amount;
                $detail->save();

                if ($amount !== null) {
                    $hasAmount = true;
                    $totalAmount += $amount;
                }
            }

            $lineChanges = array_filter($lineChanges);

            if ($lineChanges !== []) {
                $changes['details'] = $lineChanges;
            }

            $this->assertChanged('stockout_correction', $changes);

            $header->client_id = $data['client_id'];
            $header->release_date = $releaseDate;
            $header->total_amount = $hasAmount ? round($totalAmount, 2) : null;
            $header->save();

            $batchId = count($newByBatch) === 1 ? array_key_first($newByBatch) : null;

            ActivityLog::recordCorrection(
                'stock-out record',
                $header->stock_out_id,
                $batchId,
                $changes,
                $actorId,
            );

            return $changes;
        });
    }

    /**
     * Build the changed-only before/after map. Fields whose old and new values
     * are equivalent (loose numeric equality, null ≡ blank string) are omitted.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $candidates
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(array $candidates): array
    {
        $changes = [];

        foreach ($candidates as $field => $pair) {
            [$from, $to] = $pair;

            if (! $this->sameValue($from, $to)) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    private function sameValue(mixed $a, mixed $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return (string) $a === (string) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    private function assertChanged(string $errorBag, array $changes): void
    {
        if ($changes === []) {
            $this->fail($errorBag, ['record' => 'No changes were made to the record.']);
        }
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function fail(string $errorBag, array $messages): never
    {
        throw ValidationException::withMessages($messages)->errorBag($errorBag);
    }
}
