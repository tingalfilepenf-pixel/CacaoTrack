<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\ScionVariety;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock Out on the revised model (ERD §3.14/§3.15): a header plus one detail
 * line per batch/scion variety. Availability is checked per line AND per batch
 * against the live `available_count` inside one transaction.
 */
class StockOutTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(
        User $user,
        string $code = 'B001',
        int $available = 100,
        ?ScionVariety $variety = null,
    ): Batch {
        $variety ??= ScionVariety::create(['scion_variety_name' => 'Criollo']);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => $available,
            'regraft' => 0,
            'available_count' => $available,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
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

    private function payload(Client $client, array $details, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->client_id,
            'release_date' => '2026-09-05',
            'details' => $details,
        ], $overrides);
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

    public function test_stock_out_unit_dropdown_offers_only_pots(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['pots'], StockOutDetail::UNITS);

        $this->actingAs($user)
            ->get(route('inventory.stock-out'))
            ->assertOk()
            ->assertSee('<option value="pots">Pots</option>', false);
    }

    public function test_stock_out_creates_header_and_details_and_decrements_available(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 30, 'unit' => 'pots', 'unit_price' => 10],
            ]))
            ->assertRedirect(route('inventory.stock-out'))
            ->assertSessionHas('success');

        $header = StockOut::first();
        $this->assertNotNull($header);
        $this->assertSame($client->client_id, (int) $header->client_id);
        $this->assertSame('300.00', (string) $header->total_amount); // 30 x 10.00

        $this->assertDatabaseHas('stock_out_details', [
            'stock_out_id' => $header->stock_out_id,
            'batch_id' => $batch->batch_id,
            'scion_variety_id' => $batch->scion_variety_id,
            'quantity' => 30,
            'unit' => 'pots',
            'amount' => '300.00',
        ]);

        $this->assertSame(70, (int) $batch->fresh()->available_count);
    }

    public function test_stock_out_supports_multiple_detail_lines_across_batches(): void
    {
        $user = User::factory()->create();
        $first = $this->createBatch($user, 'B001', 100);
        $second = $this->createBatch($user, 'B002', 50, ScionVariety::create(['scion_variety_name' => 'Trinitario']));
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $first->batch_id, 'quantity' => 10, 'unit' => 'pots'],
                ['batch_id' => $second->batch_id, 'quantity' => 20, 'unit' => 'pots'],
            ]))
            ->assertRedirect(route('inventory.stock-out'));

        $this->assertSame(2, StockOutDetail::count());
        $this->assertSame(90, (int) $first->fresh()->available_count);
        $this->assertSame(30, (int) $second->fresh()->available_count);
        $this->assertTrue(StockOutDetail::where('batch_id', $second->batch_id)->first()->scionVariety->is($second->scionVariety));
    }

    public function test_stock_out_rejects_a_line_above_available_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 150, 'unit' => 'pots'],
            ]))
            ->assertSessionHasErrors(['details.0.quantity'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
        $this->assertSame(100, (int) $batch->fresh()->available_count);
    }

    public function test_stock_out_rejects_aggregate_lines_above_available(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 60, 'unit' => 'pots'],
                ['batch_id' => $batch->batch_id, 'quantity' => 60, 'unit' => 'pots'],
            ]))
            ->assertSessionHasErrors(['details.0.quantity'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
        $this->assertSame(100, (int) $batch->fresh()->available_count);
    }

    public function test_stock_out_rejects_a_batch_without_a_scion_variety(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Temp']);
        $batch = $this->createBatch($user, 'B001', 100, $variety);
        $batch->update(['scion_variety_id' => null]);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots'],
            ]))
            ->assertSessionHasErrors(['details.0.batch_id'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
    }

    public function test_stock_out_requires_valid_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), [])
            ->assertSessionHasErrors(['client_id', 'release_date', 'details'], null, 'stockout');
    }

    public function test_stock_out_rejects_an_unknown_client_or_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots'],
            ], ['client_id' => 9999]))
            ->assertSessionHasErrors(['client_id'], null, 'stockout');

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => 9999, 'quantity' => 10, 'unit' => 'pots'],
            ]))
            ->assertSessionHasErrors(['details.0.batch_id'], null, 'stockout');
    }

    public function test_stock_out_rejects_an_archived_client(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $client = $this->createClient();
        $client->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots'],
            ]))
            ->assertSessionHasErrors(['client_id'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
    }

    public function test_stock_out_rejects_zero_and_negative_quantities_and_invalid_units(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $client = $this->createClient();

        foreach ([0, -5] as $quantity) {
            $this->actingAs($user)
                ->post(route('inventory.stock-out.store'), $this->payload($client, [
                    ['batch_id' => $batch->batch_id, 'quantity' => $quantity, 'unit' => 'pots'],
                ]))
                ->assertSessionHasErrors(['details.0.quantity'], null, 'stockout');
        }

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 5, 'unit' => 'bogus'],
            ]))
            ->assertSessionHasErrors(['details.0.unit'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
    }

    public function test_stock_out_rejects_a_future_release_date(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $client = $this->createClient();

        $this->actingAs($user)
            ->post(route('inventory.stock-out.store'), $this->payload($client, [
                ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots'],
            ], ['release_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['release_date'], null, 'stockout');

        $this->assertSame(0, StockOut::count());
    }

    public function test_stock_out_treats_unit_price_as_optional(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100);
        $client = $this->createClient();

        $this->actingAs($user)->post(route('inventory.stock-out.store'), $this->payload($client, [
            ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots'],
        ]));

        $header = StockOut::first();
        $this->assertNull($header->total_amount);

        $this->assertDatabaseHas('stock_out_details', [
            'stock_out_id' => $header->stock_out_id,
            'unit_price' => null,
            'amount' => null,
        ]);
    }

    public function test_stock_out_page_lists_real_records(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B009', 100);
        $client = $this->createClient('Real Client Co');

        $header = StockOut::create([
            'client_id' => $client->client_id,
            'user_id' => $user->id,
            'total_amount' => 2310.50,
            'release_date' => '2026-09-04',
        ]);
        $header->details()->create(['batch_id' => $batch->batch_id, 'quantity' => 77, 'unit' => 'pots', 'unit_price' => 30, 'amount' => 2310.00]);

        $this->actingAs($user)
            ->get(route('inventory.stock-out'))
            ->assertOk()
            ->assertSee('Real Client Co')
            ->assertSee('77')
            ->assertSee('2,310.50');
    }

    public function test_stock_out_page_filters_by_client_and_search(): void
    {
        $user = User::factory()->create();
        $first = $this->createBatch($user, 'B001', 100);
        $second = $this->createBatch($user, 'B002', 100, ScionVariety::create(['scion_variety_name' => 'Trinitario']));
        $clientA = $this->createClient('Alpha Client');
        $clientB = $this->createClient('Beta Client');

        $stockOutA = StockOut::create(['client_id' => $clientA->client_id, 'user_id' => $user->id, 'release_date' => '2026-09-01']);
        $stockOutA->details()->create(['batch_id' => $first->batch_id, 'quantity' => 10, 'unit' => 'pots']);

        $stockOutB = StockOut::create(['client_id' => $clientB->client_id, 'user_id' => $user->id, 'release_date' => '2026-09-02']);
        $stockOutB->details()->create(['batch_id' => $second->batch_id, 'quantity' => 20, 'unit' => 'pots']);

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
