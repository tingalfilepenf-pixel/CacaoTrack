<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Read-only integrity check for the batch quantity invariants.
 *
 * Every value is derived from the same centralized definitions the application
 * uses (RULE 5/6) — it never writes, deletes or repairs anything. For each batch
 * it verifies:
 *
 *   1. effective mortality <= total_grafted - regraft
 *   2. available_count >= 0
 *   3. available_count <= total_grafted - regraft
 *   4. batches.regraft == SUM(regraft_records.quantity WHERE parent_batch_id = batch)
 *
 * Rule 3 should always hold for activity created after the Stock Out surviving
 * cap shipped; it is checked regardless so the command also acts as a detector
 * for legacy/imported rows.
 */
class CacaoHealthCommand extends Command
{
    protected $signature = 'cacao:health';

    protected $description = 'Read-only check of the batch quantity invariants';

    public function handle(): int
    {
        $batches = Batch::query()->orderBy('batch_code')->get();

        if ($batches->isEmpty()) {
            $this->info('Cacao health check passed. No batches to check.');

            return self::SUCCESS;
        }

        $batchIds = $batches->modelKeys();

        $mortalityByBatch = MortalityRecord::effectiveQuantityByBatch($batchIds);

        $regraftRecordsByBatch = RegraftRecord::query()
            ->whereIn('parent_batch_id', $batchIds)
            ->selectRaw('parent_batch_id, COALESCE(SUM(quantity), 0) as total')
            ->groupBy('parent_batch_id')
            ->pluck('total', 'parent_batch_id')
            ->map(fn ($total) => (int) $total);

        $violations = new Collection;

        foreach ($batches as $batch) {
            $survivingPopulation = (int) $batch->total_grafted - (int) $batch->regraft;
            $effectiveMortality = (int) ($mortalityByBatch[$batch->batch_id] ?? 0);
            $recordedRegraft = (int) ($regraftRecordsByBatch[$batch->batch_id] ?? 0);
            $available = (int) $batch->available_count;

            if ($effectiveMortality > $survivingPopulation) {
                $violations->push([
                    $batch->batch_code,
                    'effective mortality <= total_grafted - regraft',
                    $survivingPopulation,
                    $effectiveMortality,
                ]);
            }

            if ($available < 0) {
                $violations->push([
                    $batch->batch_code,
                    'available_count >= 0',
                    0,
                    $available,
                ]);
            }

            if ($available > $survivingPopulation) {
                $violations->push([
                    $batch->batch_code,
                    'available_count <= total_grafted - regraft',
                    $survivingPopulation,
                    $available,
                ]);
            }

            if ((int) $batch->regraft !== $recordedRegraft) {
                $violations->push([
                    $batch->batch_code,
                    'regraft counter == SUM(regraft_records.quantity)',
                    $recordedRegraft,
                    (int) $batch->regraft,
                ]);
            }
        }

        if ($violations->isEmpty()) {
            $this->info("Cacao health check passed. {$batches->count()} batch(es) satisfy all invariants.");

            return self::SUCCESS;
        }

        $this->error("Cacao health check FAILED: {$violations->count()} violation(s) across {$batches->count()} batch(es).");

        $this->table(
            ['Batch', 'Rule', 'Expected', 'Actual'],
            $violations->all(),
        );

        return self::FAILURE;
    }
}
