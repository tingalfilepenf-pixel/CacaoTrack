<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\OperationalSubmission;
use App\Models\OperationalSubmissionRecord;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionStockInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cacaotrack.operational_submission.enabled' => true,
            'cacaotrack.operational_submission.modules.stock_in' => true,
        ]);
    }

    private function staff(): User
    {
        return $this->userWithPermissions([
            'submit_operations',
            'manage_stock',
            'record_operations',
            'complete_checklist',
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

    private function variety(string $name = 'Criollo'): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => $name]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function bothEntries(Supplier $supplier, ScionVariety $variety): array
    {
        return [
            'seedling' => [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 500,
                'origin' => 'Seedling Origin',
                'notes' => 'Seedling note.',
                'date_received' => '2026-11-04',
            ],
            'scion' => [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 300,
                'origin' => 'Scion Origin',
                'scion_variety_id' => $variety->scion_variety_id,
                'unit_price' => 12.5,
                'notes' => 'Scion note.',
                'date_received' => '2026-11-04',
            ],
        ];
    }

    private function capture(User $staff, array $entries): OperationalSubmission
    {
        $this->actingAs($staff)
            ->post(route('operational.submissions.stock-in.store'), $entries)
            ->assertRedirect(route('operational.submissions.index'))
            ->assertSessionHas('success');

        return OperationalSubmission::query()->latest('submission_id')->firstOrFail();
    }

    public function test_both_entries_are_frozen_in_one_pending_submission_without_official_rows(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();
        $variety = $this->variety();

        $submission = $this->capture($staff, $this->bothEntries($supplier, $variety));

        $this->assertSame(OperationalSubmission::TYPE_STOCK_IN, $submission->submission_type);
        $this->assertTrue($submission->isPending());
        $this->assertNull($submission->subject_batch_id);
        $this->assertSame(500, (int) $submission->payload['seedling']['quantity']);
        $this->assertSame(300, (int) $submission->payload['scion']['quantity']);
        $this->assertSame($variety->scion_variety_id, (int) $submission->payload['scion']['scion_variety_id']);

        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, OperationalSubmissionRecord::count());
        $this->assertSame(0, ActivityLog::query()->where('activity_type', ActivityLog::TYPE_STOCK_IN_RECORDED)->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'activity_type' => ActivityLog::TYPE_OPERATIONAL_SUBMISSION_SUBMITTED,
        ]);
        $this->assertSame(0, AnalyticsMetrics::stockLedgerGrid()['summary']['seedlings']);
    }

    public function test_a_blank_tab_is_ignored_and_only_the_populated_entry_is_frozen(): void
    {
        $staff = $this->staff();
        $variety = $this->variety();

        $submission = $this->capture($staff, [
            'seedling' => [
                'supplier_id' => '',
                'quantity' => '',
                'origin' => '',
                'notes' => '',
                'date_received' => '2026-11-04',
            ],
            'scion' => [
                'supplier_id' => '',
                'quantity' => 40,
                'origin' => 'Scion Origin',
                'scion_variety_id' => $variety->scion_variety_id,
                'unit_price' => 0,
                'notes' => '',
                'date_received' => '2026-11-04',
            ],
        ]);

        $this->assertArrayNotHasKey('seedling', $submission->payload);
        $this->assertSame(40, (int) $submission->payload['scion']['quantity']);
        $this->assertNull($submission->payload['scion']['supplier_id']);
        $this->assertSame(0, StockIn::count());
    }

    public function test_an_invalid_populated_entry_accepts_nothing(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();

        $this->actingAs($staff)
            ->post(route('operational.submissions.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'date_received' => '2026-11-04',
                ],
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 0,
                    'origin' => 'Davao',
                    'scion_variety_id' => '',
                    'unit_price' => -1,
                    'date_received' => '2026-11-04',
                ],
            ])
            ->assertSessionHasErrors([
                'scion.quantity',
                'scion.scion_variety_id',
                'scion.unit_price',
            ], null, 'submission_stockin');

        $this->assertSame(0, OperationalSubmission::count());
        $this->assertSame(0, StockIn::count());
    }

    public function test_a_scion_entry_does_not_require_a_supplier(): void
    {
        $staff = $this->staff();
        $variety = $this->variety();

        $submission = $this->capture($staff, [
            'scion' => [
                'quantity' => 40,
                'origin' => 'Davao',
                'scion_variety_id' => $variety->scion_variety_id,
                'unit_price' => 12.5,
                'date_received' => '2026-11-04',
            ],
        ]);

        $this->assertNull($submission->payload['scion']['supplier_id']);
        $this->assertSame(40, (int) $submission->payload['scion']['quantity']);
    }

    public function test_only_a_submit_holder_can_open_the_stock_in_submission_form(): void
    {
        $this->actingAs($this->manager())
            ->get(route('operational.submissions.stock-in.create'))
            ->assertForbidden();

        $this->actingAs($this->staff())
            ->get(route('operational.submissions.stock-in.create'))
            ->assertOk()
            ->assertSee('Stock-in details')
            ->assertSee('Seedlings entry')
            ->assertSee('Scion entry');
    }

    public function test_approval_creates_one_official_row_per_populated_tab_attributed_to_the_submitter(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $variety = $this->variety();
        $submission = $this->capture($staff, $this->bothEntries($supplier, $variety));

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('success');

        $this->assertSame(2, StockIn::count());

        $seedling = StockIn::query()->whereNull('scion_variety_id')->firstOrFail();
        $scion = StockIn::query()->whereNotNull('scion_variety_id')->firstOrFail();

        $this->assertSame($staff->id, (int) $seedling->user_id);
        $this->assertSame(500, (int) $seedling->quantity);
        $this->assertSame($supplier->supplier_id, (int) $seedling->supplier_id);
        $this->assertNull($seedling->unit_price);

        $this->assertSame($staff->id, (int) $scion->user_id);
        $this->assertSame(300, (int) $scion->quantity);
        $this->assertSame($variety->scion_variety_id, (int) $scion->scion_variety_id);
        $this->assertSame('12.50', $scion->unit_price);

        $submission->refresh();
        $this->assertTrue($submission->isApproved());
        $this->assertSame($manager->id, (int) $submission->decided_by_id);
        $this->assertFalse($submission->decision_automatic);

        $this->assertSame(2, OperationalSubmissionRecord::query()
            ->where('submission_id', $submission->submission_id)
            ->where('record_type', OperationalSubmissionRecord::RECORD_TYPE_STOCK_IN)
            ->count());

        $this->assertSame(2, ActivityLog::query()
            ->where('activity_type', ActivityLog::TYPE_STOCK_IN_RECORDED)
            ->where('user_id', $staff->id)
            ->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $manager->id,
            'activity_type' => ActivityLog::TYPE_OPERATIONAL_SUBMISSION_APPROVED,
        ]);

        $this->assertSame(500, AnalyticsMetrics::stockLedgerGrid()['summary']['seedlings']);
        $this->assertSame(300, $variety->fresh()->receivedScionQuantity());
        $this->assertSame(0, Batch::count());
        $this->assertSame(0, Inventory::count());
    }

    public function test_pending_and_rejected_stock_in_submissions_never_reach_stock_ins_or_analytics(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $submission = $this->capture($staff, $this->bothEntries($this->supplier(), $this->variety()));

        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, AnalyticsMetrics::stockLedgerGrid()['summary']['seedlings']);

        $this->actingAs($manager)
            ->post(route('operational.submissions.reject', $submission), ['reason' => 'Wrong quantities.'])
            ->assertSessionHas('success');

        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, AnalyticsMetrics::stockLedgerGrid()['summary']['seedlings']);
        $this->assertSame(0, OperationalSubmissionRecord::count());
    }

    public function test_approval_is_applied_exactly_once(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $submission = $this->capture($staff, $this->bothEntries($this->supplier(), $this->variety()));

        $this->actingAs($manager)->post(route('operational.submissions.approve', $submission));

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('error');

        $this->assertSame(2, StockIn::count());
        $this->assertSame(2, OperationalSubmissionRecord::count());
    }

    public function test_d6_rejects_when_the_supplier_is_archived_after_capture(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $submission = $this->capture($staff, $this->bothEntries($supplier, $this->variety()));

        $supplier->update(['archived_at' => now()]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertRedirect(route('operational.submissions.show', $submission))
            ->assertSessionHas('error');

        $submission->refresh();

        $this->assertTrue($submission->isRejected());
        $this->assertTrue($submission->decision_automatic);
        $this->assertStringContainsString('Automatic rejection', (string) $submission->decision_reason);
        $this->assertStringContainsString('supplier is no longer active', (string) $submission->decision_reason);
        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, OperationalSubmissionRecord::count());
    }

    public function test_d6_rejects_when_the_scion_variety_is_archived_after_capture(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $variety = $this->variety();
        $submission = $this->capture($staff, $this->bothEntries($this->supplier(), $variety));

        $variety->update(['archived_at' => now()]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertSessionHas('error');

        $submission->refresh();

        $this->assertTrue($submission->isRejected());
        $this->assertTrue($submission->decision_automatic);
        $this->assertStringContainsString('scion variety is no longer active', (string) $submission->decision_reason);
        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, OperationalSubmissionRecord::count());
    }

    public function test_d6_keeps_both_entries_out_when_one_entry_fails_revalidation(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $variety = $this->variety();
        $submission = $this->capture($staff, $this->bothEntries($supplier, $variety));

        $variety->update(['archived_at' => now()]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertSessionHas('error');

        $this->assertSame(0, StockIn::query()->whereNull('scion_variety_id')->count());
        $this->assertSame(0, StockIn::count());
        $this->assertTrue($submission->fresh()->isRejected());
    }

    public function test_approval_remains_available_when_the_module_flag_is_turned_off_after_capture(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $submission = $this->capture($staff, $this->bothEntries($this->supplier(), $this->variety()));

        config(['cacaotrack.operational_submission.modules.stock_in' => false]);

        $this->actingAs($manager)
            ->post(route('operational.submissions.approve', $submission))
            ->assertSessionHas('success');

        $this->assertTrue($submission->fresh()->isApproved());
        $this->assertSame(2, StockIn::count());
    }

    public function test_the_queue_pending_count_and_review_include_stock_in_while_the_module_is_off(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $variety = $this->variety();

        OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_IN, $this->bothEntries($supplier, $variety))
            ->create(['submitted_by_id' => $staff->id]);

        config(['cacaotrack.operational_submission.modules.stock_in' => false]);

        $this->actingAs($manager)
            ->get(route('operational.submissions.queue'))
            ->assertOk()
            ->assertSee('Stock In')
            ->assertSee('Seedlings 500 + Scion 300');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pending submissions:')
            ->assertSee('Submission Queue');

        $submission = OperationalSubmission::firstOrFail();

        $this->actingAs($manager)
            ->get(route('operational.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Seedlings entry')
            ->assertSee('Scion entry')
            ->assertSee($supplier->supplier_name)
            ->assertSee($variety->scion_variety_name)
            ->assertSee('Approve Submission');
    }
}
