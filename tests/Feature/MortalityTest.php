<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Mortality Tracking on the client-approved model: record-only
 * (Mortality_Adjustments was removed), the cap is
 * `max(0, total_grafted - effective mortality)`, and recording NEVER mutates
 * `batches.available_count` or the `regraft` counter.
 */
class MortalityTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, int $totalGrafted = 100): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => $totalGrafted,
            'regraft' => 0,
            'available_count' => $totalGrafted,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
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

    public function test_user_can_record_mortality_without_mutating_the_available_count(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $this->actingAs($user)
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 25,
                'cause' => 'Disease',
                'recorded_at' => '2026-09-01',
            ])
            ->assertRedirect(route('operational.mortality'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mortality_records', [
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 25,
            'cause' => 'Disease',
        ]);

        // Mortality never decrements the live available balance or regraft counter.
        $this->assertSame(100, (int) $batch->fresh()->available_count);
        $this->assertSame(0, (int) $batch->fresh()->regraft);
        $this->assertSame(25, $batch->fresh()->effectiveMortalityQuantity());
        $this->assertSame(75, $batch->fresh()->remainingMortalityCapacity());

        $this->assertDatabaseHas('activity_logs', [
            'batch_id' => $batch->batch_id,
            'activity_type' => ActivityLog::TYPE_MORTALITY_RECORDED,
        ]);
    }

    public function test_mortality_capacity_is_bounded_by_total_grafted(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, totalGrafted: 400);

        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 235,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        // cap = total_grafted (400) - effective mortality (235).
        $this->assertSame(165, $batch->fresh()->remainingMortalityCapacity());
    }

    public function test_mortality_quantity_cannot_exceed_batch_capacity(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, totalGrafted: 100);

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 101,
                'cause' => 'Pest',
                'recorded_at' => '2026-09-01',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'mortality');

        $this->assertDatabaseCount('mortality_records', 0);
    }

    public function test_cumulative_mortality_cannot_exceed_batch_capacity(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, totalGrafted: 100);

        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 90,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->assertSame(10, $batch->fresh()->remainingMortalityCapacity());

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 20,
                'cause' => 'Pest',
                'recorded_at' => '2026-09-02',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'mortality');

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
            ->assertSessionHasErrors(['batch_id', 'quantity'], null, 'mortality');

        $this->actingAs($user)
            ->from(route('operational.mortality'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => 999,
                'quantity' => 5,
                'cause' => '',
                'recorded_at' => '2026-09-01',
            ])
            ->assertSessionHasErrors(['batch_id', 'cause'], null, 'mortality');
    }

    public function test_the_mortality_adjustment_route_no_longer_exists(): void
    {
        // Regression guard: the removed Mortality_Adjustments entity must not
        // reappear as a route.
        $this->assertFalse(Route::has('operational.mortality.records.adjust'));
    }

    public function test_cumulative_records_sum_correctly_for_the_same_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, totalGrafted: 400);

        foreach ([207, 28] as $quantity) {
            $this->actingAs($user)->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => $quantity,
                'cause' => 'Disease',
                'recorded_at' => '2026-09-01',
            ]);
        }

        $this->assertSame(235, $batch->fresh()->effectiveMortalityQuantity());
        $this->assertSame(235, (int) MortalityRecord::where('batch_id', $batch->batch_id)->sum('quantity'));
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

        $this->actingAs($user)
            ->get(route('operational.mortality', ['batch_id' => $batch->batch_id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('Disease')
            ->assertSee('B001');
    }
}
