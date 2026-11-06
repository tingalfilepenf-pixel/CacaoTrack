<?php

namespace Tests\Feature;

use App\Actions\RegraftBatch;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use App\Support\StockBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BatchCreationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cacaotrack.operational_submission.enabled' => true,
            'cacaotrack.operational_submission.modules.batch_creation' => true,
        ]);
    }

    private function staff(): User
    {
        return $this->userWithPermissions([
            'submit_operations',
            'record_operations',
            'manage_stock',
        ]);
    }

    private function manager(): User
    {
        return User::factory()->role(Role::MANAGER)->create();
    }

    private function supplier(string $name = 'Supplier A'): Supplier
    {
        return Supplier::create([
            'supplier_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    private function variety(string $name = 'Batangas'): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => $name]);
    }

    private function stockIn(int $quantity, ?ScionVariety $variety = null): StockIn
    {
        return StockIn::create([
            'supplier_id' => $this->supplier('Supplier '.uniqid())->supplier_id,
            'scion_variety_id' => $variety?->scion_variety_id,
            'user_id' => $this->staff()->id,
            'quantity' => $quantity,
            'unit_price' => $variety ? 10.00 : null,
            'origin' => 'Test Origin',
            'date_received' => '2026-11-04',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ownPayload(ScionVariety $variety, array $overrides = []): array
    {
        return array_merge([
            'source' => Batch::SOURCE_OWN,
            'seedlings_used' => 80,
            'scion_variety_id' => $variety->scion_variety_id,
            'scion_used' => 30,
            'plants_grafted' => 25,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPayload(Supplier $supplier, ScionVariety $variety, array $overrides = []): array
    {
        return array_merge([
            'source' => Batch::SOURCE_SUPPLIER,
            'supplier_id' => $supplier->supplier_id,
            'delivery_date' => '2026-11-05',
            'scion_variety_id' => $variety->scion_variety_id,
            'plants_delivered' => 120,
        ], $overrides);
    }

    private function submit(User $staff, array $payload): OperationalSubmission
    {
        $this->actingAs($staff)->post(route('operational.submissions.batch-creation.store'), $payload);

        return OperationalSubmission::latest('submission_id')->firstOrFail();
    }

    public function test_own_nursery_approval_creates_one_batch_and_deducts_exactly_once(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $submission = $this->submit($staff, $this->ownPayload($variety));

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('success');

        $batch = Batch::firstOrFail();

        $this->assertSame(Batch::SOURCE_OWN, $batch->source);
        $this->assertSame('B001', $batch->batch_code);
        $this->assertSame($variety->scion_variety_id, (int) $batch->scion_variety_id);
        $this->assertSame(80, (int) $batch->seedlings_used);
        $this->assertSame(30, (int) $batch->scion_used);
        $this->assertSame(25, (int) $batch->total_grafted);
        $this->assertSame(25, (int) $batch->available_count);
        $this->assertSame(now()->toDateString(), $batch->grafted_date->format('Y-m-d'));
        $this->assertSame(Batch::STATUS_ACTIVE, $batch->status);
        $this->assertSame($staff->id, (int) $batch->user_id);

        $this->assertSame(OperationalSubmission::STATUS_APPROVED, $submission->fresh()->status);
        $this->assertSame($manager->id, (int) $submission->fresh()->decided_by_id);

        $this->assertDatabaseHas('operational_submission_records', [
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_BATCH,
            'record_id' => $batch->batch_id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_BATCH_CREATED,
            'batch_id' => $batch->batch_id,
            'user_id' => $staff->id,
            'description' => 'Created batch B001.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_OPERATIONAL_SUBMISSION_APPROVED,
            'batch_id' => $batch->batch_id,
            'user_id' => $manager->id,
        ]);

        $this->assertSame(20, StockBalances::seedlingsAvailable());
        $this->assertSame(10, $variety->fresh()->availableScionQuantity());

        // A second approval is refused and never creates another batch.
        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('error');

        $this->assertSame(1, Batch::count());
        $this->assertSame(20, StockBalances::seedlingsAvailable());
        $this->assertSame(10, $variety->fresh()->availableScionQuantity());
    }

    public function test_supplier_delivery_approval_creates_one_batch_and_deducts_nothing(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $variety = $this->variety('No Stock In Variety');

        $submission = $this->submit($staff, $this->supplierPayload($supplier, $variety));

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('success');

        $batch = Batch::firstOrFail();

        $this->assertSame(Batch::SOURCE_SUPPLIER, $batch->source);
        $this->assertSame('S001', $batch->batch_code);
        $this->assertSame($supplier->supplier_id, (int) $batch->supplier_id);
        $this->assertSame($variety->scion_variety_id, (int) $batch->scion_variety_id);
        $this->assertNull($batch->seedlings_used);
        $this->assertNull($batch->scion_used);
        $this->assertSame(120, (int) $batch->total_grafted);
        $this->assertSame(120, (int) $batch->available_count);
        $this->assertSame('2026-11-05', $batch->grafted_date->format('Y-m-d'));
        $this->assertSame($staff->id, (int) $batch->user_id);

        $this->assertSame(0, StockBalances::seedlingsUsed());
        $this->assertSame(0, $variety->fresh()->usedScionQuantity());
        $this->assertDatabaseHas('operational_submission_records', [
            'submission_id' => $submission->submission_id,
            'record_type' => OperationalSubmissionRecord::RECORD_TYPE_BATCH,
            'record_id' => $batch->batch_id,
        ]);
    }

    public function test_approval_rejects_with_d6_when_the_stock_was_used_up_after_submission(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $first = $this->submit($staff, $this->ownPayload($variety));
        $second = $this->submit($staff, $this->ownPayload($variety, [
            'seedlings_used' => 70,
            'scion_used' => 25,
            'plants_grafted' => 20,
        ]));

        $this->assertSame(2, OperationalSubmission::count());

        $this->actingAs($manager)->post(route('operational.submissions.approve', $first));

        $this->assertSame(1, Batch::count());
        $this->assertSame(80, (int) Batch::first()->seedlings_used);
        $this->assertSame(30, (int) Batch::first()->scion_used);
        $this->assertSame(OperationalSubmission::STATUS_PENDING, $second->fresh()->status);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $second))
            ->assertRedirect(route('operational.submissions.show', $second))
            ->assertSessionHas('error');

        $second->refresh();

        $this->assertSame(OperationalSubmission::STATUS_REJECTED, $second->status);
        $this->assertTrue((bool) $second->decision_automatic);
        $this->assertStringContainsString('Only 20 seedlings are available. Enter 20 or fewer.', (string) $second->decision_reason);
        $this->assertSame(1, Batch::count());
        $this->assertSame(0, OperationalSubmissionRecord::query()
            ->where('submission_id', $second->submission_id)
            ->count());
    }

    public function test_an_archived_supplier_at_approval_uses_the_owner_message(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $variety = $this->variety();

        $submission = $this->submit($staff, $this->supplierPayload($supplier, $variety));
        $supplier->update(['archived_at' => now()]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertSessionHas('error');

        $submission->refresh();

        $this->assertSame(OperationalSubmission::STATUS_REJECTED, $submission->status);
        $this->assertTrue((bool) $submission->decision_automatic);
        $this->assertStringContainsString(
            'The supplier is no longer active. Resubmit with another supplier.',
            (string) $submission->decision_reason,
        );
        $this->assertSame(0, Batch::count());
    }

    public function test_an_archived_scion_variety_at_approval_uses_the_owner_message(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $variety = $this->variety();

        $submission = $this->submit($staff, $this->supplierPayload($supplier, $variety));
        $variety->update(['archived_at' => now()]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertSessionHas('error');

        $submission->refresh();

        $this->assertSame(OperationalSubmission::STATUS_REJECTED, $submission->status);
        $this->assertTrue((bool) $submission->decision_automatic);
        $this->assertStringContainsString(
            'The scion variety is no longer active. Resubmit with another variety.',
            (string) $submission->decision_reason,
        );
        $this->assertSame(0, Batch::count());
    }

    public function test_a_code_lock_timeout_leaves_the_submission_pending(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $submission = $this->submit($staff, $this->ownPayload($variety));

        $config = config('database.connections.mysql');
        $pdo = new \PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
            $config['username'],
            $config['password'],
        );

        $pdo->query("SELECT GET_LOCK('cacaotrack:batch_code:B', 0)");

        try {
            config(['cacaotrack.operational_submission.batch_code_lock_timeout' => 0]);

            $this->actingAs($manager)
                ->post(route('operational.submissions.approve', $submission))
                ->assertRedirect(route('operational.submissions.show', $submission))
                ->assertSessionHas('error');

            $this->assertSame(OperationalSubmission::STATUS_PENDING, $submission->fresh()->status);
            $this->assertSame(0, Batch::count());
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('cacaotrack:batch_code:B')");
        }

        // After the lock is released the submission can still be approved.
        $this->actingAs($manager)->post(route('operational.submissions.approve', $submission->fresh()));

        $this->assertSame(OperationalSubmission::STATUS_APPROVED, $submission->fresh()->status);
        $this->assertSame(1, Batch::count());
    }

    public function test_the_code_lock_is_taken_before_the_submission_and_stock_locks(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $submission = $this->submit($staff, $this->ownPayload($variety));

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $this->actingAs($manager)->post(route('operational.submissions.approve', $submission));

        $queries = collect(DB::connection()->getQueryLog())
            ->pluck('query')
            ->map(fn (string $query) => strtolower($query))
            ->values();

        DB::connection()->disableQueryLog();

        $codeLock = $queries->search(fn (string $query) => str_contains($query, 'get_lock'));
        $submissionLock = $queries->search(fn (string $query) => str_contains($query, 'operational_submissions') && str_contains($query, 'for update'));
        $poolLock = $queries->search(fn (string $query) => str_contains($query, 'stock_ins') && str_contains($query, 'for update'));
        $varietyLock = $queries->search(fn (string $query) => str_contains($query, 'scion_varieties') && str_contains($query, 'for update'));

        $this->assertNotFalse($codeLock);
        $this->assertNotFalse($submissionLock);
        $this->assertNotFalse($poolLock);
        $this->assertNotFalse($varietyLock);
        $this->assertLessThan($submissionLock, $codeLock);
        $this->assertLessThan($poolLock, $submissionLock);
        $this->assertLessThan($varietyLock, $poolLock);

        $batch = Batch::firstOrFail();

        // A later RegraftBatch (batch then variety) must not deadlock with the
        // approval's order; a sequential call is enough to catch an inversion
        // in the shared lock helper.
        $subBatch = app(RegraftBatch::class)->execute(
            $batch,
            5,
            $variety->scion_variety_id,
            now()->toDateString(),
            null,
            $manager->id,
        );

        $this->assertSame('B001-R1', $subBatch->batch_code);
        $this->assertSame(20, (int) $batch->fresh()->available_count);
    }

    public function test_batch_creation_visibility_matches_the_submission_policy(): void
    {
        $staff = $this->staff();
        $otherStaff = $this->staff();
        $manager = $this->manager();
        $admin = User::factory()->admin()->create();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $submission = $this->submit($staff, $this->ownPayload($variety));

        $this->actingAs($otherStaff)
            ->get(route('operational.submissions.show', $submission))
            ->assertNotFound();

        $this->actingAs($manager)
            ->get(route('operational.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Approve Submission');

        $this->actingAs($admin)
            ->get(route('operational.submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Approve Submission')
            ->assertDontSee('Reject Submission');
    }

    public function test_a_rejected_batch_creation_can_be_resubmitted_by_the_original_submitter(): void
    {
        $staff = $this->staff();
        $otherStaff = $this->staff();
        $manager = $this->manager();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $submission = $this->submit($staff, $this->ownPayload($variety));

        $this->actingAs($manager)
            ->post(route('operational.submissions.reject', $submission), ['reason' => 'Wrong quantity.']);

        $this->actingAs($otherStaff)
            ->get(route('operational.submissions.resubmit.create', $submission))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $submission))
            ->assertOk()
            ->assertSee('Resubmit Batch Creation');

        $this->actingAs($staff)
            ->post(route('operational.submissions.resubmit.batch-creation', $submission), $this->ownPayload($variety, [
                'seedlings_used' => 40,
                'scion_used' => 20,
                'plants_grafted' => 20,
            ]))
            ->assertRedirect();

        $new = OperationalSubmission::query()->latest('submission_id')->firstOrFail();

        $this->assertSame($submission->submission_id, (int) $new->resubmission_of_id);
        $this->assertSame(OperationalSubmission::TYPE_BATCH_CREATION, $new->submission_type);
        $this->assertSame(40, $new->payload['seedlings_used']);
        $this->assertSame(OperationalSubmission::STATUS_REJECTED, $submission->fresh()->status);
    }
}
