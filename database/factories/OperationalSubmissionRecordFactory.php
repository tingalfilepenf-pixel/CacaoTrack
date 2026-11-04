<?php

namespace Database\Factories;

use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalSubmissionRecord>
 */
class OperationalSubmissionRecordFactory extends Factory
{
    protected $model = OperationalSubmissionRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => OperationalSubmission::factory(),
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_MORTALITY,
            'record_id' => fake()->unique()->numberBetween(1, 1_000_000),
        ];
    }

    public function ofType(string $recordType, int $recordId): static
    {
        return $this->state(fn (): array => [
            'record_type' => $recordType,
            'record_id' => $recordId,
        ]);
    }
}
