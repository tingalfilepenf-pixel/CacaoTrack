<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MortalityRecord>
 */
class MortalityRecordFactory extends Factory
{
    protected $model = MortalityRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'user_id' => User::factory(),
            'quantity' => $this->faker->numberBetween(1, 25),
            'cause' => $this->faker->randomElement(['Disease', 'Drought', 'Pest', 'Flood']),
            'recorded_at' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
