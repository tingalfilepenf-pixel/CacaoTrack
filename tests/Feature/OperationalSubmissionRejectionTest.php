<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\MortalityRecord;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OperationalSubmissionRejectionTest extends TestCase
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

    private function batch(User $owner, string $code = 'B001', int $available = 100): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo '.$code]);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $owner->id,
            'batch_code' => $code,
            'total_grafted' => $available,
            'regraft' => 0,
            'available_count' => $available,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function mortalitySubmission(User $submitter, Batch $batch, int $quantity = 5): OperationalSubmission
    {
        return OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $submitter->id,
            'payload' => [
                'batch_id' => $batch->batch_id,
                'quantity' => $quantity,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ],
        ]);
    }

    private function stockOutSubmission(User $submitter, Batch $batch, Client $client): OperationalSubmission
    {
        return OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_OUT, [
                'client_id' => $client->client_id,
                'release_date' => '2026-11-04',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots', 'unit_price' => 25],
                ],
            ])
            ->create(['submitted_by_id' => $submitter->id]);
    }

    public function test_manual_rejection_requires_a_written_reason(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $submission = $this->mortalitySubmission($staff, $this->batch($staff));

        $this->actingAs($manager)
            ->post(route('operational.submissions.reject', $submission), ['reason' => ''])
            ->assertSessionHasErrors(['reason'], null, 'submission_rejection');

        $this->assertTrue($submission->fresh()->isPending());
        $this->assertSame(0, MortalityRecord::count());
    }

    public function test_manual_rejection_records_the_manager_and_writes_no_official_record(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $submission = $this->mortalitySubmission($staff, $this->batch($staff));

        $this->actingAs($manager)
            ->post(route('operational.submissions.reject', $submission), ['reason' => 'Quantity does not match the field record.'])
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('success');

        $submission->refresh();

        $this->assertTrue($submission->isRejected());
        $this->assertFalse($submission->decision_automatic);
        $this->assertSame($manager->id, (int) $submission->decided_by_id);
        $this->assertSame('Quantity does not match the field record.', $submission->decision_reason);
        $this->assertSame(0, MortalityRecord::count());
        $this->assertSame(0, OperationalSubmissionRecord::count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $manager->id,
            'activity_type' => ActivityLog::TYPE_OPERATIONAL_SUBMISSION_REJECTED,
        ]);
    }

    public function test_approval_revalidation_failure_automatically_rejects_a_mortality_submission(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff);
        $submission = $this->mortalitySubmission($staff, $batch, 50);

        $batch->update(['archived_at' => now()]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('error');

        $submission->refresh();

        $this->assertTrue($submission->isRejected());
        $this->assertTrue($submission->decision_automatic);
        $this->assertSame($manager->id, (int) $submission->decided_by_id);
        $this->assertStringContainsString('Automatic rejection', (string) $submission->decision_reason);
        $this->assertStringContainsString('no longer active', (string) $submission->decision_reason);
        $this->assertSame(0, MortalityRecord::count());
        $this->assertSame(0, OperationalSubmissionRecord::count());
    }

    public function test_approval_revalidation_failure_automatically_rejects_when_the_cap_changed(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff, 'B001', 100);
        $submission = $this->mortalitySubmission($staff, $batch, 50);

        $batch->update(['available_count' => 10]);

        $this->actingAs($manager)->post(route('operational.submissions.approve', $submission));

        $submission->refresh();

        $this->assertTrue($submission->isRejected());
        $this->assertTrue($submission->decision_automatic);
        $this->assertStringContainsString('remaining quantity', (string) $submission->decision_reason);
        $this->assertSame(0, MortalityRecord::count());
    }

    public function test_approval_revalidation_failure_automatically_rejects_an_archived_client(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff);
        $client = Client::create([
            'client_name' => 'Client A',
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
        $submission = $this->stockOutSubmission($staff, $batch, $client);

        $client->update(['archived_at' => now()]);

        $this->actingAs($manager)->post(route('operational.submissions.approve', $submission));

        $submission->refresh();

        $this->assertTrue($submission->isRejected());
        $this->assertTrue($submission->decision_automatic);
        $this->assertStringContainsString('archived', (string) $submission->decision_reason);
        $this->assertSame(0, StockOut::count());
        $this->assertSame(0, OperationalSubmissionRecord::count());
    }

    public function test_if_the_automatic_rejection_save_fails_the_submission_stays_pending(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff);
        $submission = $this->mortalitySubmission($staff, $batch, 50);

        $batch->update(['archived_at' => now()]);

        OperationalSubmission::updating(function (OperationalSubmission $model): void {
            if ($model->decision_automatic) {
                throw new RuntimeException('Forced automatic rejection save failure.');
            }
        });

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('error');

        $this->assertTrue($submission->fresh()->isPending());
        $this->assertSame(0, MortalityRecord::count());
        $this->assertSame(0, OperationalSubmissionRecord::count());
    }

    public function test_an_administrator_cannot_reject(): void
    {
        $staff = $this->staff();
        $admin = User::factory()->admin()->create();
        $submission = $this->mortalitySubmission($staff, $this->batch($staff));

        $this->actingAs($admin)
            ->post(route('operational.submissions.reject', $submission), ['reason' => 'No.'])
            ->assertForbidden();

        $this->assertTrue($submission->fresh()->isPending());
    }
}
