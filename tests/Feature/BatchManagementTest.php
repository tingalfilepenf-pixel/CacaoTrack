<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createMasters(): array
    {
        return [
            Variety::create(['variety_name' => 'Criollo', 'description' => 'Test variety']),
            Scion::create(['scion_name' => 'ICS-1', 'description' => 'Test scion']),
            PotType::create(['pot_name' => 'Standard Polybag', 'description' => 'Test pot']),
        ];
    }

    private function payload(User $user, Variety $variety, Scion $scion, PotType $potType, array $overrides = []): array
    {
        return array_merge([
            'batch_code' => 'B001',
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
            'total_grafted' => 200,
            'viable_count' => 180,
            'grafted_date' => '2026-07-15',
            'status' => 'active',
        ], $overrides);
    }

    private function createBatch(User $user, Variety $variety, Scion $scion, PotType $potType, string $code = 'B001', string $status = 'active'): Batch
    {
        return Batch::create([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
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
        $this->get(route('inventory.batches'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_batches_page(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $this->createBatch($user, $variety, $scion, $potType, 'REAL-001');

        $this->actingAs($user)
            ->get(route('inventory.batches'))
            ->assertOk()
            ->assertSee('Batch Directory')
            ->assertSee('REAL-001')
            ->assertSee('Criollo')
            ->assertSee('Total Batches');
    }

    public function test_batch_can_be_created_with_inventory_initialized_from_viable_count(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType))
            ->assertRedirect(route('inventory.batches'))
            ->assertSessionHas('success');

        $batch = Batch::where('batch_code', 'B001')->first();
        $this->assertNotNull($batch);
        $this->assertSame(180, (int) $batch->viable_count);
        $this->assertSame(180, (int) $batch->available_count);
        $this->assertSame($user->id, (int) $batch->user_id);

        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batch->batch_id,
            'current_quantity' => 180,
            'status' => 'active',
        ]);
    }

    public function test_batch_creation_writes_a_batch_created_activity_row(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType));

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_BATCH_CREATED,
            'batch_id' => null,
        ]);
    }

    public function test_batch_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), [])
            ->assertSessionHasErrors([
                'batch_code', 'variety_id', 'scion_id', 'pot_id',
                'total_grafted', 'viable_count', 'grafted_date', 'status',
            ], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_a_duplicate_batch_code(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $this->createBatch($user, $variety, $scion, $potType, 'DUP-001');

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType, ['batch_code' => 'DUP-001']))
            ->assertSessionHasErrors(['batch_code'], null, 'batch');

        $this->assertSame(1, Batch::count());
    }

    public function test_batch_creation_rejects_viable_count_above_total_grafted(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType, [
                'total_grafted' => 100,
                'viable_count' => 150,
            ]))
            ->assertSessionHasErrors(['viable_count'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_a_future_grafted_date(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType, [
                'grafted_date' => now()->addDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors(['grafted_date'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_an_invalid_status(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType, ['status' => 'archived']))
            ->assertSessionHasErrors(['status'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_creation_rejects_invalid_foreign_keys(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType, [
                'variety_id' => 9999,
                'scion_id' => 9999,
                'pot_id' => 9999,
            ]))
            ->assertSessionHasErrors(['variety_id', 'scion_id', 'pot_id'], null, 'batch');

        $this->assertSame(0, Batch::count());
    }

    public function test_batch_can_be_updated_and_resyncs_counts_while_unused(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $batch = $this->createBatch($user, $variety, $scion, $potType);
        Inventory::syncFromBatch($batch, true);

        $this->actingAs($user)
            ->put(route('inventory.batches.update', $batch), [
                'batch_code' => 'B001-UPDATED',
                'variety_id' => $variety->variety_id,
                'scion_id' => $scion->scion_id,
                'pot_id' => $potType->pot_id,
                'total_grafted' => 250,
                'viable_count' => 220,
                'grafted_date' => '2026-07-20',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('inventory.batches'))
            ->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('B001-UPDATED', $batch->batch_code);
        $this->assertSame(220, (int) $batch->viable_count);
        $this->assertSame(220, (int) $batch->available_count);
        $this->assertSame('inactive', $batch->status);

        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batch->batch_id,
            'current_quantity' => 220,
        ]);
    }

    public function test_batch_update_locks_counts_once_operational_records_exist(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $batch = $this->createBatch($user, $variety, $scion, $potType);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('inventory.batches.update', $batch), $this->payload($user, $variety, $scion, $potType, [
                'batch_code' => 'B001',
                'total_grafted' => 999,
                'viable_count' => 999,
            ]))
            ->assertSessionHas('error');

        $batch->refresh();
        $this->assertSame(200, (int) $batch->total_grafted);
        $this->assertSame(180, (int) $batch->viable_count);
    }

    public function test_batch_master_fields_remain_editable_when_used(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $batch = $this->createBatch($user, $variety, $scion, $potType);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('inventory.batches.update', $batch), $this->payload($user, $variety, $scion, $potType, [
                'batch_code' => 'B001-RENAMED',
                'status' => 'inactive',
            ]))
            ->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('B001-RENAMED', $batch->batch_code);
        $this->assertSame('inactive', $batch->status);
    }

    public function test_unused_batch_can_be_archived_and_its_inventory_is_preserved(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();

        $this->actingAs($user)->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType));
        $batch = Batch::where('batch_code', 'B001')->first();

        $this->assertDatabaseHas('inventories', ['batch_id' => $batch->batch_id]);

        $this->actingAs($user)
            ->post(route('inventory.batches.archive', $batch))
            ->assertRedirect(route('inventory.batches'))
            ->assertSessionHas('success');

        $this->assertNotNull($batch->fresh()->archived_at);
        $this->assertDatabaseHas('batches', ['batch_id' => $batch->batch_id]);
        $this->assertDatabaseHas('inventories', ['batch_id' => $batch->batch_id]);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => ActivityLog::TYPE_BATCH_ARCHIVED]);
    }

    public function test_batch_with_operational_records_can_still_be_archived(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $batch = $this->createBatch($user, $variety, $scion, $potType);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 5,
            'cause' => 'Disease',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('inventory.batches.archive', $batch))
            ->assertRedirect(route('inventory.batches'))
            ->assertSessionHas('success');

        $this->assertNotNull($batch->fresh()->archived_at);
        $this->assertDatabaseHas('mortality_records', ['batch_id' => $batch->batch_id]);
    }

    public function test_archived_batch_is_hidden_from_the_active_listing_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $batch = $this->createBatch($user, $variety, $scion, $potType);
        $batch->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.batches'))
            ->assertOk()
            ->assertDontSee('B001');

        $this->actingAs($user)
            ->get(route('inventory.batches', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('B001');

        $this->actingAs($user)
            ->post(route('inventory.batches.restore', $batch))
            ->assertRedirect(route('inventory.batches', ['tab' => 'archived']))
            ->assertSessionHas('success');

        $this->assertNull($batch->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => ActivityLog::TYPE_BATCH_RESTORED]);
    }

    public function test_batch_creation_rejects_an_archived_master_record(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $variety->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.batches.manage.store'), $this->payload($user, $variety, $scion, $potType))
            ->assertSessionHasErrors(['variety_id'], null, 'batch');
    }

    public function test_batches_page_filters_by_search(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $this->createBatch($user, $variety, $scion, $potType, 'ALPHA-1');
        $this->createBatch($user, $variety, $scion, $potType, 'BETA-2');

        $this->actingAs($user)
            ->get(route('inventory.batches', ['search' => 'ALPHA']))
            ->assertOk()
            ->assertSee('ALPHA-1')
            ->assertDontSee('BETA-2');
    }

    public function test_batches_page_filters_by_status(): void
    {
        $user = User::factory()->create();
        [$variety, $scion, $potType] = $this->createMasters();
        $this->createBatch($user, $variety, $scion, $potType, 'ACTIVE-1', 'active');
        $this->createBatch($user, $variety, $scion, $potType, 'INACTIVE-2', 'inactive');

        $this->actingAs($user)
            ->get(route('inventory.batches', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('INACTIVE-2')
            ->assertDontSee('ACTIVE-1');
    }

    public function test_batches_page_rejects_invalid_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.batches', ['status' => 'bogus', 'variety_id' => 9999]))
            ->assertSessionHasErrors(['status', 'variety_id']);
    }

    public function test_batches_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.batches'))
            ->assertOk()
            ->assertSee('No batches found for the current filters.');
    }
}
