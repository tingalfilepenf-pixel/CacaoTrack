<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 *
 * A monthly per-batch snapshot. `status` is frozen by the model's `creating`
 * hook from the batch's live available balance, so it is not set here.
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'inventory_date' => now()->startOfMonth()->toDateString(),
            'total_grafted' => 0,
            'regraft' => 0,
            'mortality' => 0,
            'last_update' => now(),
        ];
    }

    /**
     * Seed the snapshot's frozen counts from the batch's current live values.
     */
    public function forBatch(Batch $batch): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => $batch->batch_id,
            'total_grafted' => (int) $batch->total_grafted,
            'regraft' => $batch->totalRegrafted(),
            'mortality' => $batch->effectiveMortalityQuantity(),
        ]);
    }
}
