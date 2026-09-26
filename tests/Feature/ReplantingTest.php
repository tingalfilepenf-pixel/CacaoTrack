<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regraft / Replanting on the revised model. Regraft and Replanting are the same
 * recorded procedure (the separate `regraft_records` table was retracted), so
 * both are stored in `replanting_records`. The removed scion stock pool is no
 * longer involved.
 */
class ReplantingTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, int $viable = 100): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => $viable,
            'viable_count' => $viable,
            'available_count' => $viable,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function recordMortality(Batch $batch, User $user, int $quantity): MortalityRecord
    {
        return MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => $quantity,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);
    }

    public function test_guests_are_redirected_from_replanting_to_login(): void
    {
        $this->get(route('operational.replanting'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_replanting_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.replanting'))
            ->assertOk()
            ->assertSee('Regraft');
    }

    public function test_user_can_record_replanting(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 25);

        $this->actingAs($user)
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 20,
                'replanting_date' => '2026-09-03',
                'remark' => 'Replaced affected seedlings.',
            ])
            ->assertRedirect(route('operational.replanting'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('replanting_records', [
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 20,
            'replanting_date' => '2026-09-03',
            'remark' => 'Replaced affected seedlings.',
        ]);
    }

    public function test_replanting_requires_positive_quantity(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 25);

        $this->actingAs($user)
            ->from(route('operational.replanting'))
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 0,
                'replanting_date' => '2026-09-03',
            ])
            ->assertSessionHasErrors('quantity', null, 'replanting');

        $this->assertDatabaseCount('replanting_records', 0);
    }

    public function test_replanting_cannot_exceed_batch_deficit(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 25);

        $this->actingAs($user)
            ->from(route('operational.replanting'))
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 26,
                'replanting_date' => '2026-09-03',
            ])
            ->assertSessionHasErrors('quantity', null, 'replanting');

        $this->assertDatabaseCount('replanting_records', 0);
    }

    public function test_cumulative_replanting_cannot_exceed_deficit(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 25);

        ReplantingRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 20,
            'replanting_date' => '2026-09-02',
        ]);

        $this->actingAs($user)
            ->from(route('operational.replanting'))
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 6,
                'replanting_date' => '2026-09-03',
            ])
            ->assertSessionHasErrors('quantity', null, 'replanting');

        $this->assertDatabaseCount('replanting_records', 1);
    }

    public function test_replanting_requires_valid_batch_and_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('operational.replanting'))
            ->post(route('operational.replanting.records.store'), [
                'batch_id' => 999,
                'quantity' => 5,
                'replanting_date' => '',
            ])
            ->assertSessionHasErrors(['batch_id', 'replanting_date'], null, 'replanting');

        $this->assertDatabaseCount('replanting_records', 0);
    }

    public function test_replanting_remark_is_optional(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 10);

        $this->actingAs($user)->post(route('operational.replanting.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 5,
            'replanting_date' => '2026-09-03',
        ]);

        $this->assertDatabaseHas('replanting_records', [
            'batch_id' => $batch->batch_id,
            'quantity' => 5,
            'remark' => null,
        ]);
    }

    public function test_replanting_page_shows_deficit(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 25);

        ReplantingRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'replanting_date' => '2026-09-03',
        ]);

        $this->actingAs($user)
            ->get(route('operational.replanting'))
            ->assertOk()
            ->assertSee('Regraft Status by Batch')
            ->assertSee('B001');
    }

    public function test_replanting_page_filters_by_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 25);

        $this->actingAs($user)
            ->get(route('operational.replanting', ['batch_id' => $batch->batch_id]))
            ->assertOk()
            ->assertSee('B001');
    }

    public function test_batch_replanting_deficit_is_derived_from_effective_mortality(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $this->recordMortality($batch, $user, 25);

        ReplantingRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 15,
            'replanting_date' => '2026-09-03',
        ]);

        $this->assertSame(25, $batch->effectiveMortalityQuantity());
        $this->assertSame(15, $batch->totalReplanted());
        $this->assertSame(10, $batch->replantingDeficit());
    }
}
