<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approve one pending submission and apply its official effect exactly once.
 *
 * The approved official record is attributed to the submitter; the Manager's
 * decision lives on the submission and in its own Activity Log entry.
 */
class ApproveOperationalSubmission
{
    public function __construct(
        private readonly RecordMortality $mortality,
        private readonly RecordStockOut $stockOuts,
    ) {}

    public function execute(OperationalSubmission $submission, int $managerId): OperationalSubmission
    {
        return DB::transaction(function () use ($submission, $managerId): OperationalSubmission {
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

            if ($locked->submission_type === OperationalSubmission::TYPE_MORTALITY) {
                $this->approveMortality($locked, $payload, $managerId);
            } else {
                $this->approveStockOut($locked, $payload, $managerId);
            }

            $locked->approve($managerId);

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function approveMortality(OperationalSubmission $submission, array $payload, int $managerId): void
    {
        $batch = Batch::query()->lockForUpdate()->findOrFail($payload['batch_id']);

        if (! $batch->isSubmissionEligible()) {
            throw ValidationException::withMessages([
                'batch_id' => "Batch {$batch->batch_code} is no longer active at approval.",
            ])->errorBag('submission_approval');
        }

        $record = $this->mortality->execute($payload, (int) $submission->submitted_by_id);

        OperationalSubmissionRecord::create([
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_MORTALITY,
            'record_id' => $record->mortality_id,
        ]);

        ActivityLog::record(
            $batch->batch_id,
            ActivityLog::TYPE_OPERATIONAL_SUBMISSION_APPROVED,
            "Approved mortality submission #{$submission->submission_id} for {$batch->batch_code}; official record #{$record->mortality_id}.",
            $managerId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function approveStockOut(OperationalSubmission $submission, array $payload, int $managerId): void
    {
        $client = Client::query()->lockForUpdate()->findOrFail($payload['client_id']);

        if ($client->isArchived()) {
            throw ValidationException::withMessages([
                'client_id' => "Client {$client->client_name} is archived at approval.",
            ])->errorBag('submission_approval');
        }

        $batchIds = collect($payload['details'] ?? [])
            ->pluck('batch_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $batches = Batch::query()
            ->whereIn('batch_id', $batchIds)
            ->orderBy('batch_id')
            ->lockForUpdate()
            ->get();

        if ($batches->count() !== $batchIds->count()) {
            throw ValidationException::withMessages([
                'details' => 'One or more batches in this submission no longer exist.',
            ])->errorBag('submission_approval');
        }

        foreach ($batches as $batch) {
            if (! $batch->isSubmissionEligible()) {
                throw ValidationException::withMessages([
                    'details' => "Batch {$batch->batch_code} is no longer active at approval.",
                ])->errorBag('submission_approval');
            }
        }

        $header = $this->stockOuts->execute($payload, (int) $submission->submitted_by_id);

        OperationalSubmissionRecord::create([
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT,
            'record_id' => $header->stock_out_id,
        ]);

        foreach ($header->details()->get() as $detail) {
            OperationalSubmissionRecord::create([
                'submission_id' => $submission->submission_id,
                'record_type' => OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT_DETAIL,
                'record_id' => $detail->stock_out_detail_id,
            ]);
        }

        ActivityLog::record(
            $batches->count() === 1 ? $batches->first()->batch_id : null,
            ActivityLog::TYPE_OPERATIONAL_SUBMISSION_APPROVED,
            "Approved stock-out submission #{$submission->submission_id}; official record #{$header->stock_out_id}.",
            $managerId,
        );
    }
}
