<?php

namespace Database\Factories;

use App\Models\ScionVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScionVariety>
 */
class ScionVarietyFactory extends Factory
{
    protected $model = ScionVariety::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scion_variety_name' => 'Scion '.$this->faker->unique()->numerify('####'),
            'description' => $this->faker->optional()->sentence(),
            'archived_at' => null,
        ];
    }

    /**
     * An archived scion variety (excluded from new-record selectors).
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['archived_at' => now()]);
    }
}
