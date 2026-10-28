<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_guests_are_redirected_from_suppliers_to_login(): void
    {
        $this->get(route('inventory.suppliers'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_suppliers_page(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $this->createSupplier('Real Supplier Co');

        $this->actingAs($user)
            ->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertSee('Supplier Directory')
            ->assertSee('Real Supplier Co')
            ->assertSee('Total Suppliers');
    }

    public function test_supplier_can_be_created(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($user)
            ->post(route('inventory.suppliers.manage.store'), [
                'supplier_name' => 'New Supplier',
                'contact_number' => '0999-123-4567',
                'address' => 'Somewhere, Philippines',
            ])
            ->assertRedirect(route('inventory.suppliers'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', [
            'supplier_name' => 'New Supplier',
            'contact_number' => '0999-123-4567',
            'address' => 'Somewhere, Philippines',
        ]);
    }

    public function test_duplicate_supplier_names_are_allowed(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($user)->post(route('inventory.suppliers.manage.store'), [
            'supplier_name' => 'Duplicate Name',
            'contact_number' => '0917-000-0001',
            'address' => 'Address One',
        ]);

        $this->actingAs($user)->post(route('inventory.suppliers.manage.store'), [
            'supplier_name' => 'Duplicate Name',
            'contact_number' => '0917-000-0002',
            'address' => 'Address Two',
        ]);

        $this->assertSame(2, Supplier::where('supplier_name', 'Duplicate Name')->count());
    }

    public function test_supplier_creation_validates_required_fields(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($user)
            ->post(route('inventory.suppliers.manage.store'), [])
            ->assertSessionHasErrors(['supplier_name', 'contact_number', 'address'], null, 'supplier');

        $this->assertSame(0, Supplier::count());
    }

    public function test_supplier_creation_rejects_over_length_values(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($user)
            ->post(route('inventory.suppliers.manage.store'), [
                'supplier_name' => str_repeat('a', 256),
                'contact_number' => str_repeat('b', 256),
                'address' => str_repeat('c', 256),
            ])
            ->assertSessionHasErrors(['supplier_name', 'contact_number', 'address'], null, 'supplier');

        $this->assertSame(0, Supplier::count());
    }

    public function test_supplier_can_be_updated(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $supplier = $this->createSupplier('Old Name');

        $this->actingAs($user)
            ->put(route('inventory.suppliers.update', $supplier), [
                'supplier_name' => 'Updated Name',
                'contact_number' => '0920-999-8888',
                'address' => 'Updated Address',
            ])
            ->assertRedirect(route('inventory.suppliers'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', [
            'supplier_id' => $supplier->supplier_id,
            'supplier_name' => 'Updated Name',
            'contact_number' => '0920-999-8888',
            'address' => 'Updated Address',
        ]);
    }

    public function test_supplier_update_validates_required_fields(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $supplier = $this->createSupplier('Keep Me');

        $this->actingAs($user)
            ->put(route('inventory.suppliers.update', $supplier), [])
            ->assertSessionHasErrors(['supplier_name', 'contact_number', 'address'], null, 'supplier');

        $this->assertDatabaseHas('suppliers', [
            'supplier_id' => $supplier->supplier_id,
            'supplier_name' => 'Keep Me',
        ]);
    }

    public function test_supplier_can_be_archived(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $supplier = $this->createSupplier('Archive Me');

        $this->actingAs($user)
            ->post(route('inventory.suppliers.archive', $supplier))
            ->assertRedirect(route('inventory.suppliers'))
            ->assertSessionHas('success');

        $this->assertNotNull($supplier->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'supplier_archived',
            'user_id' => $user->id,
        ]);
    }

    public function test_supplier_with_stock_ins_can_still_be_archived(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $supplier = $this->createSupplier('Protected Supplier');

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'origin' => 'Test Origin',
            'date_received' => '2026-09-05',
        ]);

        $this->actingAs($user)
            ->post(route('inventory.suppliers.archive', $supplier))
            ->assertRedirect(route('inventory.suppliers'))
            ->assertSessionHas('success');

        $this->assertNotNull($supplier->fresh()->archived_at);
        $this->assertDatabaseHas('stock_ins', ['supplier_id' => $supplier->supplier_id]);
    }

    public function test_archived_supplier_is_hidden_from_the_active_listing_and_can_be_restored(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $supplier = $this->createSupplier('Hidden Supplier');
        $supplier->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertDontSee('Hidden Supplier');

        $this->actingAs($user)
            ->get(route('inventory.suppliers', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('Hidden Supplier');

        $this->actingAs($user)
            ->post(route('inventory.suppliers.restore', $supplier))
            ->assertRedirect(route('inventory.suppliers', ['tab' => 'archived']))
            ->assertSessionHas('success');

        $this->assertNull($supplier->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'supplier_restored',
            'user_id' => $user->id,
        ]);
    }

    public function test_archiving_an_already_archived_supplier_is_rejected(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $supplier = $this->createSupplier('Already Archived');
        $supplier->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.suppliers.archive', $supplier))
            ->assertRedirect(route('inventory.suppliers'))
            ->assertSessionHas('error');
    }

    public function test_suppliers_page_filters_by_search(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $this->createSupplier('Alpha Supply');
        $this->createSupplier('Beta Supply');

        $this->actingAs($user)
            ->get(route('inventory.suppliers', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Supply')
            ->assertDontSee('Beta Supply');
    }

    public function test_suppliers_page_shows_empty_state(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($user)
            ->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertSee('No suppliers found for the current filters.');
    }

    public function test_supplier_reference_is_formatted(): void
    {
        $first = new Supplier;
        $first->supplier_id = 1;

        $padded = new Supplier;
        $padded->supplier_id = 42;

        $large = new Supplier;
        $large->supplier_id = 1234;

        $this->assertSame('SUP-001', $first->reference());
        $this->assertSame('SUP-042', $padded->reference());
        $this->assertSame('SUP-1234', $large->reference());
    }
}
