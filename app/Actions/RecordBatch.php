<?php

namespace App\Actions;

use App\Exceptions\ApprovalRevalidationException;
use App\Exceptions\BatchCodeLockTimeoutException;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\ScionVariety;
use App\Models\Supplier;
use App\Support\StockBalances;
use Closure;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single place an official Batch row is written (RULE 6).
 *
 * Both the legacy direct form and the proposed Batch Creation approval call
 * this action. A named MySQL lock serializes code assignment per prefix
 * (`B` own nursery, `S` supplier delivery); the unique `batch_code` index is the
 * final guard and one retry covers a manual code that raced in. The named lock
 * is taken before any transaction/row lock and released in `finally`.
 */
class RecordBatch
{
    /**
     * Seconds to wait for the per-prefix named lock before treating the lock
     * as unavailable (an infrastructure error, not a business-rule failure).
     */
    private const CODE_LOCK_TIMEOUT_SECONDS = 10;

    /**
     * Create a legacy/direct batch. The direct path keeps its existing fields,
     * Activity Log wording and B-code sequence; it records no stock use and
     * performs no source-specific balance checks.
     *
     * @param  array<string, mixed>  $data  Validated StoreBatchRequest data.
     */
    public function createDirect(array $data, int $actorId): Batch
    {
        return $this->withCodeLock('B', function () use ($data, $actorId): Batch {
            return DB::transaction(function () use ($data, $actorId): Batch {
                $batch = $this->persist([
                    'source' => Batch::SOURCE_OWN,
                    'supplier_id' => null,
                    'scion_variety_id' => $data['scion_variety_id'] ?? null,
                    'parent_batch_id' => null,
                    'user_id' => $actorId,
                    'total_grafted' => (int) $data['total_grafted'],
                    'seedlings_used' => null,
                    'scion_used' => null,
                    'regraft' => 0,
                    'available_count' => (int) $data['total_grafted'],
                    'grafted_date' => now()->toDateString(),
                    'created_at' => now(),
                    'status' => $data['status'],
                ], 'B');

                ActivityLog::record(
                    null,
                    ActivityLog::TYPE_BATCH_CREATED,
                    "Created batch {$batch->batch_code}.",
                );

                return $batch;
            });
        });
    }

    /**
     * Approve one pending Batch Creation submission and apply its official
     * effect exactly once.
     *
     * Lock order (owner decision): named batch-code lock, submission row,
     * seedling pool rows (ascending id, own nursery only), scion variety rows
     * (ascending id), supplier row (supplier delivery only). No batch rows are
     * locked for code assignment, so this cannot invert with RegraftBatch
     * (which locks its source batch and then the variety).
     */
    public function approveCreation(OperationalSubmission $submission, int $managerId): OperationalSubmission
    {
        $source = (string) ($submission->payload['source'] ?? Batch::SOURCE_OWN);
        $prefix = self::prefixForSource($source);

        return $this->withCodeLock($prefix, function () use ($submission, $managerId, $source): OperationalSubmission {
            return DB::transaction(function () use ($submission, $managerId, $source): OperationalSubmission {
                /** @var OperationalSubmission $locked */
                $locked = OperationalSubmission::query()
                    ->whereKey($submission->submission_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== OperationalSubmission::STATUS_PENDING) {
                    throw new DomainException('This submission has already been decided.');
                }

                if ((int) $locked->submitted_by_id === $managerId) {
                    throw new DomainException('The decider must differ from the submitter.');
                }

                $payload = $locked->payload;

                $batch = $source === Batch::SOURCE_SUPPLIER
                    ? $this->approveSupplierDelivery($locked, $payload)
                    : $this->approveOwnNursery($locked, $payload);

                $this->linkAndLog($locked, $batch, $managerId);
                $locked->approve($managerId);

                return $locked->fresh();
            });
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function approveOwnNursery(OperationalSubmission $submission, array $payload): Batch
    {
        // The seedling pool has no single balance row: lock every seedling-only
        // Stock In row in ascending id order first.
        $pool = StockBalances::lockSeedlingPool();

        $varietyId = (int) ($payload['scion_variety_id'] ?? 0);
        $variety = ScionVariety::lockForScionStock([$varietyId])->get($varietyId);

        if ($variety === null || $variety->isArchived()) {
            throw new ApprovalRevalidationException('The scion variety is no longer active. Resubmit with another variety.');
        }

        $seedlingsUsed = (int) ($payload['seedlings_used'] ?? 0);
        $scionUsed = (int) ($payload['scion_used'] ?? 0);
        $plantsGrafted = (int) ($payload['plants_grafted'] ?? 0);

        if ($seedlingsUsed < 1) {
            $this->fail('seedlings_used', 'Enter how many seedlings were used.');
        }

        if ($scionUsed < 1) {
            $this->fail('scion_used', 'Enter how many scion were used.');
        }

        if ($plantsGrafted < 1) {
            $this->fail('plants_grafted', 'Enter how many plants were grafted.');
        }

        $seedlingsAvailable = StockBalances::seedlingsAvailable(
            (int) $pool->sum('quantity'),
            StockBalances::seedlingsUsed(),
        );

        if ($seedlingsUsed > $seedlingsAvailable) {
            $this->fail('seedlings_used', "Only {$seedlingsAvailable} seedlings are available. Enter {$seedlingsAvailable} or fewer.");
        }

        $scionAvailable = $variety->availableScionQuantity();

        if ($scionUsed > $scionAvailable) {
            $this->fail('scion_used', "Only {$scionAvailable} {$variety->scion_variety_name} scion is available. Enter {$scionAvailable} or fewer.");
        }

        if ($plantsGrafted > min($seedlingsUsed, $scionUsed)) {
            $this->fail('plants_grafted', "Plants grafted can't be more than the seedlings or scion used.");
        }

        return $this->persist([
            'source' => Batch::SOURCE_OWN,
            'supplier_id' => null,
            'scion_variety_id' => $variety->scion_variety_id,
            'parent_batch_id' => null,
            'user_id' => (int) $submission->submitted_by_id,
            'total_grafted' => $plantsGrafted,
            'seedlings_used' => $seedlingsUsed,
            'scion_used' => $scionUsed,
            'regraft' => 0,
            'available_count' => $plantsGrafted,
            'grafted_date' => (string) ($payload['grafted_date'] ?? now()->toDateString()),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ], 'B');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function approveSupplierDelivery(OperationalSubmission $submission, array $payload): Batch
    {
        $varietyId = (int) ($payload['scion_variety_id'] ?? 0);
        $variety = ScionVariety::lockForScionStock([$varietyId])->get($varietyId);

        if ($variety === null || $variety->isArchived()) {
            throw new ApprovalRevalidationException('The scion variety is no longer active. Resubmit with another variety.');
        }

        $supplier = Supplier::query()
            ->lockForUpdate()
            ->find((int) ($payload['supplier_id'] ?? 0));

        if ($supplier === null || $supplier->isArchived()) {
            throw new ApprovalRevalidationException('The supplier is no longer active. Resubmit with another supplier.');
        }

        $plantsDelivered = (int) ($payload['plants_delivered'] ?? 0);

        if ($plantsDelivered < 1) {
            $this->fail('plants_delivered', 'Enter how many plants were delivered.');
        }

        $deliveryDate = $this->parseDeliveryDate((string) ($payload['delivery_date'] ?? ''));

        if ($deliveryDate->gt(now()->startOfDay())) {
            $this->fail('delivery_date', 'The delivery date cannot be in the future.');
        }

        return $this->persist([
            'source' => Batch::SOURCE_SUPPLIER,
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'parent_batch_id' => null,
            'user_id' => (int) $submission->submitted_by_id,
            'total_grafted' => $plantsDelivered,
            'seedlings_used' => null,
            'scion_used' => null,
            'regraft' => 0,
            'available_count' => $plantsDelivered,
            'grafted_date' => $deliveryDate->toDateString(),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ], 'S');
    }

    private function parseDeliveryDate(string $date): Carbon
    {
        try {
            return Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            $this->fail('delivery_date', 'Enter the delivery date.');
        }
    }

    private function linkAndLog(OperationalSubmission $submission, Batch $batch, int $managerId): void
    {
        OperationalSubmissionRecord::create([
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_BATCH,
            'record_id' => $batch->batch_id,
        ]);

        ActivityLog::record(
            $batch->batch_id,
            ActivityLog::TYPE_BATCH_CREATED,
            "Created batch {$batch->batch_code}.",
            (int) $submission->submitted_by_id,
        );

        ActivityLog::record(
            $batch->batch_id,
            ActivityLog::TYPE_OPERATIONAL_SUBMISSION_APPROVED,
            "Approved batch-creation submission #{$submission->submission_id}; official record #{$batch->batch_id} ({$batch->batch_code}).",
            $managerId,
        );
    }

    private function fail(string $field, string $message): never
    {
        throw new ApprovalRevalidationException($message);
    }

    /**
     * The batch-code prefix for a source.
     */
    public static function prefixForSource(string $source): string
    {
        return $source === Batch::SOURCE_SUPPLIER ? 'S' : 'B';
    }

    /**
     * Persist one official batch under the prefix named lock, retrying once on
     * a duplicate `batch_code` (the only remaining race is a manual code).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persist(array $attributes, string $prefix): Batch
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $attributes['batch_code'] = Batch::nextBatchCode($prefix);

            try {
                return Batch::create($attributes);
            } catch (QueryException $exception) {
                if ($attempt === 0 && $this->isDuplicateBatchCode($exception)) {
                    continue;
                }

                throw $exception;
            }
        }

        throw new \RuntimeException('A batch code could not be assigned.');
    }

    private function isDuplicateBatchCode(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            || str_contains($exception->getMessage(), 'batches_batch_code_unique');
    }

    /**
     * Run `$callback` while holding the named MySQL lock for one batch-code
     * prefix. The lock is taken before the transaction and released in
     * `finally`; a timeout is an infrastructure error.
     */
    private function withCodeLock(string $prefix, Closure $callback): mixed
    {
        $name = 'cacaotrack:batch_code:'.$prefix;

        $timeout = (int) config(
            'cacaotrack.operational_submission.batch_code_lock_timeout',
            self::CODE_LOCK_TIMEOUT_SECONDS,
        );

        $acquired = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
            $name,
            $timeout,
        ])->acquired;

        if ((int) $acquired !== 1) {
            throw BatchCodeLockTimeoutException::forPrefix($prefix);
        }

        try {
            return $callback();
        } finally {
            try {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
            } catch (\Throwable) {
                // The connection close also releases the named lock; never mask
                // the original failure with a release error.
            }
        }
    }
}
