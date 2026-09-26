<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOutDetail>
 *
 * `scion_variety_id` is deliberately not set here: the model's `saving` hook
 * derives it from the referenced batch (locked business rule 4).
 */
class StockOutDetailFactory extends Factory
{
    protected $model = StockOutDetail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_out_id' => StockOut::factory(),
            'batch_id' => Batch::factory(),
            'quantity' => $this->faker->numberBetween(1, 20),
            'unit' => StockOutDetail::UNITS[0],
            'unit_price' => null,
            'amount' => null,
        ];
    }
}
