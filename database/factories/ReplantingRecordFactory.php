<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\ReplantingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReplantingRecord>
 *
 * Regraft and Replanting are the same recorded procedure (the separate
 * `regraft_records` table was retracted), so this factory covers both.
 */
class ReplantingRecordFactory extends Factory
{
    protected $model = ReplantingRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'user_id' => User::factory(),
            'quantity' => $this->faker->numberBetween(1, 20),
            'replanting_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'remark' => $this->faker->optional()->sentence(),
        ];
    }
}
