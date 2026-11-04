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
use App\Models\StockOutDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionApprovalTest extends TestCase
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

    public function test_mortality_approval_creates_one_official_record_attributed_to_the_submitter(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff);
        $submission = $this->mortalitySubmission($staff, $batch, 25);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('success');

        $record = MortalityRecord::firstOrFail();

        $this->assertSame($staff->id, (int) $record->user_id);
        $this->assertSame(25, (int) $record->quantity);
        $this->assertSame(100, (int) $batch->fresh()->available_count);

        $submission->refresh();
        $this->assertTrue($submission->isApproved());
        $this->assertSame($manager->id, (int) $submission->decided_by_id);
        $this->assertFalse($submission->decision_automatic);

        $this->assertDatabaseHas('operational_submission_records', [
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_MORTALITY,
            'record_id' => $record->mortality_id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'activity_type' => ActivityLog::TYPE_MORTALITY_RECORDED,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $manager->id,
            'activity_type' => ActivityLog::TYPE_OPERATIONAL_SUBMISSION_APPROVED,
        ]);
    }

    public function test_mortality_approval_cannot_be_applied_twice(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $submission = $this->mortalitySubmission($staff, $this->batch($staff));

        $this->actingAs($manager)->post(route('operational.submissions.approve', $submission));
        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('error');

        $this->assertSame(1, MortalityRecord::count());
        $this->assertSame(1, OperationalSubmissionRecord::count());
    }

    public function test_stock_out_approval_creates_header_details_links_and_one_balance_decrement(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $batch = $this->batch($staff, 'B001', 100);
        $client = Client::create([
            'client_name' => 'Client A',
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
        $submission = OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_OUT, [
                'client_id' => $client->client_id,
                'release_date' => '2026-11-04',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots', 'unit_price' => 25],
                ],
            ])
            ->create(['submitted_by_id' => $staff->id]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('success');

        $header = StockOut::firstOrFail();
        $detail = StockOutDetail::firstOrFail();

        $this->assertSame($staff->id, (int) $header->user_id);
        $this->assertSame($client->client_id, (int) $header->client_id);
        $this->assertSame(90, (int) $batch->fresh()->available_count);
        $this->assertSame('250.00', (string) $header->total_amount);

        $this->assertDatabaseHas('operational_submission_records', [
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT,
            'record_id' => $header->stock_out_id,
        ]);
        $this->assertDatabaseHas('operational_submission_records', [
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_STOCK_OUT_DETAIL,
            'record_id' => $detail->stock_out_detail_id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'activity_type' => ActivityLog::TYPE_STOCK_OUT_RECORDED,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $manager->id,
            'activity_type' => ActivityLog::TYPE_OPERATIONAL_SUBMISSION_APPROVED,
        ]);
    }

    public function test_a_manager_cannot_approve_their_own_submission(): void
    {
        $manager = $this->manager();
        $submission = $this->mortalitySubmission($manager, $this->batch($manager));

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('error');

        $this->assertTrue($submission->fresh()->isPending());
        $this->assertSame(0, MortalityRecord::count());
    }

    public function test_an_administrator_cannot_approve(): void
    {
        $staff = $this->staff();
        $admin = User::factory()->admin()->create();
        $submission = $this->mortalitySubmission($staff, $this->batch($staff));

        $this->actingAs($admin)
            ->post(route('operational.submissions.approve', $submission))
            ->assertForbidden();

        $this->assertTrue($submission->fresh()->isPending());
    }
}
