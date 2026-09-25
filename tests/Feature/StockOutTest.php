<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\StockOut;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOutTest extends TestCase
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

    private function createClient(string $name = 'Client A'): Client
    {
        return Client::create([
            'client_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    public function test_guests_are_redirected_from_stock_out_to_login(): void
    {
        $this->get(route('inventory.stock-out'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_stock_out_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-out'))
            ->assertOk()
            ->assertSee('Stock Out')
            ->assertSee('Release History')
            ->assertSee('Total Quantity Released');
    }

    public function test_stock_out_creates_a_record_and_decreases_inventory(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 100);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 30,
                'total_amount' => 1500,
                'release_date' => '2026-09-05',
            ])
            ->assertRedirect(route('inventory.stock-out'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_outs', [
            'client_id' => $client->client_id,
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'total_amount' => '1500.00',
            'release_date' => '2026-09-05',
        ]);

        $this->assertSame(70, (int) $inventory->fresh()->current_quantity);
        $this->assertSame(Inventory::STATUS_ACTIVE, $inventory->fresh()->status);
    }

    public function test_stock_out_depletes_inventory_when_all_stock_is_released(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 50);
        $inventory = $this->createInventory($batch, 50);
        $client = $this->createClient();

        $this->actingAs($user)->post(route('inventory.stock-out.store'), [
            'client_id' => $client->client_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 50,
            'release_date' => '2026-09-05',
        ]);

        $fresh = $inventory->fresh();
        $this->assertSame(0, (int) $fresh->current_quantity);
        $this->assertSame(Inventory::STATUS_DEPLETED, $fresh->status);
    }

    public function test_stock_out_rejects_quantity_greater_than_available_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 100);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 150,
                'release_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
        $this->assertSame(100, (int) $inventory->fresh()->current_quantity);
    }

    public function test_stock_out_creates_inventory_when_missing_and_decreases_it(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 80);
        $client = $this->createClient();

        $this->assertDatabaseMissing('inventories', ['batch_id' => $batch->batch_id]);

        $this->actingAs($user)->post(route('inventory.stock-out.store'), [
            'client_id' => $client->client_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 20,
            'release_date' => '2026-09-05',
        ]);

        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batch->batch_id,
            'current_quantity' => 60,
            'status' => 'active',
        ]);
    }

    public function test_stock_out_requires_valid_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [])
            ->assertSessionHasErrors(['client_id', 'batch_id', 'quantity', 'release_date'], null, 'stockout');
    }

    public function test_stock_out_rejects_unknown_client_or_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => 9999,
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'release_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['client_id'], null, 'stockout');

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'batch_id' => 9999,
                'quantity' => 10,
                'release_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['batch_id'], null, 'stockout');
    }

    public function test_stock_out_rejects_an_archived_client(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->createInventory($batch, 100);
        $client = $this->createClient();
        $client->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'release_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['client_id'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
    }

    public function test_stock_out_rejects_zero_and_negative_quantities(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $client = $this->createClient();

        foreach ([0, -5] as $quantity) {
            $this->actingAs($user)
                ->post(route('inventory.stock-out.store'), [
                    'client_id' => $client->client_id,
                    'batch_id' => $batch->batch_id,
                    'quantity' => $quantity,
                    'release_date' => '2026-09-05',
                ])
                ->assertSessionHasErrors(['quantity'], null, 'stockout');
        }

        $this->assertSame(0, StockOut::count());
    }

    public function test_stock_out_rejects_a_future_release_date(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 10,
                'release_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['release_date'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
    }

    public function test_stock_out_treats_total_amount_as_optional_and_rejects_negative(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $this->createInventory($batch, 100);
        $client = $this->createClient();

        $this->actingAs($user)->post(route('inventory.stock-out.store'), [
            'client_id' => $client->client_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 10,
            'release_date' => '2026-09-05',
        ]);

        $this->assertDatabaseHas('stock_outs', [
            'batch_id' => $batch->batch_id,
            'quantity' => 10,
            'total_amount' => null,
        ]);

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'batch_id' => $batch->batch_id,
                'quantity' => 5,
                'total_amount' => -1,
                'release_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['total_amount'], null, 'stockout');
    }

    public function test_stock_out_page_lists_real_records(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B009', 100);
        $client = $this->createClient('Real Client Co');
        StockOut::create([
            'client_id' => $client->client_id,
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 77,
            'total_amount' => 2310.50,
            'release_date' => '2026-09-04',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-out'))
            ->assertOk()
            ->assertSee('B009')
            ->assertSee('Real Client Co')
            ->assertSee('77')
            ->assertSee('2,310.50');
    }

    public function test_stock_out_page_filters_by_client_and_search(): void
    {
        $user = User::factory()->create();
        $first = $this->createBatch($user, 'B001', 100);
        $second = $this->createBatch($user, 'B002', 100);
        $clientA = $this->createClient('Alpha Client');
        $clientB = $this->createClient('Beta Client');

        $stockOutA = StockOut::create(['client_id' => $clientA->client_id, 'batch_id' => $first->batch_id, 'user_id' => $user->id, 'quantity' => 10, 'release_date' => '2026-09-01']);
        $stockOutB = StockOut::create(['client_id' => $clientB->client_id, 'batch_id' => $second->batch_id, 'user_id' => $user->id, 'quantity' => 20, 'release_date' => '2026-09-02']);

        $this->actingAs($user)
            ->get(route('inventory.stock-out', ['client_id' => $clientB->client_id]))
            ->assertOk()
            ->assertSee($stockOutB->reference())
            ->assertDontSee($stockOutA->reference());

        $this->actingAs($user)
            ->get(route('inventory.stock-out', ['search' => 'B001']))
            ->assertOk()
            ->assertSee($stockOutA->reference())
            ->assertDontSee($stockOutB->reference());
    }

    public function test_stock_out_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-out'))
            ->assertOk()
            ->assertSee('No stock-out transactions found for the current filters.');
    }

    public function test_client_can_be_created_inline(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.clients.store'), [
                'client_name' => 'New Client',
                'contact_number' => '0999-123-4567',
                'address' => 'Somewhere, Philippines',
            ])
            ->assertRedirect(route('inventory.stock-out'))
            ->assertSessionHas('new_client_id');

        $this->assertDatabaseHas('clients', ['client_name' => 'New Client']);
    }

    public function test_client_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.clients.store'), [])
            ->assertSessionHasErrors(['client_name', 'contact_number', 'address'], null, 'client');

        $this->assertSame(0, Client::count());
    }

    public function test_stock_out_reference_is_formatted(): void
    {
        $first = new StockOut;
        $first->stock_out_id = 1;

        $padded = new StockOut;
        $padded->stock_out_id = 42;

        $large = new StockOut;
        $large->stock_out_id = 1234;

        $this->assertSame('SO-001', $first->reference());
        $this->assertSame('SO-042', $padded->reference());
        $this->assertSame('SO-1234', $large->reference());
    }
}
