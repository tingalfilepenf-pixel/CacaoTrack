<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VarietyTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, Variety $variety, string $code = 'B001'): Batch
    {
        $scion = Scion::create(['scion_name' => 'ICS-1']);
        $potType = PotType::create(['pot_name' => 'Standard Polybag']);

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

    private function createVariety(string $name = 'Criollo'): Variety
    {
        return Variety::create([
            'variety_name' => $name,
            'description' => 'Test description',
        ]);
    }

    public function test_guests_are_redirected_from_varieties_to_login(): void
    {
        $this->get(route('inventory.varieties'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_varieties_page(): void
    {
        $user = User::factory()->create();
        $this->createVariety('Real Variety');

        $this->actingAs($user)
            ->get(route('inventory.varieties'))
            ->assertOk()
            ->assertSee('Variety Directory')
            ->assertSee('Real Variety')
            ->assertSee('Total Varieties');
    }

    public function test_variety_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.varieties.manage.store'), [
                'variety_name' => 'New Variety',
                'description' => 'A newly added variety.',
            ])
            ->assertRedirect(route('inventory.varieties'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('varieties', [
            'variety_name' => 'New Variety',
            'description' => 'A newly added variety.',
        ]);
    }

    public function test_variety_can_be_created_without_a_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.varieties.manage.store'), [
                'variety_name' => 'No Description Variety',
            ])
            ->assertRedirect(route('inventory.varieties'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('varieties', [
            'variety_name' => 'No Description Variety',
            'description' => null,
        ]);
    }

    public function test_duplicate_variety_names_are_allowed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('inventory.varieties.manage.store'), [
            'variety_name' => 'Duplicate Name',
        ]);

        $this->actingAs($user)->post(route('inventory.varieties.manage.store'), [
            'variety_name' => 'Duplicate Name',
        ]);

        $this->assertSame(2, Variety::where('variety_name', 'Duplicate Name')->count());
    }

    public function test_variety_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.varieties.manage.store'), [])
            ->assertSessionHasErrors(['variety_name'], null, 'variety');

        $this->assertSame(0, Variety::count());
    }

    public function test_variety_creation_rejects_over_length_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.varieties.manage.store'), [
                'variety_name' => str_repeat('a', 256),
                'description' => str_repeat('b', 1001),
            ])
            ->assertSessionHasErrors(['variety_name', 'description'], null, 'variety');

        $this->assertSame(0, Variety::count());
    }

    public function test_variety_can_be_updated(): void
    {
        $user = User::factory()->create();
        $variety = $this->createVariety('Old Name');

        $this->actingAs($user)
            ->put(route('inventory.varieties.update', $variety), [
                'variety_name' => 'Updated Name',
                'description' => 'Updated description.',
            ])
            ->assertRedirect(route('inventory.varieties'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('varieties', [
            'variety_id' => $variety->variety_id,
            'variety_name' => 'Updated Name',
            'description' => 'Updated description.',
        ]);
    }

    public function test_variety_update_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $variety = $this->createVariety('Keep Me');

        $this->actingAs($user)
            ->put(route('inventory.varieties.update', $variety), [])
            ->assertSessionHasErrors(['variety_name'], null, 'variety');

        $this->assertDatabaseHas('varieties', [
            'variety_id' => $variety->variety_id,
            'variety_name' => 'Keep Me',
        ]);
    }

    public function test_variety_can_be_archived(): void
    {
        $user = User::factory()->create();
        $variety = $this->createVariety('Archive Me');

        $this->actingAs($user)
            ->post(route('inventory.varieties.archive', $variety))
            ->assertRedirect(route('inventory.varieties'))
            ->assertSessionHas('success');

        $this->assertNotNull($variety->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'variety_archived',
            'user_id' => $user->id,
        ]);
    }

    public function test_variety_with_batches_can_still_be_archived(): void
    {
        $user = User::factory()->create();
        $variety = $this->createVariety('Protected Variety');
        $this->createBatch($user, $variety);

        $this->actingAs($user)
            ->post(route('inventory.varieties.archive', $variety))
            ->assertRedirect(route('inventory.varieties'))
            ->assertSessionHas('success');

        $this->assertNotNull($variety->fresh()->archived_at);
        $this->assertDatabaseHas('batches', ['variety_id' => $variety->variety_id]);
    }

    public function test_archived_variety_is_hidden_from_the_active_listing_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $variety = $this->createVariety('Hidden Variety');
        $variety->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.varieties'))
            ->assertOk()
            ->assertDontSee('Hidden Variety');

        $this->actingAs($user)
            ->get(route('inventory.varieties', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('Hidden Variety');

        $this->actingAs($user)
            ->post(route('inventory.varieties.restore', $variety))
            ->assertRedirect(route('inventory.varieties', ['tab' => 'archived']))
            ->assertSessionHas('success');

        $this->assertNull($variety->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'variety_restored',
            'user_id' => $user->id,
        ]);
    }

    public function test_archiving_an_already_archived_variety_is_rejected(): void
    {
        $user = User::factory()->create();
        $variety = $this->createVariety('Already Archived');
        $variety->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.varieties.archive', $variety))
            ->assertRedirect(route('inventory.varieties'))
            ->assertSessionHas('error');
    }

    public function test_varieties_page_filters_by_search(): void
    {
        $user = User::factory()->create();
        $this->createVariety('Alpha Variety');
        $this->createVariety('Beta Variety');

        $this->actingAs($user)
            ->get(route('inventory.varieties', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Variety')
            ->assertDontSee('Beta Variety');
    }

    public function test_varieties_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.varieties'))
            ->assertOk()
            ->assertSee('No varieties found for the current filters.');
    }

    public function test_variety_reference_is_formatted(): void
    {
        $first = new Variety;
        $first->variety_id = 1;

        $padded = new Variety;
        $padded->variety_id = 42;

        $large = new Variety;
        $large->variety_id = 1234;

        $this->assertSame('VAR-001', $first->reference());
        $this->assertSame('VAR-042', $padded->reference());
        $this->assertSame('VAR-1234', $large->reference());
    }
}
