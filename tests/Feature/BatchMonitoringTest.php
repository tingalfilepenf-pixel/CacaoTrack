<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchMonitoring;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, array $overrides = []): Batch
    {
        $variety = Variety::create(['variety_name' => 'Criollo']);
        $scion = Scion::create(['scion_name' => 'Scion', 'quantity' => 1000]);
        $potType = PotType::create(['pot_name' => 'Standard Polybag']);

        return Batch::create(array_merge([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => 100,
            'viable_count' => 100,
            'available_count' => 100,
            'grafted_date' => '2026-08-01',
            'monitoring_start_date' => '2026-09-01',
            'created_at' => now(),
            'status' => 'active',
        ], $overrides));
    }

    public function test_guests_are_redirected_from_batch_monitoring_to_login(): void
    {
        $this->get(route('operational.batch-monitoring'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_batch_monitoring_page(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user);

        $this->actingAs($user)
            ->get(route('operational.batch-monitoring'))
            ->assertOk()
            ->assertSee('Batch Monitoring Status')
            ->assertSee('B001')
            ->assertSee('VARIETY')
            ->assertSee('START DATE')
            ->assertSee('ALIVE PLANTS')
            ->assertDontSee('SURVIVAL RATE');
    }

    public function test_page_displays_the_current_alive_plant_count(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, ['viable_count' => 88, 'available_count' => 88]);

        $this->actingAs($user)
            ->get(route('operational.batch-monitoring'))
            ->assertOk()
            ->assertSee('ALIVE PLANTS')
            ->assertSee('88');
    }

    public function test_alive_plants_drop_when_mortality_is_recorded(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-02',
        ]);

        $this->assertSame(75, $batch->fresh()->alivePlants());

        $this->actingAs($user)
            ->get(route('operational.batch-monitoring', ['as_of' => '2026-09-05']))
            ->assertOk()
            ->assertSee('75');
    }

    public function test_status_is_pending_until_the_21_day_lifecycle_completes(): void
    {
        $user = User::factory()->create();

        // No monitoring start date -> still Pending.
        $notStarted = $this->createBatch($user, ['batch_code' => 'B-NONE', 'monitoring_start_date' => null]);
        $this->assertSame(
            BatchMonitoring::STATUS_PENDING,
            BatchMonitoring::statusFor($notStarted, '2026-09-08'),
        );

        // Day 21 is the last Pending day...
        $batch = $this->createBatch($user, ['batch_code' => 'B-21']);
        $this->assertSame(BatchMonitoring::STATUS_PENDING, BatchMonitoring::statusFor($batch, '2026-09-21'));

        // ...and the batch automatically becomes Complete once the full
        // 21-day lifecycle has elapsed (21 days after the start date).
        $this->assertSame(BatchMonitoring::STATUS_COMPLETE, BatchMonitoring::statusFor($batch, '2026-09-22'));
    }

    public function test_page_status_auto_completes_after_21_days(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user);

        $this->actingAs($user)
            ->get(route('operational.batch-monitoring', ['as_of' => '2026-09-22']))
            ->assertOk()
            ->assertSee('Complete');
    }

    public function test_lifecycle_day_and_remaining_days_are_derived(): void
    {
        $this->assertSame(8, BatchMonitoring::monitoringDay('2026-09-01', '2026-09-08'));
        $this->assertSame(13, BatchMonitoring::remainingDays('2026-09-01', '2026-09-08'));
        $this->assertSame(1, BatchMonitoring::monitoringDay('2026-09-01', '2026-09-01'));
        $this->assertSame(0, BatchMonitoring::remainingDays('2026-09-21', '2026-10-12'));
        $this->assertNull(BatchMonitoring::monitoringDay(null, '2026-09-08'));
    }

    public function test_user_can_start_monitoring_and_double_start_is_rejected(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, ['monitoring_start_date' => null]);
        $date = now()->subDays(4)->toDateString();

        $this->actingAs($user)
            ->post(route('operational.batch-monitoring.batches.start', ['batch' => $batch->batch_id]), [
                'monitoring_start_date' => $date,
            ])
            ->assertRedirect(route('operational.batch-monitoring'));

        $this->assertDatabaseHas('batches', [
            'batch_id' => $batch->batch_id,
            'monitoring_start_date' => $date,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'batch_id' => $batch->batch_id,
            'activity_type' => 'batch_monitoring_started',
        ]);

        $this->actingAs($user)
            ->post(route('operational.batch-monitoring.batches.start', ['batch' => $batch->batch_id]), [
                'monitoring_start_date' => now()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('batches', [
            'batch_id' => $batch->batch_id,
            'monitoring_start_date' => $date,
        ]);
    }

    public function test_page_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, ['batch_code' => 'B001']);
        $this->createBatch($user, ['batch_code' => 'B002', 'monitoring_start_date' => null]);

        $this->actingAs($user)
            ->get(route('operational.batch-monitoring', ['status' => 'pending', 'as_of' => '2026-09-22']))
            ->assertOk()
            ->assertSee('B002')
            ->assertSee('Pending');
    }
}
