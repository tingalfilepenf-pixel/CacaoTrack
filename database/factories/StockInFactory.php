<?php

namespace Database\Factories;

use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockIn>
 *
 * Stock In has NO `batch_id` (ERD §3.7): the received quantity is recorded
 * before a Batch is established.
 */
class StockInFactory extends Factory
{
    protected $model = StockIn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'scion_variety_id' => null,
            'user_id' => User::factory(),
            'quantity' => $this->faker->numberBetween(20, 600),
            'unit_price' => null,
            'origin' => $this->faker->randomElement(['Davao', 'Bukidnon', 'North Mindanao', 'Local Nursery']),
            'date_received' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ];
    }
}
