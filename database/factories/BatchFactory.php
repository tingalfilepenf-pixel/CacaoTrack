<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\Supplier;
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

        return [
            'source' => Batch::SOURCE_OWN,
            'supplier_id' => null,
            'scion_variety_id' => ScionVariety::factory(),
            'parent_batch_id' => null,
            'user_id' => User::factory(),
            'batch_code' => 'B'.$this->faker->unique()->numerify('####'),
            'total_grafted' => $grafted,
            'seedlings_used' => null,
            'scion_used' => null,
            'regraft' => 0,
            'available_count' => $grafted,
            'grafted_date' => $this->faker->dateTimeBetween('-6 months', '-1 month')->format('Y-m-d'),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
            'archived_at' => null,
        ];
    }

    /**
     * A supplier-delivery batch (Phase 4b).
     */
    public function supplierDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => Batch::SOURCE_SUPPLIER,
            'supplier_id' => Supplier::factory(),
        ]);
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
