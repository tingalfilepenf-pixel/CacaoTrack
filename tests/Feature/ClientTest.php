<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\StockOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(string $name = 'Client A'): Client
    {
        return Client::create([
            'client_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    public function test_guests_are_redirected_from_clients_to_login(): void
    {
        $this->get(route('inventory.clients'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_clients_page(): void
    {
        $user = User::factory()->create();
        $this->createClient('Real Client Co');

        $this->actingAs($user)
            ->get(route('inventory.clients'))
            ->assertOk()
            ->assertSee('Client Directory')
            ->assertSee('Real Client Co')
            ->assertSee('Total Clients');
    }

    public function test_client_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.clients.manage.store'), [
                'client_name' => 'New Client',
                'contact_number' => '0999-123-4567',
                'address' => 'Somewhere, Philippines',
            ])
            ->assertRedirect(route('inventory.clients'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'client_name' => 'New Client',
            'contact_number' => '0999-123-4567',
            'address' => 'Somewhere, Philippines',
        ]);
    }

    public function test_duplicate_client_names_are_allowed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('inventory.clients.manage.store'), [
            'client_name' => 'Duplicate Name',
            'contact_number' => '0917-000-0001',
            'address' => 'Address One',
        ]);

        $this->actingAs($user)->post(route('inventory.clients.manage.store'), [
            'client_name' => 'Duplicate Name',
            'contact_number' => '0917-000-0002',
            'address' => 'Address Two',
        ]);

        $this->assertSame(2, Client::where('client_name', 'Duplicate Name')->count());
    }

    public function test_client_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.clients.manage.store'), [])
            ->assertSessionHasErrors(['client_name', 'contact_number', 'address'], null, 'client');

        $this->assertSame(0, Client::count());
    }

    public function test_client_creation_rejects_over_length_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.clients.manage.store'), [
                'client_name' => str_repeat('a', 256),
                'contact_number' => str_repeat('b', 256),
                'address' => str_repeat('c', 256),
            ])
            ->assertSessionHasErrors(['client_name', 'contact_number', 'address'], null, 'client');

        $this->assertSame(0, Client::count());
    }

    public function test_client_can_be_updated(): void
    {
        $user = User::factory()->create();
        $client = $this->createClient('Old Name');

        $this->actingAs($user)
            ->put(route('inventory.clients.update', $client), [
                'client_name' => 'Updated Name',
                'contact_number' => '0920-999-8888',
                'address' => 'Updated Address',
            ])
            ->assertRedirect(route('inventory.clients'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'client_id' => $client->client_id,
            'client_name' => 'Updated Name',
            'contact_number' => '0920-999-8888',
            'address' => 'Updated Address',
        ]);
    }

    public function test_client_update_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->createClient('Keep Me');

        $this->actingAs($user)
            ->put(route('inventory.clients.update', $client), [])
            ->assertSessionHasErrors(['client_name', 'contact_number', 'address'], null, 'client');

        $this->assertDatabaseHas('clients', [
            'client_id' => $client->client_id,
            'client_name' => 'Keep Me',
        ]);
    }

    public function test_client_can_be_archived(): void
    {
        $user = User::factory()->create();
        $client = $this->createClient('Archive Me');

        $this->actingAs($user)
            ->post(route('inventory.clients.archive', $client))
            ->assertRedirect(route('inventory.clients'))
            ->assertSessionHas('success');

        $this->assertNotNull($client->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'client_archived',
            'user_id' => $user->id,
        ]);
    }

    public function test_client_with_stock_outs_can_still_be_archived(): void
    {
        $user = User::factory()->create();
        $client = $this->createClient('Protected Client');

        StockOut::create([
            'client_id' => $client->client_id,
            'user_id' => $user->id,
            'total_amount' => null,
            'release_date' => '2026-09-05',
        ]);

        $this->actingAs($user)
            ->post(route('inventory.clients.archive', $client))
            ->assertRedirect(route('inventory.clients'))
            ->assertSessionHas('success');

        $this->assertNotNull($client->fresh()->archived_at);
        $this->assertDatabaseHas('stock_outs', ['client_id' => $client->client_id]);
    }

    public function test_archived_client_is_hidden_from_the_active_listing_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $client = $this->createClient('Hidden Client');
        $client->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.clients'))
            ->assertOk()
            ->assertDontSee('Hidden Client');

        $this->actingAs($user)
            ->get(route('inventory.clients', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('Hidden Client');

        $this->actingAs($user)
            ->post(route('inventory.clients.restore', $client))
            ->assertRedirect(route('inventory.clients', ['tab' => 'archived']))
            ->assertSessionHas('success');

        $this->assertNull($client->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'client_restored',
            'user_id' => $user->id,
        ]);
    }

    public function test_archiving_an_already_archived_client_is_rejected(): void
    {
        $user = User::factory()->create();
        $client = $this->createClient('Already Archived');
        $client->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.clients.archive', $client))
            ->assertRedirect(route('inventory.clients'))
            ->assertSessionHas('error');
    }

    public function test_clients_page_filters_by_search(): void
    {
        $user = User::factory()->create();
        $this->createClient('Alpha Client');
        $this->createClient('Beta Client');

        $this->actingAs($user)
            ->get(route('inventory.clients', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Client')
            ->assertDontSee('Beta Client');
    }

    public function test_clients_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.clients'))
            ->assertOk()
            ->assertSee('No clients found for the current filters.');
    }

    public function test_client_reference_is_formatted(): void
    {
        $first = new Client;
        $first->client_id = 1;

        $padded = new Client;
        $padded->client_id = 42;

        $large = new Client;
        $large->client_id = 1234;

        $this->assertSame('CLI-001', $first->reference());
        $this->assertSame('CLI-042', $padded->reference());
        $this->assertSame('CLI-1234', $large->reference());
    }
}
