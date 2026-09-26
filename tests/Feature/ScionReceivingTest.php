<?php

namespace Tests\Feature;

use App\Models\ScionReceiving;
use App\Models\ScionVariety;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Part D: Scion_Receiving is a receiving/cost-tracking LOG only.
 *
 * Regression guards for the earlier REJECTED design: it must NOT hold or mutate
 * any scion stock balance, and NO freshness/viability rule is implemented yet.
 */
class ScionReceivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_scion_receiving_belongs_to_scion_variety_and_optional_supplier(): void
    {
        $variety = ScionVariety::factory()->create();
        $supplier = Supplier::factory()->create();

        $unbound = ScionReceiving::factory()->create(['scion_variety_id' => $variety->scion_variety_id]);
        $bound = ScionReceiving::factory()->fromSupplier($supplier)->create(['scion_variety_id' => $variety->scion_variety_id]);

        $this->assertTrue($unbound->scionVariety->is($variety));
        $this->assertNull($unbound->supplier);

        $this->assertTrue($bound->supplier->is($supplier));
        $this->assertTrue($bound->scionVariety->is($variety));
    }

    public function test_rules_cover_the_confirmed_nullable_and_required_fields(): void
    {
        $rules = ScionReceiving::rules();

        $this->assertSame(
            ['scion_variety_id', 'supplier_id', 'quantity', 'unit_price', 'date_received', 'origin', 'notes'],
            array_keys($rules),
        );

        // Required: scion variety, quantity, date received.
        $valid = Validator::make([
            'scion_variety_id' => ScionVariety::factory()->create()->scion_variety_id,
            'quantity' => 100,
            'date_received' => '2026-08-15',
        ], $rules);
        $this->assertFalse($valid->fails());

        // Nullable: supplier_id, unit_price, origin, notes.
        $nullables = Validator::make([
            'scion_variety_id' => ScionVariety::factory()->create()->scion_variety_id,
            'quantity' => 100,
            'date_received' => '2026-08-15',
            'supplier_id' => null,
            'unit_price' => null,
            'origin' => null,
            'notes' => null,
        ], $rules);
        $this->assertFalse($nullables->fails());

        // Missing required fields fail.
        $invalid = Validator::make([], $rules);
        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('scion_variety_id', $invalid->errors()->toArray());
        $this->assertArrayHasKey('quantity', $invalid->errors()->toArray());
        $this->assertArrayHasKey('date_received', $invalid->errors()->toArray());
    }

    public function test_a_future_received_date_is_rejected(): void
    {
        $result = Validator::make([
            'scion_variety_id' => ScionVariety::factory()->create()->scion_variety_id,
            'quantity' => 10,
            'date_received' => now()->addDay()->toDateString(),
        ], ScionReceiving::rules());

        $this->assertTrue($result->fails());
        $this->assertArrayHasKey('date_received', $result->errors()->toArray());
    }

    public function test_no_freshness_or_viability_rule_is_implemented(): void
    {
        // Explicitly deferred pending client confirmation.
        $this->assertArrayNotHasKey('usable_until', ScionReceiving::rules());
        $this->assertFalse(Schema::hasColumn('scion_receivings', 'usable_until'));
        $this->assertFalse(Schema::hasColumn('scion_receivings', 'expires_at'));

        // An old receiving is accepted — no expiry blocking.
        $variety = ScionVariety::factory()->create();
        $old = Validator::make([
            'scion_variety_id' => $variety->scion_variety_id,
            'quantity' => 10,
            'date_received' => '2020-01-01',
        ], ScionReceiving::rules());

        $this->assertFalse($old->fails());
    }

    public function test_scion_receiving_has_no_stock_balance_field(): void
    {
        $columns = Schema::getColumnListing('scion_receivings');

        foreach ($columns as $column) {
            $this->assertDoesNotMatchRegularExpression(
                '/balance|stock|available|usable|expir|viab/i',
                $column,
                "Unexpected balance/freshness column [{$column}] on scion_receivings.",
            );
        }
    }

    public function test_scion_variety_has_no_stock_balance_field(): void
    {
        $columns = Schema::getColumnListing('scion_varieties');

        foreach ($columns as $column) {
            $this->assertDoesNotMatchRegularExpression(
                '/balance|quantity|stock|available/i',
                $column,
                "Unexpected balance column [{$column}] on scion_varieties.",
            );
        }

        $this->assertFalse(Schema::hasColumn('scion_varieties', 'quantity'));
        $this->assertFalse(Schema::hasColumn('scion_varieties', 'scion_quantity'));
    }

    public function test_recording_a_receiving_does_not_mutate_any_scion_variety_balance(): void
    {
        $variety = ScionVariety::factory()->create();
        $before = $variety->getAttributes();

        ScionReceiving::factory()->create([
            'scion_variety_id' => $variety->scion_variety_id,
            'quantity' => 500,
        ]);

        // The receiving is a log entry only: nothing on the scion variety changes.
        $this->assertEquals($before, $variety->fresh()->getAttributes());
    }
}
