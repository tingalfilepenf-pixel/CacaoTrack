<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, string $code = 'B001', int $available = 100): Batch
    {
        $variety = Variety::create(['variety_name' => 'Criollo']);
        $scion = Scion::create(['scion_name' => 'ICS-1']);
        $potType = PotType::create(['pot_name' => 'Standard Polybag']);

        return Batch::create([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => $available,
            'viable_count' => $available,
            'available_count' => $available,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => 'active',
        ]);
    }

    private function createInventory(Batch $batch, int $quantity): Inventory
    {
        return Inventory::create([
            'batch_id' => $batch->batch_id,
            'current_quantity' => $quantity,
            'status' => Inventory::statusForQuantity($quantity),
            'last_update' => now(),
        ]);
    }

    private function createSupplier(string $name = 'Supplier A'): Supplier
    {
        return Supplier::create([
            'supplier_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
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

    public function test_stock_in_creates_a_record_and_increases_inventory(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 100);
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 30,
                'date_received' => '2026-09-05',
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_ins', [
            'supplier_id' => $supplier->supplier_id,
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'date_received' => '2026-09-05',
        ]);

        $this->assertSame(130, (int) $inventory->fresh()->current_quantity);
        $this->assertSame(Inventory::STATUS_ACTIVE, $inventory->fresh()->status);
    }

    public function test_stock_in_reactivates_a_depleted_inventory(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 50);
        $inventory = $this->createInventory($batch, 0);
        $supplier = $this->createSupplier();

        $this->assertSame(Inventory::STATUS_DEPLETED, $inventory->status);

        $this->actingAs($user)->post(route('inventory.stock-in.store'), [
            'supplier_id' => $supplier->supplier_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 25,
            'date_received' => '2026-09-05',
        ]);

        $fresh = $inventory->fresh();
        $this->assertSame(25, (int) $fresh->current_quantity);
        $this->assertSame(Inventory::STATUS_ACTIVE, $fresh->status);
    }

    public function test_stock_in_creates_inventory_when_missing(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 80);
        $supplier = $this->createSupplier();

        $this->assertDatabaseMissing('inventories', ['batch_id' => $batch->batch_id]);

        $this->actingAs($user)->post(route('inventory.stock-in.store'), [
            'supplier_id' => $supplier->supplier_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 20,
            'date_received' => '2026-09-05',
        ]);

        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batch->batch_id,
            'current_quantity' => 100,
            'status' => 'active',
        ]);
    }

    public function test_stock_in_requires_valid_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [])
            ->assertSessionHasErrors(['supplier_id', 'batch_id', 'quantity', 'date_received'], null, 'stockin');
    }

    public function test_stock_in_rejects_unknown_supplier_or_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => 9999,
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['supplier_id'], null, 'stockin');

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'batch_id' => 9999,
                'quantity' => 10,
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['batch_id'], null, 'stockin');
    }

    public function test_stock_in_rejects_zero_and_negative_quantities(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $supplier = $this->createSupplier();

        foreach ([0, -5] as $quantity) {
            $this->actingAs($user)
                ->post(route('inventory.stock-in.store'), [
                    'supplier_id' => $supplier->supplier_id,
                    'batch_id' => $batch->batch_id,
                    'quantity' => $quantity,
                    'date_received' => '2026-09-05',
                ])
                ->assertSessionHasErrors(['quantity'], null, 'stockin');
        }

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_rejects_a_future_received_date(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'date_received' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['date_received'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_page_lists_real_records(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B009', 100);
        $supplier = $this->createSupplier('Real Supplier Co');
        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 77,
            'date_received' => '2026-09-04',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('B009')
            ->assertSee('Real Supplier Co')
            ->assertSee('77');
    }

    public function test_stock_in_page_filters_by_supplier_and_search(): void
    {
        $user = User::factory()->create();
        $first = $this->createBatch($user, 'B001', 100);
        $second = $this->createBatch($user, 'B002', 100);
        $supplierA = $this->createSupplier('Alpha Supply');
        $supplierB = $this->createSupplier('Beta Supply');

        $stockInA = StockIn::create(['supplier_id' => $supplierA->supplier_id, 'batch_id' => $first->batch_id, 'user_id' => $user->id, 'quantity' => 10, 'date_received' => '2026-09-01']);
        $stockInB = StockIn::create(['supplier_id' => $supplierB->supplier_id, 'batch_id' => $second->batch_id, 'user_id' => $user->id, 'quantity' => 20, 'date_received' => '2026-09-02']);

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['supplier_id' => $supplierB->supplier_id]))
            ->assertOk()
            ->assertSee($stockInB->reference())
            ->assertDontSee($stockInA->reference());

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['search' => 'B001']))
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

    public function test_supplier_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.suppliers.store'), [])
            ->assertSessionHasErrors(['supplier_name', 'contact_number', 'address'], null, 'supplier');

        $this->assertSame(0, Supplier::count());
    }

    public function test_stock_in_rejects_an_archived_supplier_or_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $supplier = $this->createSupplier();
        $supplier->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $supplier->supplier_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['supplier_id'], null, 'stockin');

        $batch->update(['archived_at' => now()]);
        $activeSupplier = $this->createSupplier('Active Supplier');

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'supplier_id' => $activeSupplier->supplier_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'date_received' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['batch_id'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_archived_supplier_is_not_offered_on_the_stock_in_form(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user);
        $this->createSupplier('Hidden Supplier')->update(['archived_at' => now()]);
        $this->createSupplier('Visible Supplier');

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Visible Supplier')
            ->assertDontSee('Hidden Supplier');
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
