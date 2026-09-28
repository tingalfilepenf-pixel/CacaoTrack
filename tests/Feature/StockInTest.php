<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stock In on the client-approved model (ERD §3.7): it records the received
 * quantity immediately (no five-day viability count), has NO `batch_id` and no
 * inventory side effect. Since the client-confirmed merge of Scion_Receiving
 * into Stock_In, a single form/list handles both seedling-only rows and
 * scion-inclusive rows (identified by a non-null `scion_variety_id`).
 */
class StockInTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplier(string $name = 'Supplier A'): Supplier
    {
        return Supplier::create([
            'supplier_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    private function createScionVariety(string $name = 'Criollo'): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => $name]);
    }

    public function test_guests_are_redirected_from_stock_in_to_login(): void
    {
        $this->get(route('inventory.stock-in'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_stock_in_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Stock In')
            ->assertSee('Receiving History')
            ->assertSee('Total Quantity Received');
    }

    public function test_stock_in_creates_a_record_without_touching_inventory(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 30,
                'origin' => 'Davao del Sur',
                'date_received' => '2026-09-05',
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_ins', [
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'origin' => 'Davao del Sur',
            'date_received' => '2026-09-05',
        ]);

        // Stock In is a standalone receiving record; it never mutates a batch.
        $this->assertDatabaseCount('inventories', 0);

        // A pure-seedling submission never sets the optional scion fields.
        $this->assertNull(StockIn::first()->scion_variety_id);
        $this->assertNull(StockIn::first()->unit_price);
    }

    public function test_stock_in_saves_without_a_viable_column(): void
    {
        // The approved Stock In has no separate seedling viability count.
        $this->assertFalse(Schema::hasColumn('stock_ins', 'viable'));

        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 45,
                'origin' => 'Davao',
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(45, (int) StockIn::first()->quantity);
    }

    public function test_scion_stock_in_persists_scion_fields(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety('Criollo');

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'scion_variety_id' => $variety->scion_variety_id,
                'quantity' => 120,
                'unit_price' => 12.50,
                'origin' => 'Davao del Sur',
                'notes' => 'Premium bundle.',
                'date_received' => '2026-09-05',
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success', 'Scion receiving recorded successfully.');

        $stockIn = StockIn::first();

        $this->assertNotNull($stockIn);
        $this->assertSame($variety->scion_variety_id, (int) $stockIn->scion_variety_id);
        $this->assertSame('12.50', $stockIn->unit_price);
        $this->assertSame(120, (int) $stockIn->quantity);
        $this->assertTrue($stockIn->isScionEntry());
        $this->assertTrue($stockIn->scionVariety->is($variety));
    }

    public function test_scion_variety_and_unit_price_must_be_provided_together(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        // Scion variety without a unit price.
        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'scion_variety_id' => $variety->scion_variety_id,
                'quantity' => 10,
                'origin' => 'Davao',
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['unit_price'], null, 'stockin');

        // Unit price without a scion variety.
        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 10,
                'unit_price' => 5.00,
                'origin' => 'Davao',
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['scion_variety_id'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_list_shows_scion_variety_for_scion_rows_and_dash_for_seedlings(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety('Trinitario');

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 50,
            'origin' => 'Seedling Origin',
            'date_received' => '2026-09-01',
        ]);

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'quantity' => 80,
            'unit_price' => 12.50,
            'origin' => 'Scion Origin',
            'date_received' => '2026-09-02',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Trinitario')
            ->assertSee('Scion');
    }

    public function test_stock_in_seedling_total_excludes_scion_rows(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 50,
            'origin' => 'Seedling Origin',
            'date_received' => '2026-09-01',
        ]);

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'quantity' => 80,
            'unit_price' => 12.50,
            'origin' => 'Scion Origin',
            'date_received' => '2026-09-02',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertViewHas('totalReceived', 130)
            ->assertViewHas('totalReceivedSeedlings', 50);
    }

    public function test_stock_in_schema_carries_optional_scion_fields(): void
    {
        // The merged scion columns are nullable so a pure-seedling row is
        // unchanged; presence of scion_variety_id is the only "type" signal.
        $this->assertTrue(Schema::hasColumn('stock_ins', 'scion_variety_id'));
        $this->assertTrue(Schema::hasColumn('stock_ins', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('stock_ins', 'entry_type'));
    }

    public function test_stock_in_requires_valid_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [])
            ->assertSessionHasErrors(
                ['supplier_id', 'quantity', 'origin', 'date_received'],
                null,
                'stockin',
            );
    }

    public function test_stock_in_rejects_unknown_or_archived_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => 9999,
                'quantity' => 10,
                'origin' => 'Davao',
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['supplier_id'], null, 'stockin');

        $supplier->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 10,
                'origin' => 'Davao',
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['supplier_id'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_rejects_zero_and_negative_quantities(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        foreach ([0, -5] as $quantity) {
            $this->actingAs($user)
                ->post(route('inventory.stock-in.store'), [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => $quantity,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ])
                ->assertSessionHasErrors(['quantity'], null, 'stockin');
        }

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_rejects_a_future_received_date(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 10,
                'origin' => 'Davao',
                'date_received' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['date_received'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_page_lists_real_records(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier('Real Supplier Co');
        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 77,
            'origin' => 'Test Origin',
            'date_received' => '2026-09-04',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Real Supplier Co')
            ->assertSee('77');
    }

    public function test_stock_in_page_filters_by_supplier_and_search(): void
    {
        $user = User::factory()->create();
        $supplierA = $this->createSupplier('Alpha Supply');
        $supplierB = $this->createSupplier('Beta Supply');

        $stockInA = StockIn::create(['supplier_id' => $supplierA->supplier_id, 'user_id' => $user->id, 'quantity' => 10, 'origin' => 'Alpha Origin', 'date_received' => '2026-09-01']);
        $stockInB = StockIn::create(['supplier_id' => $supplierB->supplier_id, 'user_id' => $user->id, 'quantity' => 20, 'origin' => 'Beta Origin', 'date_received' => '2026-09-02']);

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['supplier_id' => $supplierB->supplier_id]))
            ->assertOk()
            ->assertSee($stockInB->reference())
            ->assertDontSee($stockInA->reference());

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['search' => 'Alpha Supply']))
            ->assertOk()
            ->assertSee($stockInA->reference())
            ->assertDontSee($stockInB->reference());
    }

    public function test_stock_in_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('No stock-in transactions found for the current filters.');
    }

    public function test_supplier_can_be_created_inline(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.suppliers.store'), [
                'supplier_name' => 'New Supplier',
                'contact_number' => '0999-123-4567',
                'address' => 'Somewhere, Philippines',
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('new_supplier_id');

        $this->assertDatabaseHas('suppliers', ['supplier_name' => 'New Supplier']);
    }

    public function test_archived_supplier_is_not_offered_on_the_stock_in_form(): void
    {
        $user = User::factory()->create();
        $this->createSupplier('Hidden Supplier')->update(['archived_at' => now()]);
        $this->createSupplier('Visible Supplier');

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Visible Supplier')
            ->assertDontSee('Hidden Supplier');
    }

    public function test_stock_in_does_not_reference_a_batch(): void
    {
        // Regression guard: the removed `stock_ins.batch_id` must not reappear.
        $this->assertFalse(
            Schema::hasColumn('stock_ins', 'batch_id'),
        );
    }

    public function test_stock_in_reference_is_formatted(): void
    {
        $first = new StockIn;
        $first->stock_in_id = 1;

        $padded = new StockIn;
        $padded->stock_in_id = 42;

        $large = new StockIn;
        $large->stock_in_id = 1234;

        $this->assertSame('SI-001', $first->reference());
        $this->assertSame('SI-042', $padded->reference());
        $this->assertSame('SI-1234', $large->reference());
    }
}
