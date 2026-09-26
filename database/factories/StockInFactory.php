<?php

namespace Database\Factories;

use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockIn>
 *
 * Stock In has NO `batch_id` (ERD §3.7): the five-day viability recount
 * happens before a Batch is established.
 */
class StockInFactory extends Factory
{
    protected $model = StockIn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(20, 600);

        return [
            'supplier_id' => Supplier::factory(),
            'user_id' => User::factory(),
            'quantity' => $quantity,
            'viable' => $this->faker->numberBetween(0, $quantity),
            'origin' => $this->faker->randomElement(['Davao', 'Bukidnon', 'North Mindanao', 'Local Nursery']),
            'date_received' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ];
    }

    /**
     * The recounted viable subset exactly equals the received quantity.
     */
    public function fullyViable(): static
    {
        return $this->state(fn (array $attributes) => ['viable' => $attributes['quantity']]);
    }
}
