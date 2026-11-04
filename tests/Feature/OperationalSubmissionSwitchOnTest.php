<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionSwitchOnTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create([
            'role' => Role::STAFF,
            'permissions' => ['submit_operations', 'record_operations', 'manage_stock', 'complete_checklist'],
        ]);
    }

    private function manager(): User
    {
        return User::factory()->role(Role::MANAGER)->create();
    }

    private function batch(User $owner): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $owner->id,
            'batch_code' => 'B001',
            'total_grafted' => 100,
            'regraft' => 0,
            'available_count' => 100,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_switch_on_disables_both_direct_pilot_writes_together(): void
    {
        config(['cacaotrack.operational_submission.enabled' => true]);

        $staff = $this->staff();
        $batch = $this->batch($staff);

        $this->actingAs($staff)
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 1,
                'cause' => 'Disease',
                'recorded_at' => '2026-11-04',
            ])
            ->assertNotFound();

        $this->actingAs($staff)
            ->post(route('inventory.stock-out.store'), [
                'client_id' => 1,
                'release_date' => '2026-11-04',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 1, 'unit' => 'pots'],
                ],
            ])
            ->assertNotFound();
    }

    public function test_switch_on_restricts_staff_from_general_mortality_and_stock_out_history(): void
    {
        config(['cacaotrack.operational_submission.enabled' => true]);

        $staff = $this->staff();
        $this->batch($staff);

        $this->actingAs($staff)->get(route('operational.mortality'))->assertForbidden();
        $this->actingAs($staff)->get(route('inventory.stock-out'))->assertForbidden();

        // The submission forms remain the Staff batch-selection path.
        $this->actingAs($staff)
            ->get(route('operational.submissions.mortality.create'))
            ->assertOk()
            ->assertSee('B001');
    }

    public function test_manager_pending_count_appears_on_dashboard_and_sidebar_only_when_switch_is_on(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff);

        OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $staff->id,
            'payload' => [
                'batch_id' => $batch->batch_id,
                'quantity' => 1,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ],
        ]);

        config(['cacaotrack.operational_submission.enabled' => false]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pending submissions:')
            ->assertDontSee('Submission Queue');

        config(['cacaotrack.operational_submission.enabled' => true]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pending submissions:')
            ->assertSee('Submission Queue')
            ->assertSee('Review Queue');
    }
}
