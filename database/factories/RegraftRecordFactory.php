<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\RegraftRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegraftRecord>
 *
 * Regraft and Replanting are the same recorded procedure, so this factory
 * covers the single `regraft_records` table.
 */
class RegraftRecordFactory extends Factory
{
    protected $model = RegraftRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'user_id' => User::factory(),
            'quantity' => $this->faker->numberBetween(1, 20),
            'regraft_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'remark' => $this->faker->optional()->sentence(),
        ];
    }
}
