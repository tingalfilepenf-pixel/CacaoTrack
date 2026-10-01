<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inventory as monthly per-batch snapshots (revised ERD §3.12): many rows per
 * batch, `status` frozen at creation from the live `available_count`.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(
        User $user,
        string $code = 'B001',
        int $totalGrafted = 100,
        int $available = 100,
        string $varietyName = 'Criollo',
    ): Batch {
        $variety = ScionVariety::create(['scion_variety_name' => $varietyName]);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => $totalGrafted,
            'regraft' => 0,
            'available_count' => $available,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function createSnapshot(Batch $batch, int $available, string $date = '2026-09-01'): Inventory
    {
        return Inventory::create([
            'batch_id' => $batch->batch_id,
            'inventory_date' => $date,
            'total_grafted' => (int) $batch->total_grafted,
            'regraft' => $batch->totalRegrafted(),
            'mortality' => $batch->effectiveMortalityQuantity(),
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
        $batch = $this->createBatch($user, 'B001', 120, 120);
        $this->createSnapshot($batch, 120);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Record Monthly Inventory')
            ->assertSee('Total Grafted')
            ->assertSee("Counts each plant once; a regrafted plant's quantity is not added again under its new sub-batch.", false)
            ->assertSee('B001');
    }

    public function test_inventory_page_shows_real_database_values(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B007', 250, 198);
        $this->createSnapshot($batch, 198);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('B007')
            ->assertSee('Monthly Inventory Snapshots')
            ->assertViewHas('inventories', fn ($inventories) => $inventories->total() === 1
                && $inventories->first()->batch->batch_code === 'B007'
                && (int) $inventories->first()->total_grafted === 250);
    }

    public function test_inventory_page_filters_by_batch_code(): void
    {
        $user = User::factory()->create();
        $first = $this->createBatch($user, 'B001', 100, 100);
        $second = $this->createBatch($user, 'B002', 100, 100, 'Trinitario');
        $this->createSnapshot($first, 100);
        $this->createSnapshot($second, 100);

        // Assert the filtered dataset, not raw HTML: the filter dropdown also
        // lists every active batch code.
        $this->actingAs($user)
            ->get(route('inventory.index', ['search' => 'B002']))
            ->assertOk()
            ->assertViewHas('inventories', fn ($inventories) => $inventories->total() === 1
                && $inventories->first()->batch->batch_code === 'B002');
    }

    public function test_inventory_page_filters_by_status(): void
    {
        $user = User::factory()->create();
        $active = $this->createBatch($user, 'B001', 100, 40);
        $depleted = $this->createBatch($user, 'B002', 100, 0, 'Trinitario');
        $this->createSnapshot($active, 40);
        $this->createSnapshot($depleted, 0);

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => Inventory::STATUS_DEPLETED]))
            ->assertOk()
            ->assertViewHas('inventories', fn ($inventories) => $inventories->total() === 1
                && $inventories->first()->batch->batch_code === 'B002'
                && $inventories->first()->status === Inventory::STATUS_DEPLETED);
    }

    public function test_inventory_page_filters_by_scion_variety(): void
    {
        $user = User::factory()->create();
        $criollo = $this->createBatch($user, 'B001', 100, 100, 'Criollo');
        $forastero = $this->createBatch($user, 'B002', 100, 100, 'Forastero');
        $this->createSnapshot($criollo, 100);
        $this->createSnapshot($forastero, 100);

        $this->actingAs($user)
            ->get(route('inventory.index', ['scion_variety_id' => $forastero->scion_variety_id]))
            ->assertOk()
            ->assertViewHas('inventories', fn ($inventories) => $inventories->total() === 1
                && $inventories->first()->batch->batch_code === 'B002');
    }

    public function test_inventory_page_rejects_invalid_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.index', ['scion_variety_id' => 999, 'status' => 'bogus']))
            ->assertSessionHasErrors(['scion_variety_id', 'status']);
    }

    public function test_inventory_page_shows_empty_state_when_no_records(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('No inventory snapshots found for the current filters.');
    }

    public function test_record_monthly_inventory_derives_counts_server_side(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 180, 150);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 20,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        RegraftRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'regraft_date' => '2026-09-03',
        ]);

        // Phase 7B: the snapshot's `regraft` reads the batch's cached counter.
        $batch->update(['regraft' => 10]);

        $this->actingAs($user)
            ->post(route('inventory.snapshots.store'), [
                'batch_id' => $batch->batch_id,
                'inventory_date' => '2026-09-15',
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batch->batch_id,
            'total_grafted' => 180,
            'regraft' => 10,
            'mortality' => 20,
            'status' => Inventory::STATUS_ACTIVE,
        ]);
    }

    public function test_record_monthly_inventory_rejects_a_second_snapshot_in_the_same_month(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 180, 150);
        $this->createSnapshot($batch, 150, '2026-09-01');

        $this->actingAs($user)
            ->from(route('inventory.index'))
            ->post(route('inventory.snapshots.store'), [
                'batch_id' => $batch->batch_id,
                'inventory_date' => '2026-09-20',
            ])
            ->assertSessionHasErrors(['batch_id'], null, 'inventory');

        $this->assertSame(1, Inventory::count());
    }

    public function test_inventory_status_is_frozen_from_the_batch_available_balance(): void
    {
        $this->assertSame(Inventory::STATUS_ACTIVE, Inventory::statusForAvailable(10));
        $this->assertSame(Inventory::STATUS_DEPLETED, Inventory::statusForAvailable(0));

        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 75, 75);

        $snapshot = $this->createSnapshot($batch, 75);

        $this->assertSame(Inventory::STATUS_ACTIVE, $snapshot->status);

        // Draining the live balance afterwards must NOT rewrite the frozen snapshot.
        $batch->update(['available_count' => 0]);

        $this->assertSame(Inventory::STATUS_ACTIVE, $snapshot->fresh()->status);
        $this->assertSame(Inventory::STATUS_DEPLETED, Inventory::statusForBatch($batch->fresh()));
    }

    public function test_batch_exposes_inventory_snapshots_relationship(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);
        $first = $this->createSnapshot($batch, 100, '2026-08-01');
        $second = $this->createSnapshot($batch, 100, '2026-09-01');

        $this->assertCount(2, $batch->inventories);
        $this->assertTrue($batch->inventories->contains($first));
        $this->assertTrue($batch->inventories->contains($second));
        $this->assertTrue($batch->latestInventory->is($second));
        $this->assertSame($batch->batch_id, $first->batch->batch_id);
    }
}
