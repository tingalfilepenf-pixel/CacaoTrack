<?php

namespace Tests\Feature;

use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_identity_and_timing_fields_are_immutable(): void
    {
        $otherUser = User::factory()->create();

        $changes = [
            'payload' => ['quantity' => 99],
            'submission_type' => OperationalSubmission::TYPE_STOCK_OUT,
            'submitted_by_id' => $otherUser->id,
            'submitted_at' => now()->addDay(),
            'resubmission_of_id' => 999999,
        ];

        foreach ($changes as $field => $value) {
            $submission = $this->makeSubmission();
            $submission->{$field} = $value;

            try {
                $submission->save();
                $this->fail("Expected [{$field}] to be immutable.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame(
                OperationalSubmission::STATUS_PENDING,
                $submission->fresh()->status
            );
        }
    }

    public function test_a_submission_cannot_be_deleted(): void
    {
        $submission = $this->makeSubmission();

        $this->expectException(DomainException::class);

        $submission->delete();
    }

    public function test_a_pending_submission_can_be_approved_once(): void
    {
        $submission = $this->makeSubmission();
        $decider = User::factory()->create();

        $result = $submission->approve($decider->id, 'Verified against the batch record.');

        $this->assertSame($submission, $result);

        $submission->refresh();

        $this->assertSame(OperationalSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame($decider->id, $submission->decided_by_id);
        $this->assertNotNull($submission->decided_at);
        $this->assertSame('Verified against the batch record.', $submission->decision_reason);
        $this->assertFalse($submission->decision_automatic);
    }

    public function test_a_rejection_requires_a_written_reason(): void
    {
        foreach (['', '   '] as $reason) {
            $submission = $this->makeSubmission();
            $decider = User::factory()->create();

            try {
                $submission->reject($decider->id, $reason);
                $this->fail('Expected a blank rejection reason to be refused.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame(
                OperationalSubmission::STATUS_PENDING,
                $submission->fresh()->status
            );
        }
    }

    public function test_manager_and_automatic_rejections_are_distinguished(): void
    {
        $decider = User::factory()->create();

        $manual = $this->makeSubmission();
        $manual->reject($decider->id, 'Quantity exceeds the recorded mortality.');
        $manual->refresh();

        $this->assertSame(OperationalSubmission::STATUS_REJECTED, $manual->status);
        $this->assertFalse($manual->decision_automatic);
        $this->assertSame('Quantity exceeds the recorded mortality.', $manual->decision_reason);

        $automatic = $this->makeSubmission();
        $automatic->reject($decider->id, 'Automatic rejection: batch revalidation failed (archived).', true);
        $automatic->refresh();

        $this->assertSame(OperationalSubmission::STATUS_REJECTED, $automatic->status);
        $this->assertTrue($automatic->decision_automatic);
        $this->assertStringContainsString('revalidation failed', $automatic->decision_reason);
    }

    public function test_a_submission_cannot_be_decided_by_its_submitter(): void
    {
        $submitter = User::factory()->create();
        $submission = $this->makeSubmission($submitter);

        try {
            $submission->approve($submitter->id);
            $this->fail('Expected self-approval to be refused.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(OperationalSubmission::STATUS_PENDING, $submission->fresh()->status);
    }

    public function test_a_decided_submission_cannot_be_decided_again(): void
    {
        $submission = $this->makeSubmission();
        $decider = User::factory()->create();
        $otherDecider = User::factory()->create();

        $submission->approve($decider->id);

        try {
            $submission->reject($otherDecider->id, 'A second decision is not allowed.');
            $this->fail('Expected a second decision to be refused.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(OperationalSubmission::STATUS_APPROVED, $submission->fresh()->status);
        $this->assertSame($decider->id, $submission->fresh()->decided_by_id);
    }

    public function test_a_stale_second_decision_cannot_succeed(): void
    {
        $submission = $this->makeSubmission();
        $decider = User::factory()->create();
        $otherDecider = User::factory()->create();

        $first = OperationalSubmission::query()->findOrFail($submission->submission_id);
        $second = OperationalSubmission::query()->findOrFail($submission->submission_id);

        $first->approve($decider->id);

        try {
            $second->reject($otherDecider->id, 'Stale instance must not decide.');
            $this->fail('Expected the stale decision to be refused.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $fresh = $submission->fresh();

        $this->assertSame(OperationalSubmission::STATUS_APPROVED, $fresh->status);
        $this->assertSame($decider->id, $fresh->decided_by_id);
    }

    public function test_decision_metadata_cannot_be_edited_without_a_decision(): void
    {
        $submission = $this->makeSubmission();
        $submission->decision_reason = 'Direct edit attempt.';

        try {
            $submission->save();
            $this->fail('Expected decision metadata to require a decision transition.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(OperationalSubmission::STATUS_PENDING, $submission->fresh()->status);
    }

    public function test_resubmission_requires_a_rejected_same_type_same_submitter(): void
    {
        $submitter = User::factory()->create();
        $decider = User::factory()->create();

        $pending = $this->makeSubmission($submitter);

        try {
            OperationalSubmission::factory()->resubmissionOf($pending)->create();
            $this->fail('Expected a pending submission to be non-resubmittable.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $approved = $this->makeSubmission($submitter);
        $approved->approve($decider->id);

        try {
            OperationalSubmission::factory()->resubmissionOf($approved)->create();
            $this->fail('Expected an approved submission to be non-resubmittable.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $rejected = $this->rejectedSubmission($submitter);

        try {
            OperationalSubmission::factory()->resubmissionOf($rejected)->create([
                'submission_type' => OperationalSubmission::TYPE_STOCK_OUT,
            ]);
            $this->fail('Expected a changed submission type to be refused.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        try {
            OperationalSubmission::factory()->resubmissionOf($rejected)->create([
                'submitted_by_id' => User::factory()->create()->id,
            ]);
            $this->fail('Expected a different submitter to be refused.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_resubmission_chain_is_allowed_and_original_stays_unchanged(): void
    {
        $submitter = User::factory()->create();
        $original = $this->rejectedSubmission($submitter);
        $originalAttributes = $original->getAttributes();

        $second = OperationalSubmission::factory()->resubmissionOf($original)->create();
        $secondDecider = User::factory()->create();
        $second->reject($secondDecider->id, 'Second rejection.');

        $third = OperationalSubmission::factory()->resubmissionOf($second->refresh())->create();

        $this->assertSame($original->submission_id, $second->resubmission_of_id);
        $this->assertSame($second->submission_id, $third->resubmission_of_id);
        $this->assertSame($original->submission_id, $third->resubmissionOf->resubmission_of_id);
        $this->assertSame($original->submission_type, $third->submission_type);
        $this->assertSame((int) $original->submitted_by_id, (int) $third->submitted_by_id);
        $this->assertSame($originalAttributes, $original->fresh()->getAttributes());
    }

    public function test_link_rows_are_append_only(): void
    {
        $record = OperationalSubmissionRecord::factory()->create();
        $originalRecordId = $record->record_id;

        try {
            $record->record_id = $originalRecordId + 1;
            $record->save();
            $this->fail('Expected link rows to be immutable.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($originalRecordId, $record->fresh()->record_id);

        $this->expectException(DomainException::class);

        $record->delete();
    }

    private function makeSubmission(?User $submitter = null): OperationalSubmission
    {
        $submitter ??= User::factory()->create();

        return OperationalSubmission::factory()
            ->create(['submitted_by_id' => $submitter->id]);
    }

    private function rejectedSubmission(?User $submitter = null): OperationalSubmission
    {
        $submission = $this->makeSubmission($submitter);
        $decider = User::factory()->create();
        $submission->reject($decider->id, 'Rejected for test.');

        return $submission->refresh();
    }
}
