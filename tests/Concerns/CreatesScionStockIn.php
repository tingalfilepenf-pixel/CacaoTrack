<?php

namespace Tests\Concerns;

use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\User;

/**
 * Shared test setup for the regraft scion-variety rule: a variety qualifies
 * only when an active Stock In receipt recorded it as scion.
 */
trait CreatesScionStockIn
{
    /**
     * Give a scion variety a scion Stock In receipt (one row, no balance effect).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createScionStockIn(ScionVariety $variety, ?User $user = null, array $attributes = []): StockIn
    {
        return StockIn::create(array_merge([
            'supplier_id' => null,
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user?->id ?? User::factory()->create()->id,
            'quantity' => 1000,
            'unit_price' => 10.00,
            'origin' => 'Test nursery',
            'notes' => null,
            'date_received' => now()->subMonths(3)->toDateString(),
        ], $attributes));
    }
}
