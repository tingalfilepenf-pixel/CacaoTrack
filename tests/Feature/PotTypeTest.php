<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PotTypeTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, PotType $potType, string $code = 'B001'): Batch
    {
        $variety = Variety::create(['variety_name' => 'Criollo']);
        $scion = Scion::create(['scion_name' => 'ICS-1']);

        return Batch::create([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => 100,
            'viable_count' => 100,
            'available_count' => 100,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => 'active',
        ]);
    }

    private function createPotType(string $name = 'Standard Polybag'): PotType
    {
        return PotType::create([
            'pot_name' => $name,
            'description' => 'Test description',
        ]);
    }

    public function test_guests_are_redirected_from_pot_types_to_login(): void
    {
        $this->get(route('inventory.pot-types'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_pot_types_page(): void
    {
        $user = User::factory()->create();
        $this->createPotType('Real Pot Type');

        $this->actingAs($user)
            ->get(route('inventory.pot-types'))
            ->assertOk()
            ->assertSee('Pot Type Directory')
            ->assertSee('Real Pot Type')
            ->assertSee('Total Pot Types');
    }

    public function test_pot_type_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.pot-types.manage.store'), [
                'pot_name' => 'New Pot Type',
                'description' => 'A newly added pot type.',
            ])
            ->assertRedirect(route('inventory.pot-types'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pot_types', [
            'pot_name' => 'New Pot Type',
            'description' => 'A newly added pot type.',
        ]);
    }

    public function test_pot_type_can_be_created_without_a_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.pot-types.manage.store'), [
                'pot_name' => 'No Description Pot Type',
            ])
            ->assertRedirect(route('inventory.pot-types'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pot_types', [
            'pot_name' => 'No Description Pot Type',
            'description' => null,
        ]);
    }

    public function test_duplicate_pot_type_names_are_allowed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('inventory.pot-types.manage.store'), [
            'pot_name' => 'Duplicate Name',
        ]);

        $this->actingAs($user)->post(route('inventory.pot-types.manage.store'), [
            'pot_name' => 'Duplicate Name',
        ]);

        $this->assertSame(2, PotType::where('pot_name', 'Duplicate Name')->count());
    }

    public function test_pot_type_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.pot-types.manage.store'), [])
            ->assertSessionHasErrors(['pot_name'], null, 'pot');

        $this->assertSame(0, PotType::count());
    }

    public function test_pot_type_creation_rejects_over_length_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.pot-types.manage.store'), [
                'pot_name' => str_repeat('a', 256),
                'description' => str_repeat('b', 1001),
            ])
            ->assertSessionHasErrors(['pot_name', 'description'], null, 'pot');

        $this->assertSame(0, PotType::count());
    }

    public function test_pot_type_can_be_updated(): void
    {
        $user = User::factory()->create();
        $potType = $this->createPotType('Old Name');

        $this->actingAs($user)
            ->put(route('inventory.pot-types.update', $potType), [
                'pot_name' => 'Updated Name',
                'description' => 'Updated description.',
            ])
            ->assertRedirect(route('inventory.pot-types'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pot_types', [
            'pot_id' => $potType->pot_id,
            'pot_name' => 'Updated Name',
            'description' => 'Updated description.',
        ]);
    }

    public function test_pot_type_update_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $potType = $this->createPotType('Keep Me');

        $this->actingAs($user)
            ->put(route('inventory.pot-types.update', $potType), [])
            ->assertSessionHasErrors(['pot_name'], null, 'pot');

        $this->assertDatabaseHas('pot_types', [
            'pot_id' => $potType->pot_id,
            'pot_name' => 'Keep Me',
        ]);
    }

    public function test_pot_type_can_be_archived(): void
    {
        $user = User::factory()->create();
        $potType = $this->createPotType('Archive Me');

        $this->actingAs($user)
            ->post(route('inventory.pot-types.archive', $potType))
            ->assertRedirect(route('inventory.pot-types'))
            ->assertSessionHas('success');

        $this->assertNotNull($potType->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'pot_type_archived',
            'user_id' => $user->id,
        ]);
    }

    public function test_pot_type_with_batches_can_still_be_archived(): void
    {
        $user = User::factory()->create();
        $potType = $this->createPotType('Protected Pot Type');
        $this->createBatch($user, $potType);

        $this->actingAs($user)
            ->post(route('inventory.pot-types.archive', $potType))
            ->assertRedirect(route('inventory.pot-types'))
            ->assertSessionHas('success');

        $this->assertNotNull($potType->fresh()->archived_at);
        $this->assertDatabaseHas('batches', ['pot_id' => $potType->pot_id]);
    }

    public function test_archived_pot_type_is_hidden_from_the_active_listing_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $potType = $this->createPotType('Hidden Pot Type');
        $potType->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.pot-types'))
            ->assertOk()
            ->assertDontSee('Hidden Pot Type');

        $this->actingAs($user)
            ->get(route('inventory.pot-types', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('Hidden Pot Type');

        $this->actingAs($user)
            ->post(route('inventory.pot-types.restore', $potType))
            ->assertRedirect(route('inventory.pot-types', ['tab' => 'archived']))
            ->assertSessionHas('success');

        $this->assertNull($potType->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'pot_type_restored',
            'user_id' => $user->id,
        ]);
    }

    public function test_archiving_an_already_archived_pot_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $potType = $this->createPotType('Already Archived');
        $potType->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.pot-types.archive', $potType))
            ->assertRedirect(route('inventory.pot-types'))
            ->assertSessionHas('error');
    }

    public function test_pot_types_page_filters_by_search(): void
    {
        $user = User::factory()->create();
        $this->createPotType('Alpha Pot');
        $this->createPotType('Beta Pot');

        $this->actingAs($user)
            ->get(route('inventory.pot-types', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Pot')
            ->assertDontSee('Beta Pot');
    }

    public function test_pot_types_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.pot-types'))
            ->assertOk()
            ->assertSee('No pot types found for the current filters.');
    }

    public function test_pot_type_reference_is_formatted(): void
    {
        $first = new PotType;
        $first->pot_id = 1;

        $padded = new PotType;
        $padded->pot_id = 42;

        $large = new PotType;
        $large->pot_id = 1234;

        $this->assertSame('POT-001', $first->reference());
        $this->assertSame('POT-042', $padded->reference());
        $this->assertSame('POT-1234', $large->reference());
    }
}
