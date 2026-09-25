<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\StockReversal;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReversalTest extends TestCase
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

    private function createClient(string $name = 'Client A'): Client
    {
        return Client::create([
            'client_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    private function createStockIn(User $user, Batch $batch, int $quantity): StockIn
    {
        return StockIn::create([
            'supplier_id' => $this->createSupplier()->supplier_id,
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => $quantity,
            'date_received' => '2026-09-01',
        ]);
    }

    private function createStockOut(User $user, Batch $batch, int $quantity): StockOut
    {
        return StockOut::create([
            'client_id' => $this->createClient()->client_id,
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => $quantity,
            'release_date' => '2026-09-01',
        ]);
    }

    public function test_guests_are_redirected_from_stock_reversal_to_login(): void
    {
        $this->get(route('inventory.stock-reversal'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_stock_reversal_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-reversal'))
            ->assertOk()
            ->assertSee('Stock Reversal')
            ->assertSee('Stock Reversal History')
            ->assertSee('Total Quantity Reversed');
    }

    public function test_reversing_a_stock_in_decreases_inventory_and_records_the_reversal(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 100);
        $stockIn = $this->createStockIn($user, $batch, 50);

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'in',
                'source_id' => $stockIn->stock_in_id,
                'quantity' => 20,
                'reason' => 'Supplier sent the wrong variety.',
                'reversed_at' => '2026-09-05',
            ])
            ->assertRedirect(route('inventory.stock-reversal'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_reversals', [
            'stock_in_id' => $stockIn->stock_in_id,
            'stock_out_id' => null,
            'user_id' => $user->id,
            'quantity' => 20,
            'reason' => 'Supplier sent the wrong variety.',
            'reversed_at' => '2026-09-05 00:00:00',
        ]);

        $this->assertSame(80, (int) $inventory->fresh()->current_quantity);
    }

    public function test_reversing_a_stock_out_increases_inventory_and_records_the_reversal(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 70);
        $stockOut = $this->createStockOut($user, $batch, 30);

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'out',
                'source_id' => $stockOut->stock_out_id,
                'quantity' => 30,
                'reason' => 'Client returned the seedlings.',
                'reversed_at' => '2026-09-05',
            ])
            ->assertRedirect(route('inventory.stock-reversal'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_reversals', [
            'stock_in_id' => null,
            'stock_out_id' => $stockOut->stock_out_id,
            'quantity' => 30,
        ]);

        $this->assertSame(100, (int) $inventory->fresh()->current_quantity);
    }

    public function test_reversal_rejects_a_quantity_greater_than_the_reversible_quantity_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 100);
        $stockIn = $this->createStockIn($user, $batch, 40);

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'in',
                'source_id' => $stockIn->stock_in_id,
                'quantity' => 50,
                'reason' => 'Too much.',
                'reversed_at' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'reversal');

        $this->assertSame(0, StockReversal::count());
        $this->assertSame(100, (int) $inventory->fresh()->current_quantity);
    }

    public function test_accumulated_reversals_cannot_exceed_the_original_transaction(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 100);
        $stockIn = $this->createStockIn($user, $batch, 50);

        $this->actingAs($user)->post(route('inventory.stock-reversal.store'), [
            'type' => 'in',
            'source_id' => $stockIn->stock_in_id,
            'quantity' => 30,
            'reason' => 'Partial reversal.',
            'reversed_at' => '2026-09-02',
        ])->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'in',
                'source_id' => $stockIn->stock_in_id,
                'quantity' => 30,
                'reason' => 'Attempting to over-reverse.',
                'reversed_at' => '2026-09-03',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'reversal');

        $this->assertSame(1, StockReversal::count());
        $this->assertSame(20, $stockIn->fresh()->reversibleQuantity());

        $this->actingAs($user)->post(route('inventory.stock-reversal.store'), [
            'type' => 'in',
            'source_id' => $stockIn->stock_in_id,
            'quantity' => 20,
            'reason' => 'Final reversal.',
            'reversed_at' => '2026-09-04',
        ])->assertSessionHas('success');

        $this->assertSame(0, $stockIn->fresh()->reversibleQuantity());

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'in',
                'source_id' => $stockIn->stock_in_id,
                'quantity' => 1,
                'reason' => 'Nothing left to reverse.',
                'reversed_at' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['source_id'], null, 'reversal');

        $this->assertSame(2, StockReversal::count());
    }

    public function test_reversing_a_stock_in_beyond_the_current_inventory_is_rejected(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $inventory = $this->createInventory($batch, 10);
        $stockIn = $this->createStockIn($user, $batch, 50);

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'in',
                'source_id' => $stockIn->stock_in_id,
                'quantity' => 30,
                'reason' => 'Not enough stock on hand.',
                'reversed_at' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'reversal');

        $this->assertSame(0, StockReversal::count());
        $this->assertSame(10, (int) $inventory->fresh()->current_quantity);
    }

    public function test_reversal_requires_valid_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [])
            ->assertSessionHasErrors(['type', 'source_id', 'quantity', 'reason', 'reversed_at'], null, 'reversal');
    }

    public function test_reversal_rejects_an_unknown_source_transaction(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'in',
                'source_id' => 9999,
                'quantity' => 5,
                'reason' => 'Unknown.',
                'reversed_at' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['source_id'], null, 'reversal');

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'out',
                'source_id' => 9999,
                'quantity' => 5,
                'reason' => 'Unknown.',
                'reversed_at' => '2026-09-05',
            ])
            ->assertSessionHasErrors(['source_id'], null, 'reversal');
    }

    public function test_reversal_rejects_zero_and_negative_quantities(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $stockIn = $this->createStockIn($user, $batch, 50);

        foreach ([0, -5] as $quantity) {
            $this->actingAs($user)
                ->post(route('inventory.stock-reversal.store'), [
                    'type' => 'in',
                    'source_id' => $stockIn->stock_in_id,
                    'quantity' => $quantity,
                    'reason' => 'Invalid quantity.',
                    'reversed_at' => '2026-09-05',
                ])
                ->assertSessionHasErrors(['quantity'], null, 'reversal');
        }

        $this->assertSame(0, StockReversal::count());
    }

    public function test_reversal_rejects_a_future_date(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $stockIn = $this->createStockIn($user, $batch, 50);

        $this->actingAs($user)
            ->post(route('inventory.stock-reversal.store'), [
                'type' => 'in',
                'source_id' => $stockIn->stock_in_id,
                'quantity' => 10,
                'reason' => 'Future dated.',
                'reversed_at' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['reversed_at'], null, 'reversal');

        $this->assertSame(0, StockReversal::count());
    }

    public function test_reversal_page_lists_real_records(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B009', 100);
        $stockIn = $this->createStockIn($user, $batch, 60);
        $reversal = StockReversal::create([
            'stock_in_id' => $stockIn->stock_in_id,
            'user_id' => $user->id,
            'quantity' => 15,
            'reason' => 'Wrong seedling grade.',
            'reversed_at' => '2026-09-04',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-reversal'))
            ->assertOk()
            ->assertSee($reversal->reference())
            ->assertSee('Wrong seedling grade.')
            ->assertSee('B009');
    }

    public function test_reversal_page_filters_by_type_and_search(): void
    {
        $user = User::factory()->create();
        $firstBatch = $this->createBatch($user, 'B001', 100);
        $secondBatch = $this->createBatch($user, 'B002', 100);
        $stockIn = $this->createStockIn($user, $firstBatch, 50);
        $stockOut = $this->createStockOut($user, $secondBatch, 50);

        $inReversal = StockReversal::create([
            'stock_in_id' => $stockIn->stock_in_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'reason' => 'Alpha reason',
            'reversed_at' => '2026-09-01',
        ]);
        $outReversal = StockReversal::create([
            'stock_out_id' => $stockOut->stock_out_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'reason' => 'Beta reason',
            'reversed_at' => '2026-09-02',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-reversal', ['type' => 'in']))
            ->assertOk()
            ->assertSee($inReversal->reference())
            ->assertDontSee($outReversal->reference());

        $this->actingAs($user)
            ->get(route('inventory.stock-reversal', ['search' => 'Beta']))
            ->assertOk()
            ->assertSee($outReversal->reference())
            ->assertDontSee($inReversal->reference());
    }

    public function test_reversal_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-reversal'))
            ->assertOk()
            ->assertSee('No stock reversals found for the current filters.');
    }

    public function test_reversal_reference_is_formatted(): void
    {
        $first = new StockReversal;
        $first->reversal_id = 1;

        $padded = new StockReversal;
        $padded->reversal_id = 42;

        $large = new StockReversal;
        $large->reversal_id = 1234;

        $this->assertSame('REV-001', $first->reference());
        $this->assertSame('REV-042', $padded->reference());
        $this->assertSame('REV-1234', $large->reference());
    }

    public function test_reversible_quantity_helpers_account_for_existing_reversals(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $stockIn = $this->createStockIn($user, $batch, 50);

        $this->assertSame(0, $stockIn->reversedQuantity());
        $this->assertSame(50, $stockIn->reversibleQuantity());

        StockReversal::create([
            'stock_in_id' => $stockIn->stock_in_id,
            'user_id' => $user->id,
            'quantity' => 20,
            'reason' => 'Partial.',
            'reversed_at' => '2026-09-02',
        ]);

        $this->assertSame(20, $stockIn->fresh()->reversedQuantity());
        $this->assertSame(30, $stockIn->fresh()->reversibleQuantity());
    }
}
