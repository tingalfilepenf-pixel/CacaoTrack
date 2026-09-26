<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\StockOut;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOut>
 *
 * Stock Out is a header; released quantities live on `Stock_Out_Detail` lines.
 */
class StockOutFactory extends Factory
{
    protected $model = StockOut::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'user_id' => User::factory(),
            'total_amount' => null,
            'release_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
