<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\PotType;
use App\Models\Scion;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function createBatch(User $user, int $viable = 100, string $code = 'B001'): Batch
    {
        $variety = Variety::create(['variety_name' => 'Criollo']);
        $scion = Scion::create(['scion_name' => 'ICS-1']);
        $potType = PotType::create(['pot_name' => 'Standard Polybag']);

        return Batch::create([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $potType->pot_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => $viable,
            'viable_count' => $viable,
            'available_count' => $viable,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_guests_are_redirected_from_activity_history_to_login(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_activity_history_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertSee('Activity History')
            ->assertSee('Recent Activities');
    }

    public function test_activity_history_shows_real_records(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        ActivityLog::record(
            $batch->batch_id,
            ActivityLog::TYPE_MORTALITY_RECORDED,
            'Recorded 25 mortality for B001 due to Disease.',
            $user->id,
        );

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertSee('Recorded 25 mortality for B001 due to Disease.')
            ->assertSee('Mortality Recorded')
            ->assertSee('B001');
    }

    public function test_activity_history_can_be_filtered_by_type(): void
    {
        $user = User::factory()->create();

        ActivityLog::record(null, ActivityLog::TYPE_TASK_COMPLETED, 'Completed task: Inspect seedlings.', $user->id);
        ActivityLog::record(null, ActivityLog::TYPE_REPORT_GENERATED, 'Generated inventory report.', $user->id);

        $this->actingAs($user)
            ->get(route('activity.index', ['activity_type' => ActivityLog::TYPE_TASK_COMPLETED]))
            ->assertOk()
            ->assertSee('Completed task: Inspect seedlings.')
            ->assertDontSee('Generated inventory report.');
    }

    public function test_activity_history_can_be_filtered_by_user(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $other = User::factory()->create(['name' => 'Staff User']);

        ActivityLog::record(null, ActivityLog::TYPE_TASK_COMPLETED, 'Completed task: Admin action.', $admin->id);
        ActivityLog::record(null, ActivityLog::TYPE_TASK_COMPLETED, 'Completed task: Staff action.', $other->id);

        // Assert on the filtered page dataset rather than raw HTML: the navbar
        // notification bell also renders real notification text on every page
        // (ERD §2.19), so assertDontSee() is no longer page-scoped.
        $response = $this->actingAs($admin)
            ->get(route('activity.index', ['user_id' => $admin->id]));

        $response->assertOk();
        $response->assertViewHas('activities', fn ($paginator) => $paginator
            ->pluck('description')
            ->contains(fn (string $description) => str_contains($description, 'Admin action.'))
            && ! $paginator->pluck('description')
                ->contains(fn (string $description) => str_contains($description, 'Staff action.')));
    }

    public function test_activity_history_can_be_filtered_by_batch(): void
    {
        $user = User::factory()->create();
        $b1 = $this->createBatch($user, 100, 'B001');
        $b2 = $this->createBatch($user, 100, 'B002');

        ActivityLog::record($b1->batch_id, ActivityLog::TYPE_MORTALITY_RECORDED, 'Recorded mortality for B001.', $user->id);
        ActivityLog::record($b2->batch_id, ActivityLog::TYPE_MORTALITY_RECORDED, 'Recorded mortality for B002.', $user->id);

        $this->actingAs($user)
            ->get(route('activity.index', ['batch_id' => $b1->batch_id]))
            ->assertOk()
            ->assertSee('Recorded mortality for B001.')
            ->assertDontSee('Recorded mortality for B002.');
    }

    public function test_activity_history_can_be_filtered_by_date_range(): void
    {
        $user = User::factory()->create();

        ActivityLog::record(null, ActivityLog::TYPE_TASK_COMPLETED, 'Recent activity.', $user->id, '2026-09-10');
        ActivityLog::record(null, ActivityLog::TYPE_TASK_COMPLETED, 'Old activity.', $user->id, '2026-08-01');

        $this->actingAs($user)
            ->get(route('activity.index', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('Recent activity.')
            ->assertDontSee('Old activity.');
    }

    public function test_activity_history_search_matches_description(): void
    {
        $user = User::factory()->create();

        ActivityLog::record(null, ActivityLog::TYPE_TASK_COMPLETED, 'Completed task: Inspect grafts.', $user->id);
        ActivityLog::record(null, ActivityLog::TYPE_REPORT_GENERATED, 'Generated gap report.', $user->id);

        $this->actingAs($user)
            ->get(route('activity.index', ['search' => 'grafts']))
            ->assertOk()
            ->assertSee('Completed task: Inspect grafts.')
            ->assertDontSee('Generated gap report.');
    }

    public function test_activity_history_rejects_invalid_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activity.index', ['user_id' => 999]))
            ->assertSessionHasErrors('user_id');

        $this->actingAs($user)
            ->get(route('activity.index', ['batch_id' => 999]))
            ->assertSessionHasErrors('batch_id');

        $this->actingAs($user)
            ->get(route('activity.index', ['activity_type' => 'not_a_type']))
            ->assertSessionHasErrors('activity_type');

        $this->actingAs($user)
            ->get(route('activity.index', ['date_from' => '2026-09-10', 'date_to' => '2026-09-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_activity_history_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertSee('No activity records found');
    }

    public function test_record_creates_an_activity_log_with_correct_fields(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $log = ActivityLog::record(
            $batch->batch_id,
            ActivityLog::TYPE_STOCK_IN_RECORDED,
            'Received 100 trees from supplier for B001.',
            $user->id,
            '2026-09-12',
        );

        $this->assertDatabaseHas('activity_logs', [
            'activity_id' => $log->activity_id,
            'user_id' => $user->id,
            'batch_id' => $batch->batch_id,
            'activity_type' => ActivityLog::TYPE_STOCK_IN_RECORDED,
            'description' => 'Received 100 trees from supplier for B001.',
        ]);

        $this->assertSame('Stock In Recorded', $log->typeLabel());
        $this->assertSame('bg-info text-dark', $log->typeBadgeClass());
    }

    public function test_record_rejects_an_unknown_activity_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ActivityLog::record(null, 'made_up_type', 'Should fail.');
    }

    public function test_activity_log_exposes_user_and_batch_relations(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $log = ActivityLog::record($batch->batch_id, ActivityLog::TYPE_REPLANTING_RECORDED, 'Recorded replanting.', $user->id);

        $this->assertTrue($log->user->is($user));
        $this->assertTrue($log->batch->is($batch));
    }

    public function test_recording_mortality_writes_an_activity_log(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);

        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $batch->batch_id,
            'quantity' => 10,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ])->assertRedirect(route('operational.mortality'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'batch_id' => $batch->batch_id,
            'activity_type' => ActivityLog::TYPE_MORTALITY_RECORDED,
            'description' => 'Recorded 10 mortality for B001 due to Disease.',
        ]);
    }

    public function test_recording_stock_in_writes_an_activity_log(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user);
        $supplier = Supplier::create([
            'supplier_name' => 'Davao Cacao Growers',
            'contact_number' => '0917-111-2233',
            'address' => 'Calinan, Davao City',
        ]);

        $this->actingAs($user)->post(route('inventory.stock-in.store'), [
            'supplier_id' => $supplier->supplier_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 100,
            'date_received' => '2026-09-12',
        ])->assertRedirect(route('inventory.stock-in'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'batch_id' => $batch->batch_id,
            'activity_type' => ActivityLog::TYPE_STOCK_IN_RECORDED,
            'description' => 'Received 100 trees from Davao Cacao Growers for B001.',
        ]);
    }

    public function test_generating_a_report_writes_an_activity_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('analytics.reports.store'), [
            'analysis_type' => 'gap_report',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'batch_id' => null,
            'activity_type' => ActivityLog::TYPE_REPORT_GENERATED,
        ]);
    }
}
