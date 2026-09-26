<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch master data on the revised model (P3): one nullable `scion_variety_id`,
 * no variety/pot columns, live `available_count`, monthly Inventory snapshots.
 * Batches live at `/operational/batches` under `record_operations`.
 */
class BatchManagementTest extends TestCase
{
    use RefreshDatabase;

    private function variety(string $name = 'Criollo'): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => $name]);
    }

    private function payload(?ScionVariety $variety, array $overrides = []): array
    {
        return array_merge([
            'batch_code' => 'B001',
            'scion_variety_id' => $variety?->scion_variety_id,
            'total_grafted' => 200,
            'viable_count' => 180,
            'grafted_date' => '2026-07-15',
            'status' => Batch::STATUS_ACTIVE,
        ], $overrides);
    }

    private function createBatch(
        User $user,
        ?ScionVariety $variety,
        string $code = 'B001',
        string $status = Batch::STATUS_ACTIVE,
    ): Batch {
        return Batch::create([
            'scion_variety_id' => $variety?->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => 200,
            'viable_count' => 180,
            'available_count' => 180,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => $status,
        ]);
    }

    public function test_guests_are_redirected_from_batches_to_login(): void
    {
        $this->get(route('operational.batches'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_batches_page(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $this->createBatch($user, $variety, 'REAL-001');

        $this->actingAs($user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertSee('Batch Directory')
            ->assertSee('REAL-001')
            ->assertSee('Criollo')
            ->assertSee('Total Batches');
    }

    public function test_batch_can_be_created_with_available_count_initialized_from_viable_count(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload($variety))
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('success');

        $batch = Batch::where('batch_code', 'B001')->first();
        $this->assertNotNull($batch);
        $this->assertSame(180, (int) $batch->viable_count);
        $this->assertSame(180, (int) $batch->available_count);
        $this->assertSame($user->id, (int) $batch->user_id);
    }

    public function test_batch_can_be_created_without_a_scion_variety(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload(null))
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('success');

        $this->assertNull(Batch::where('batch_code', 'B001')->first()->scion_variety_id);
    }

    public function test_batch_creation_writes_a_batch_created_activity_row(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload($variety));

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_BATCH_CREATED,
            'batch_id' => null,
        ]);
    }

    public function test_batch_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), [])
            ->assertSessionHasErrors([
                'batch_code', 'total_grafted', 'viable_count', 'grafted_date', 'status',
            ], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_a_duplicate_batch_code(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $this->createBatch($user, $variety, 'DUP-001');

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload($variety, ['batch_code' => 'DUP-001']))
            ->assertSessionHasErrors(['batch_code'], null, 'batch');

        $this->assertSame(1, Batch::count());
    }

    public function test_batch_creation_rejects_viable_count_above_total_grafted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload(null, [
                'total_grafted' => 100,
                'viable_count' => 150,
            ]))
            ->assertSessionHasErrors(['viable_count'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_a_future_grafted_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload(null, [
                'grafted_date' => now()->addDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors(['grafted_date'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_an_invalid_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload(null, ['status' => 'archived']))
            ->assertSessionHasErrors(['status'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_an_unknown_scion_variety(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload(null, ['scion_variety_id' => 9999]))
            ->assertSessionHasErrors(['scion_variety_id'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_an_archived_scion_variety(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $variety->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('operational.batches.manage.store'), $this->payload($variety))
            ->assertSessionHasErrors(['scion_variety_id'], null, 'batch');
    }

    public function test_batch_can_be_updated_and_resyncs_available_count_while_unused(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $batch = $this->createBatch($user, $variety);

        $this->actingAs($user)
            ->put(route('operational.batches.update', $batch), [
                'batch_code' => 'B001-UPDATED',
                'scion_variety_id' => $variety->scion_variety_id,
                'total_grafted' => 250,
                'viable_count' => 220,
                'grafted_date' => '2026-07-20',
                'status' => Batch::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('B001-UPDATED', $batch->batch_code);
        $this->assertSame(220, (int) $batch->viable_count);
        $this->assertSame(220, (int) $batch->available_count);
        $this->assertSame(Batch::STATUS_INACTIVE, $batch->status);
    }

    public function test_batch_update_locks_counts_once_operational_records_exist(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $batch = $this->createBatch($user, $variety);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('operational.batches.update', $batch), $this->payload($variety, [
                'total_grafted' => 999,
                'viable_count' => 999,
            ]))
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame(200, (int) $batch->total_grafted);
        $this->assertSame(180, (int) $batch->viable_count);
        $this->assertSame(180, (int) $batch->available_count);
    }

    public function test_batch_master_fields_remain_editable_when_used(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $batch = $this->createBatch($user, $variety);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('operational.batches.update', $batch), $this->payload($variety, [
                'batch_code' => 'B001-RENAMED',
                'status' => Batch::STATUS_INACTIVE,
            ]))
            ->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('B001-RENAMED', $batch->batch_code);
        $this->assertSame(Batch::STATUS_INACTIVE, $batch->status);
    }

    public function test_batch_with_operational_records_can_still_be_archived_and_restored(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $batch = $this->createBatch($user, $variety);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('operational.batches.archive', $batch))
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('success');

        $this->assertNotNull($batch->fresh()->archived_at);
        $this->assertDatabaseHas('mortality_records', ['batch_id' => $batch->batch_id]);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => ActivityLog::TYPE_BATCH_ARCHIVED]);
    }

    public function test_archived_batch_is_hidden_from_the_active_listing_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $batch = $this->createBatch($user, $variety);
        $batch->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertDontSee('B001');

        $this->actingAs($user)
            ->get(route('operational.batches', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('B001');

        $this->actingAs($user)
            ->post(route('operational.batches.restore', $batch))
            ->assertRedirect(route('operational.batches', ['tab' => 'archived']))
            ->assertSessionHas('success');

        $this->assertNull($batch->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => ActivityLog::TYPE_BATCH_RESTORED]);
    }

    public function test_batches_page_filters_by_search(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $this->createBatch($user, $variety, 'ALPHA-1');
        $this->createBatch($user, $variety, 'BETA-2');

        $this->actingAs($user)
            ->get(route('operational.batches', ['search' => 'ALPHA']))
            ->assertOk()
            ->assertSee('ALPHA-1')
            ->assertDontSee('BETA-2');
    }

    public function test_batches_page_filters_by_status(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $this->createBatch($user, $variety, 'ACTIVE-1', Batch::STATUS_ACTIVE);
        $this->createBatch($user, $variety, 'INACTIVE-2', Batch::STATUS_INACTIVE);

        $this->actingAs($user)
            ->get(route('operational.batches', ['status' => Batch::STATUS_INACTIVE]))
            ->assertOk()
            ->assertSee('INACTIVE-2')
            ->assertDontSee('ACTIVE-1');
    }

    public function test_batches_page_rejects_invalid_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.batches', ['status' => 'bogus', 'scion_variety_id' => 9999]))
            ->assertSessionHasErrors(['status', 'scion_variety_id']);
    }

    public function test_batches_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertSee('No batches found for the current filters.');
    }
}
