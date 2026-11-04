<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalSubmission>
 */
class OperationalSubmissionFactory extends Factory
{
    protected $model = OperationalSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_type' => OperationalSubmission::TYPE_MORTALITY,
            'status' => OperationalSubmission::STATUS_PENDING,
            'payload' => $this->payloadFor(OperationalSubmission::TYPE_MORTALITY),
            'submitted_by_id' => User::factory(),
            'submitted_at' => now()->subMinute(),
            'decided_by_id' => null,
            'decided_at' => null,
            'decision_reason' => null,
            'decision_automatic' => false,
            'resubmission_of_id' => null,
            'subject_batch_id' => null,
        ];
    }

    public function ofType(string $type, array $payload = []): static
    {
        return $this->state(fn (): array => [
            'submission_type' => $type,
            'payload' => $payload === [] ? $this->payloadFor($type) : $payload,
        ]);
    }

    public function forBatch(Batch|int $batch): static
    {
        return $this->state(fn (): array => [
            'subject_batch_id' => $batch instanceof Batch ? $batch->batch_id : $batch,
        ]);
    }

    public function resubmissionOf(OperationalSubmission $previous): static
    {
        return $this->state(fn (): array => [
            'submission_type' => $previous->submission_type,
            'submitted_by_id' => $previous->submitted_by_id,
            'resubmission_of_id' => $previous->submission_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $type): array
    {
        if ($type === OperationalSubmission::TYPE_STOCK_OUT) {
            return [
                'client_id' => 1,
                'release_date' => now()->toDateString(),
                'details' => [
                    ['batch_id' => 1, 'quantity' => 1, 'unit' => 'pots', 'unit_price' => null],
                ],
            ];
        }

        return [
            'batch_id' => 1,
            'quantity' => 1,
            'cause' => 'Disease',
            'recorded_at' => now()->toDateString(),
        ];
    }
}
