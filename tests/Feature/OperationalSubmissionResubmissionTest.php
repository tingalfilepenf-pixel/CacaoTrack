<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionResubmissionTest extends TestCase
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

    private function rejectedSubmission(User $staff, Batch $batch): OperationalSubmission
    {
        $submission = OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $staff->id,
            'payload' => [
                'batch_id' => $batch->batch_id,
                'quantity' => 5,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ],
        ]);

        $submission->reject($this->manager()->id, 'First rejection.');

        return $submission->refresh();
    }

    public function test_only_the_original_submitter_can_open_a_resubmission_form(): void
    {
        $staff = $this->staff();
        $other = $this->staff();
        $submission = $this->rejectedSubmission($staff, $this->batch($staff));

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $submission))
            ->assertOk()
            ->assertSee('Resubmit Mortality')
            ->assertSee('100')
            ->assertSee('B001');

        $this->actingAs($other)
            ->get(route('operational.submissions.resubmit.create', $submission))
            ->assertNotFound();
    }

    public function test_a_resubmission_creates_a_new_linked_pending_row_and_leaves_the_original_unchanged(): void
    {
        $staff = $this->staff();
        $batch = $this->batch($staff);
        $original = $this->rejectedSubmission($staff, $batch);

        $this->actingAs($staff)
            ->post(route('operational.submissions.resubmit.mortality', $original), [
                'batch_id' => $batch->batch_id,
                'quantity' => 4,
                'cause' => 'Pest',
                'recorded_at' => '2026-11-04',
            ])
            ->assertSessionHas('success');

        $new = OperationalSubmission::query()
            ->where('resubmission_of_id', $original->submission_id)
            ->firstOrFail();

        $this->assertTrue($new->isPending());
        $this->assertSame($original->submission_id, $new->resubmission_of_id);
        $this->assertSame($staff->id, (int) $new->submitted_by_id);
        $this->assertSame(4, (int) $new->payload['quantity']);
        $this->assertSame('Pest', $new->payload['cause']);

        $original->refresh();
        $this->assertTrue($original->isRejected());
        $this->assertSame('First rejection.', $original->decision_reason);
    }

    public function test_pending_or_approved_submissions_cannot_be_resubmitted(): void
    {
        $staff = $this->staff();
        $batch = $this->batch($staff);

        $pending = OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $pending))
            ->assertNotFound();

        $approved = OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $staff->id,
        ]);
        $approved->approve($this->manager()->id);

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $approved))
            ->assertNotFound();
    }

    public function test_resubmission_chains_are_allowed(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff);
        $first = $this->rejectedSubmission($staff, $batch);

        $second = OperationalSubmission::factory()->resubmissionOf($first)->create([
            'payload' => [
                'batch_id' => $batch->batch_id,
                'quantity' => 3,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ],
        ]);
        $second->reject($manager->id, 'Second rejection.');

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $second->refresh()))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('operational.submissions.resubmit.mortality', $second->refresh()), [
                'batch_id' => $batch->batch_id,
                'quantity' => 2,
                'cause' => 'Disease',
                'recorded_at' => '2026-11-04',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, OperationalSubmission::query()
            ->where('resubmission_of_id', $second->submission_id)
            ->count());
    }
}
