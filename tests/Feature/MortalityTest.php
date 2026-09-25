<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MortalityTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, int $viable = 100): Batch
    {
        $variety = Variety::create(['variety_name' => 'Criollo']);
        $scion = Scion::create(['scion_name' => 'ICS-1']);
        $potType = PotType::create(['pot_name' => 'Standard Polybag']);

        return Batch::create([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => $viable,
            'viable_count' => $viable,
            'available_count' => $viable,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_guests_are_redirected_from_mortality_to_login(): void
    {
        $this->get(route('operational.mortality'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_mortality_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.mortality'))
            ->assertOk()
            ->assertSee('Mortality Tracking');
    }

    public function test_user_can_record_mortality(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $response = $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $response->assertRedirect(route('operational.mortality'));
        $this->assertDatabaseHas('mortality_records', [
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 25,
            'cause' => 'Disease',
        ]);

        // Recording mortality deducts the dead plants from the batch's alive
        // count (stored viable_count).
        $this->assertSame(75, $batch->fresh()->alivePlants());
    }

    public function test_mortality_quantity_cannot_exceed_batch_alive_count(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, viable: 100);

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 101,
                'cause' => 'Pest',
                'recorded_at' => '2026-09-01',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('mortality_records', 0);
        $this->assertSame(100, $batch->fresh()->alivePlants());
    }

    public function test_cumulative_mortality_cannot_exceed_batch_alive_count(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, viable: 100);

        // First 90 deaths are recorded (alive drops to 10).
        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 90,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);
        $this->assertSame(10, $batch->fresh()->alivePlants());

        // A further 20 would exceed the remaining alive count.
        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 20,
                'cause' => 'Pest',
                'recorded_at' => '2026-09-02',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('mortality_records', 1);
    }

    public function test_mortality_requires_positive_quantity_and_valid_batch(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => 999,
                'quantity' => 0,
                'cause' => 'Disease',
                'recorded_at' => '2026-09-01',
            ])
            ->assertSessionHasErrors(['batch_id', 'quantity']);

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => 999,
                'quantity' => 5,
                'cause' => '',
                'recorded_at' => '2026-09-01',
            ])
            ->assertSessionHasErrors(['batch_id', 'cause']);
    }

    public function test_user_can_adjust_a_mortality_record(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);
        $this->assertSame(75, $batch->fresh()->alivePlants());

        $record = MortalityRecord::where('batch_id', $batch->batch_id)->first();

        $response = $this->actingAs($user)->post(route('operational.mortality.records.adjust', $record), [
            'adjustment_quantity' => -5,
            'reason' => 'Recount corrected the entry.',
        ]);

        $response->assertRedirect(route('operational.mortality'));
        $this->assertDatabaseHas('mortality_adjustments', [
            'mortality_id' => $record->mortality_id,
            'approved_by_id' => $user->id,
            'adjustment_quantity' => -5,
            'reason' => 'Recount corrected the entry.',
        ]);
        $this->assertSame(20, $record->fresh()->effectiveQuantity());

        // A negative adjustment restores those plants to the alive count.
        $this->assertSame(80, $batch->fresh()->alivePlants());
    }

    public function test_adjustment_cannot_make_effective_mortality_negative(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $record = MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.adjust', $record), [
                'adjustment_quantity' => -11,
                'reason' => 'Invalid correction.',
            ])
            ->assertSessionHasErrors('adjustment_quantity');

        $this->assertDatabaseCount('mortality_adjustments', 0);
    }

    public function test_adjustment_cannot_exceed_batch_alive_count(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, viable: 100);

        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 90,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $record = MortalityRecord::where('batch_id', $batch->batch_id)->first();

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.adjust', $record), [
                'adjustment_quantity' => 11,
                'reason' => 'Invalid increase.',
            ])
            ->assertSessionHasErrors('adjustment_quantity');

        $this->assertDatabaseCount('mortality_adjustments', 0);
        $this->assertSame(10, $batch->fresh()->alivePlants());
    }

    public function test_adjustment_quantity_cannot_be_zero(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $record = MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.adjust', $record), [
                'adjustment_quantity' => 0,
                'reason' => 'No-op.',
            ])
            ->assertSessionHasErrors('adjustment_quantity');
    }

    public function test_mortality_page_filters_by_batch_and_date(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $record = MortalityRecord::where('batch_id', $batch->batch_id)->first();

        $this->actingAs($user)->post(route('operational.mortality.records.adjust', $record), [
            'adjustment_quantity' => -5,
            'reason' => 'Recount correction',
        ]);

        $this->actingAs($user)
            ->get(route('operational.mortality', ['batch_id' => $batch->batch_id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('Disease')
            ->assertSee('Recount correction');
    }
}
