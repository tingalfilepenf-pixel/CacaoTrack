<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cacaotrack.operational_submission.enabled' => true]);
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => Role::STAFF,
            'permissions' => ['submit_operations', 'record_operations', 'manage_stock', 'complete_checklist'],
        ]);
    }

    private function batch(User $owner, string $code = 'B001'): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo '.$code]);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $owner->id,
            'batch_code' => $code,
            'total_grafted' => 100,
            'regraft' => 0,
            'available_count' => 100,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function submission(User $submitter, Batch $batch): OperationalSubmission
    {
        return OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $submitter->id,
            'payload' => [
                'batch_id' => $batch->batch_id,
                'quantity' => 5,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ],
        ]);
    }

    public function test_only_a_verify_holder_can_open_the_pending_queue(): void
    {
        $staff = $this->staff();
        $manager = User::factory()->role(Role::MANAGER)->create();
        $submission = $this->submission($staff, $this->batch($staff));

        $this->actingAs($staff)
            ->get(route('operational.submissions.queue'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('operational.submissions.queue'))
            ->assertOk()
            ->assertSee('B001')
            ->assertSee('Review');

        $this->assertTrue($submission->isPending());
    }

    public function test_staff_can_view_their_own_submission_but_not_another_users(): void
    {
        $staff = $this->staff();
        $other = $this->staff();
        $own = $this->submission($staff, $this->batch($staff, 'OWN-1'));
        $foreign = $this->submission($other, $this->batch($other, 'OTHER-1'));

        $this->actingAs($staff)
            ->get(route('operational.submissions.show', $own))
            ->assertOk()
            ->assertSee('OWN-1');

        $this->actingAs($staff)
            ->get(route('operational.submissions.show', $foreign))
            ->assertNotFound();
    }

    public function test_manager_can_review_all_submissions(): void
    {
        $staff = $this->staff();
        $manager = User::factory()->role(Role::MANAGER)->create();
        $submission = $this->submission($staff, $this->batch($staff));

        $this->actingAs($manager)
            ->get(route('operational.submissions.show', $submission))
            ->assertOk()
            ->assertSee('B001')
            ->assertSee('Disease')
            ->assertSee('waiting for Manager verification');
    }

    public function test_queue_status_tabs_switch_between_pending_and_decided_submissions(): void
    {
        $staff = $this->staff();
        $manager = User::factory()->role(Role::MANAGER)->create();
        $this->submission($staff, $this->batch($staff, 'PENDING-1'));
        $approved = $this->submission($staff, $this->batch($staff, 'APPROVED-1'));

        $approved->approve($manager->id);

        $this->actingAs($manager)
            ->get(route('operational.submissions.queue'))
            ->assertOk()
            ->assertSee('PENDING-1')
            ->assertDontSee('APPROVED-1');

        $this->actingAs($manager)
            ->get(route('operational.submissions.queue', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('APPROVED-1')
            ->assertDontSee('PENDING-1');
    }

    public function test_queue_search_and_type_filter_limit_the_cards(): void
    {
        $staff = $this->staff();
        $manager = User::factory()->role(Role::MANAGER)->create();
        $this->submission($staff, $this->batch($staff, 'B001'));
        $this->submission($staff, $this->batch($staff, 'B002'));

        $this->actingAs($manager)
            ->get(route('operational.submissions.queue', ['q' => 'B002']))
            ->assertOk()
            ->assertSee('B002')
            ->assertDontSee('B001');

        $this->actingAs($manager)
            ->get(route('operational.submissions.queue', ['type' => 'stock_out']))
            ->assertOk()
            ->assertSee('No matching submissions')
            ->assertDontSee('B001')
            ->assertDontSee('B002');
    }

    public function test_a_decided_submission_shows_its_decision_and_no_pending_actions(): void
    {
        $staff = $this->staff();
        $manager = User::factory()->role(Role::MANAGER)->create();
        $submission = $this->submission($staff, $this->batch($staff));

        $submission->approve($manager->id);

        $this->actingAs($manager)
            ->get(route('operational.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Decided By')
            ->assertSee('Manager decision')
            ->assertDontSee('Approve Submission')
            ->assertDontSee('Reject Submission');
    }

    public function test_administrator_can_view_all_submissions_read_only(): void
    {
        $staff = $this->staff();
        $admin = User::factory()->admin()->create();
        $submission = $this->submission($staff, $this->batch($staff));

        $this->actingAs($admin)
            ->get(route('operational.submissions.show', $submission))
            ->assertOk()
            ->assertSee('B001')
            ->assertDontSee('Approve Submission')
            ->assertDontSee('Reject Submission');
    }
}
