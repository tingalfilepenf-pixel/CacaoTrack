<?php

namespace Database\Factories;

use App\Models\ScionReceiving;
use App\Models\ScionVariety;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScionReceiving>
 *
 * A Scion_Receiving is a receiving/cost-tracking log only: it holds NO stock
 * balance on `scion_varieties` and has no freshness/viability column.
 */
class ScionReceivingFactory extends Factory
{
    protected $model = ScionReceiving::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scion_variety_id' => ScionVariety::factory(),
            'supplier_id' => null,
            'quantity' => $this->faker->numberBetween(10, 500),
            'unit_price' => $this->faker->optional()->randomFloat(2, 1, 100),
            'date_received' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'origin' => $this->faker->optional()->randomElement(['Davao', 'Bukidnon', 'Local Nursery']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Tie the receiving to a formal Supplier record (allowed but not required).
     */
    public function fromSupplier(Supplier $supplier): static
    {
        return $this->state(fn (array $attributes) => ['supplier_id' => $supplier->supplier_id]);
    }
}
