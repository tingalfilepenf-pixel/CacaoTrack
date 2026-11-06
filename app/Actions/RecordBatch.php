<?php

namespace App\Actions;

use App\Exceptions\BatchCodeLockTimeoutException;
use App\Models\ActivityLog;
use App\Models\Batch;
use Closure;
use Illuminate\Database\QueryException;
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

        $acquired = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
            $name,
            self::CODE_LOCK_TIMEOUT_SECONDS,
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
