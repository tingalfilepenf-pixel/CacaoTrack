<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Part D: validation rules for the current schema — Stock Out quantity
 * <= available_count, Stock In accepted without a viability count, and
 * required/nullable fields.
 */
class StockValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_rules_accept_a_plain_seedling_record(): void
    {
        $supplier = Supplier::factory()->create();

        $valid = Validator::make([
            'supplier_id' => $supplier->supplier_id,
            'quantity' => 100,
            'origin' => 'Davao',
            'date_received' => '2026-09-01',
        ], StockIn::rules());

        $this->assertFalse($valid->fails());
    }

    public function test_stock_out_detail_rules_require_a_valid_unit_and_positive_quantity(): void
    {
        $batch = Batch::factory()->create();
        $header = StockOut::factory()->create();

        $rules = StockOutDetail::rules();
        $this->assertNotContains('scion_variety_id', array_keys($rules)); // derived server-side

        $valid = Validator::make([
            'stock_out_id' => $header->stock_out_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 5,
            'unit' => 'seedlings',
            'unit_price' => null,
        ], $rules);
        $this->assertFalse($valid->fails());

        $badUnit = Validator::make([
            'stock_out_id' => $header->stock_out_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 5,
            'unit' => 'bogus',
        ], $rules);
        $this->assertTrue($badUnit->fails());
        $this->assertArrayHasKey('unit', $badUnit->errors()->toArray());

        $badQuantity = Validator::make([
            'stock_out_id' => $header->stock_out_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 0,
            'unit' => 'seedlings',
        ], $rules);
        $this->assertTrue($badQuantity->fails());
        $this->assertArrayHasKey('quantity', $badQuantity->errors()->toArray());
    }

    public function test_batch_rules_require_total_grafted_and_scion_variety_is_nullable(): void
    {
        $valid = Validator::make([
            'batch_code' => 'B001',
            'scion_variety_id' => null,
            'total_grafted' => 100,
            'grafted_date' => '2026-07-15',
            'status' => Batch::STATUS_ACTIVE,
        ], Batch::rules());
        $this->assertFalse($valid->fails());

        $invalid = Validator::make([
            'batch_code' => 'B001',
            'scion_variety_id' => null,
            'total_grafted' => 0,
            'grafted_date' => '2026-07-15',
            'status' => Batch::STATUS_ACTIVE,
        ], Batch::rules());
        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('total_grafted', $invalid->errors()->toArray());
    }

    public function test_stock_out_availability_is_checked_against_available_count(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $variety = ScionVariety::factory()->create();

        $batch = Batch::factory()->create([
            'scion_variety_id' => $variety->scion_variety_id,
            'total_grafted' => 100,
            'available_count' => 40,
        ]);

        // 41 > available_count 40 -> rejected, nothing written.
        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'release_date' => '2026-09-05',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 41, 'unit' => 'seedlings'],
                ],
            ])
            ->assertSessionHasErrors(['details.0.quantity'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
        $this->assertSame(40, (int) $batch->fresh()->available_count);

        // Exactly available_count is accepted and decrements the balance.
        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'release_date' => '2026-09-05',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 40, 'unit' => 'seedlings'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, (int) $batch->fresh()->available_count);
        $this->assertSame(1, StockOut::count());
    }
}
