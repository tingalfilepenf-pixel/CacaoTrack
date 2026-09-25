<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, string $code = 'B001', int $available = 100, string $varietyName = 'Criollo'): Batch
    {
        $variety = Variety::create(['variety_name' => $varietyName]);
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

    public function test_guests_are_redirected_from_inventory_to_login(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_inventory_page(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 120);
        $this->createInventory($batch, 120);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Inventory Management')
            ->assertSee('Current Inventory by Batch')
            ->assertSee('B001')
            ->assertSee('Total Available Inventory');
    }

    public function test_inventory_page_shows_real_database_values(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B007', 250);
        $this->createInventory($batch, 198);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('B007')
            ->assertSee('198')
            ->assertSee('250');
    }

    public function test_inventory_page_filters_by_batch_code(): void
    {
        $user = User::factory()->create();
        $first = $this->createBatch($user, 'B001', 100);
        $second = $this->createBatch($user, 'B002', 100, 'Trinitario');
        $this->createInventory($first, 100);
        $this->createInventory($second, 100);

        $this->actingAs($user)
            ->get(route('inventory.index', ['search' => 'B002']))
            ->assertOk()
            ->assertSee('B002')
            ->assertDontSee('B001');
    }

    public function test_inventory_page_filters_by_status(): void
    {
        $user = User::factory()->create();
        $active = $this->createBatch($user, 'B001', 100);
        $depleted = $this->createBatch($user, 'B002', 100, 'Trinitario');
        $this->createInventory($active, 40);
        $this->createInventory($depleted, 0);

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => 'depleted']))
            ->assertOk()
            ->assertSee('B002')
            ->assertDontSee('B001');
    }

    public function test_inventory_page_filters_by_variety(): void
    {
        $user = User::factory()->create();
        $criollo = $this->createBatch($user, 'B001', 100, 'Criollo');
        $forastero = $this->createBatch($user, 'B002', 100, 'Forastero');
        $this->createInventory($criollo, 100);
        $this->createInventory($forastero, 100);

        $varietyId = $forastero->variety_id;

        $this->actingAs($user)
            ->get(route('inventory.index', ['variety_id' => $varietyId]))
            ->assertOk()
            ->assertSee('B002')
            ->assertDontSee('B001');
    }

    public function test_inventory_page_rejects_invalid_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.index', ['variety_id' => 999, 'status' => 'bogus']))
            ->assertSessionHasErrors(['variety_id', 'status']);
    }

    public function test_inventory_page_shows_empty_state_when_no_records(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('No inventory records found for the current filters.');
    }

    public function test_inventory_cannot_have_duplicate_batch_rows(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->createInventory($batch, 50);

        $this->expectException(QueryException::class);

        $this->createInventory($batch, 50);
    }

    public function test_inventory_status_is_derived_from_quantity(): void
    {
        $this->assertSame(Inventory::STATUS_ACTIVE, Inventory::statusForQuantity(10));
        $this->assertSame(Inventory::STATUS_DEPLETED, Inventory::statusForQuantity(0));

        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 75);

        $inventory = Inventory::syncFromBatch($batch, true);

        $this->assertSame(75, (int) $inventory->current_quantity);
        $this->assertSame(Inventory::STATUS_ACTIVE, $inventory->status);
        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batch->batch_id,
            'current_quantity' => 75,
            'status' => 'active',
        ]);
    }

    public function test_total_active_inventory_sums_only_active_records(): void
    {
        $user = User::factory()->create();
        $active = $this->createBatch($user, 'B001', 100);
        $depleted = $this->createBatch($user, 'B002', 100, 'Trinitario');
        $this->createInventory($active, 30);
        $this->createInventory($depleted, 0);

        $this->assertSame(30, Inventory::totalActiveQuantity());
    }

    public function test_batch_exposes_inventory_relationship(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $inventory = $this->createInventory($batch, 60);

        $this->assertTrue($batch->inventory->is($inventory));
        $this->assertSame($batch->batch_id, $inventory->batch->batch_id);
    }
}
