<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\OperationalSubmission;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Reject a pending submission. No official operational row is ever written by
 * this action; rejected submissions remain in history.
 */
class RejectOperationalSubmission
{
    public function manual(OperationalSubmission $submission, int $managerId, string $reason): OperationalSubmission
    {
        return $this->decide($submission, $managerId, $reason, false);
    }

    public function automatic(OperationalSubmission $submission, int $managerId, string $safeReason): OperationalSubmission
    {
        return $this->decide($submission, $managerId, $safeReason, true);
    }

    private function decide(
        OperationalSubmission $submission,
        int $managerId,
        string $reason,
        bool $automatic,
    ): OperationalSubmission {
        return DB::transaction(function () use ($submission, $managerId, $reason, $automatic): OperationalSubmission {
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

            $reason = trim($reason);

            if ($automatic) {
                $reason = 'Automatic rejection: approval revalidation failed — '.$reason;
            }

            $locked->reject($managerId, $reason, $automatic);

            ActivityLog::record(
                $locked->subject_batch_id,
                ActivityLog::TYPE_OPERATIONAL_SUBMISSION_REJECTED,
                'Rejected '.(string) $locked->submission_type." submission #{$locked->submission_id}: {$reason}",
                $managerId,
            );

            return $locked->fresh();
        });
    }
}
