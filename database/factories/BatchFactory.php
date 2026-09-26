<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grafted = $this->faker->numberBetween(50, 400);
        $viable = $this->faker->numberBetween(1, $grafted);

        return [
            'scion_variety_id' => ScionVariety::factory(),
            'user_id' => User::factory(),
            'batch_code' => 'B'.$this->faker->unique()->numerify('####'),
            'total_grafted' => $grafted,
            'viable_count' => $viable,
            'available_count' => $viable,
            'grafted_date' => $this->faker->dateTimeBetween('-6 months', '-1 month')->format('Y-m-d'),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
            'archived_at' => null,
        ];
    }

    /**
     * An archived batch.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['archived_at' => now()]);
    }

    /**
     * A batch with a fixed live available balance (e.g. to model a release).
     */
    public function withAvailable(int $available): static
    {
        return $this->state(fn (array $attributes) => ['available_count' => $available]);
    }
}
