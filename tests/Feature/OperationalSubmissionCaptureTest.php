<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\MortalityRecord;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cacaotrack.operational_submission.enabled' => true]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function staff(array $permissions = [
        'submit_operations',
        'record_operations',
        'manage_stock',
        'complete_checklist',
    ]): User
    {
        return User::factory()->create([
            'role' => Role::STAFF,
            'permissions' => $permissions,
        ]);
    }

    private function batch(
        User $owner,
        string $code = 'B001',
        string $status = Batch::STATUS_ACTIVE,
        bool $archived = false,
    ): Batch {
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
            'status' => $status,
            'archived_at' => $archived ? now() : null,
        ]);
    }

    private function client(string $name = 'Client A'): Client
    {
        return Client::create([
            'client_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    public function test_submission_routes_are_disabled_when_the_switch_is_off(): void
    {
        config(['cacaotrack.operational_submission.enabled' => false]);

        $staff = $this->staff();

        $this->actingAs($staff)
            ->get(route('operational.submissions.index'))
            ->assertNotFound();
    }

    public function test_staff_sees_only_submission_eligible_batches_in_both_forms(): void
    {
        $staff = $this->staff();
        $this->batch($staff, 'ACTIVE-1');
        $this->batch($staff, 'INACTIVE-1', Batch::STATUS_INACTIVE);
        $this->batch($staff, 'ARCHIVED-1', Batch::STATUS_ACTIVE, true);
        $this->client();

        $this->actingAs($staff)
            ->get(route('operational.submissions.mortality.create'))
            ->assertOk()
            ->assertSee('ACTIVE-1')
            ->assertDontSee('INACTIVE-1')
            ->assertDontSee('ARCHIVED-1');

        $this->actingAs($staff)
            ->get(route('operational.submissions.stock-out.create'))
            ->assertOk()
            ->assertSee('ACTIVE-1')
            ->assertDontSee('INACTIVE-1')
            ->assertDontSee('ARCHIVED-1');
    }

    public function test_a_mortality_submission_is_pending_and_writes_no_official_record(): void
    {
        $staff = $this->staff();
        $batch = $this->batch($staff);

        $this->actingAs($staff)
            ->post(route('operational.submissions.mortality.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 25,
                'cause' => 'Disease',
                'recorded_at' => '2026-11-04',
            ])
            ->assertRedirect(route('operational.submissions.index'))
            ->assertSessionHas('success');

        $submission = OperationalSubmission::firstOrFail();

        $this->assertSame(OperationalSubmission::TYPE_MORTALITY, $submission->submission_type);
        $this->assertSame(OperationalSubmission::STATUS_PENDING, $submission->status);
        $this->assertSame($staff->id, (int) $submission->submitted_by_id);
        $this->assertSame($batch->batch_id, (int) $submission->subject_batch_id);
        $this->assertSame(25, (int) $submission->payload['quantity']);
        $this->assertSame('Disease', $submission->payload['cause']);
        $this->assertNotNull($submission->submitted_at);

        $this->assertSame(0, MortalityRecord::count());
        $this->assertSame(0, OperationalSubmission::where('status', OperationalSubmission::STATUS_APPROVED)->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'activity_type' => 'operational_submission_submitted',
        ]);
    }

    public function test_a_stock_out_submission_is_pending_and_reserves_no_stock(): void
    {
        $staff = $this->staff();
        $batch = $this->batch($staff);
        $client = $this->client();

        $this->actingAs($staff)
            ->post(route('operational.submissions.stock-out.store'), [
                'client_id' => $client->client_id,
                'release_date' => '2026-11-04',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots', 'unit_price' => 25],
                ],
            ])
            ->assertRedirect(route('operational.submissions.index'))
            ->assertSessionHas('success');

        $submission = OperationalSubmission::firstOrFail();

        $this->assertSame(OperationalSubmission::TYPE_STOCK_OUT, $submission->submission_type);
        $this->assertSame(OperationalSubmission::STATUS_PENDING, $submission->status);
        $this->assertNull($submission->subject_batch_id);
        $this->assertSame($client->client_id, (int) $submission->payload['client_id']);
        $this->assertSame($batch->batch_id, (int) $submission->payload['details'][0]['batch_id']);

        $this->assertSame(0, StockOut::count());
        $this->assertSame(0, StockOutDetail::count());
        $this->assertSame(100, (int) $batch->fresh()->available_count);
    }

    public function test_submission_rejects_inactive_or_archived_batches(): void
    {
        $staff = $this->staff();
        $inactive = $this->batch($staff, 'INACTIVE-1', Batch::STATUS_INACTIVE);
        $archived = $this->batch($staff, 'ARCHIVED-1', Batch::STATUS_ACTIVE, true);
        $client = $this->client();

        foreach ([$inactive, $archived] as $batch) {
            $this->actingAs($staff)
                ->post(route('operational.submissions.mortality.store'), [
                    'batch_id' => $batch->batch_id,
                    'quantity' => 1,
                    'cause' => 'Disease',
                    'recorded_at' => '2026-11-04',
                ])
                ->assertSessionHasErrors(['batch_id'], null, 'submission_mortality');

            $this->actingAs($staff)
                ->post(route('operational.submissions.stock-out.store'), [
                    'client_id' => $client->client_id,
                    'release_date' => '2026-11-04',
                    'details' => [
                        ['batch_id' => $batch->batch_id, 'quantity' => 1, 'unit' => 'pots'],
                    ],
                ])
                ->assertSessionHasErrors(['details.0.batch_id'], null, 'submission_stockout');
        }

        $this->assertSame(0, OperationalSubmission::count());
    }

    public function test_submission_reuses_the_direct_validation_rules(): void
    {
        $staff = $this->staff();
        $batch = $this->batch($staff);
        $client = $this->client();

        $this->actingAs($staff)
            ->post(route('operational.submissions.mortality.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 5,
                'cause' => 'Others',
                'recorded_at' => '2026-11-04',
            ])
            ->assertSessionHasErrors(['cause_other'], null, 'submission_mortality');

        $this->actingAs($staff)
            ->post(route('operational.submissions.stock-out.store'), [
                'client_id' => $client->client_id,
                'release_date' => '2026-11-04',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 1, 'unit' => 'bogus'],
                ],
            ])
            ->assertSessionHasErrors(['details.0.unit'], null, 'submission_stockout');

        $this->assertSame(0, OperationalSubmission::count());
    }

    public function test_a_verify_only_user_cannot_create_submissions(): void
    {
        $manager = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($manager)
            ->get(route('operational.submissions.mortality.create'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('operational.submissions.stock-out.create'))
            ->assertForbidden();
    }

    public function test_staff_index_lists_only_their_own_submissions(): void
    {
        $staff = $this->staff();
        $other = $this->staff();
        $ownBatch = $this->batch($staff, 'OWN-1');
        $otherBatch = $this->batch($other, 'OTHER-1');

        OperationalSubmission::factory()->forBatch($ownBatch)->create([
            'submitted_by_id' => $staff->id,
            'payload' => [
                'batch_id' => $ownBatch->batch_id,
                'quantity' => 1,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ],
        ]);
        OperationalSubmission::factory()->forBatch($otherBatch)->create([
            'submitted_by_id' => $other->id,
            'payload' => [
                'batch_id' => $otherBatch->batch_id,
                'quantity' => 1,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('operational.submissions.index'))
            ->assertOk()
            ->assertSee('OWN-1')
            ->assertDontSee('OTHER-1');
    }
}
