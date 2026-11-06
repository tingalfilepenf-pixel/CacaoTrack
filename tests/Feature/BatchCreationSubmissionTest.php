<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use App\Support\StockBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchCreationSubmissionTest extends TestCase
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

    public function test_the_batch_module_is_off_by_default_and_its_routes_are_404(): void
    {
        config([
            'cacaotrack.operational_submission.enabled' => false,
            'cacaotrack.operational_submission.modules.batch_creation' => false,
        ]);

        $this->actingAs($this->staff())
            ->get(route('operational.submissions.batch-creation.create'))
            ->assertNotFound();
    }

    public function test_the_master_switch_wins_over_the_batch_module(): void
    {
        config([
            'cacaotrack.operational_submission.enabled' => false,
            'cacaotrack.operational_submission.modules.batch_creation' => true,
        ]);

        $this->actingAs($this->staff())
            ->get(route('operational.submissions.batch-creation.create'))
            ->assertNotFound();
    }

    public function test_the_form_shows_both_sources_without_costing_or_receipt_fields(): void
    {
        $this->actingAs($this->staff())
            ->get(route('operational.submissions.batch-creation.create'))
            ->assertOk()
            ->assertSee('Own nursery')
            ->assertSee('Supplier delivery')
            ->assertSee('name="seedlings_used"', false)
            ->assertSee('name="scion_used"', false)
            ->assertSee('name="plants_grafted"', false)
            ->assertSee('name="supplier_id"', false)
            ->assertSee('name="delivery_date"', false)
            ->assertSee('name="plants_delivered"', false)
            ->assertDontSee('Delivery Receipt')
            ->assertDontSee('Unit Price');
    }

    public function test_the_direct_batch_write_is_disabled_while_the_module_is_on(): void
    {
        $this->actingAs($this->staff())
            ->post(route('operational.batches.manage.store'), [
                'total_grafted' => 10,
                'status' => Batch::STATUS_ACTIVE,
            ])
            ->assertNotFound();

        $this->assertSame(0, Batch::count());
    }

    public function test_the_direct_batch_write_still_works_when_only_the_master_is_on(): void
    {
        config(['cacaotrack.operational_submission.modules.batch_creation' => false]);

        $this->actingAs($this->staff())
            ->post(route('operational.batches.manage.store'), [
                'total_grafted' => 10,
                'status' => Batch::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('operational.batches'));

        $this->assertSame(1, Batch::count());
    }

    public function test_an_own_nursery_submission_is_frozen_without_writing_a_batch_or_deducting_stock(): void
    {
        $staff = $this->staff();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(50);
        $this->stockIn(40, $variety);

        $beforeBatches = Batch::count();
        $beforeStockIns = StockIn::count();

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), $this->ownPayload($variety))
            ->assertRedirect(route('operational.submissions.index'))
            ->assertSessionHas('success');

        $submission = OperationalSubmission::firstOrFail();

        $this->assertSame(OperationalSubmission::TYPE_BATCH_CREATION, $submission->submission_type);
        $this->assertSame(OperationalSubmission::STATUS_PENDING, $submission->status);
        $this->assertNull($submission->subject_batch_id);
        $this->assertSame(Batch::SOURCE_OWN, $submission->payload['source']);
        $this->assertSame(80, $submission->payload['seedlings_used']);
        $this->assertSame(30, $submission->payload['scion_used']);
        $this->assertSame(25, $submission->payload['plants_grafted']);
        $this->assertSame($variety->scion_variety_id, $submission->payload['scion_variety_id']);
        $this->assertSame(now()->toDateString(), $submission->payload['grafted_date']);
        $this->assertSame(150, $submission->payload['seedlings_available']);
        $this->assertSame(40, $submission->payload['scion_available']);
        $this->assertSame($staff->id, (int) $submission->submitted_by_id);

        $this->assertSame($beforeBatches, Batch::count());
        $this->assertSame($beforeStockIns, StockIn::count());
        $this->assertSame(150, StockBalances::seedlingsAvailable());
        $this->assertSame(40, $variety->fresh()->availableScionQuantity());
    }

    public function test_a_supplier_delivery_submission_is_frozen_without_deducting_stock(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();
        $variety = $this->variety('No Stock In Variety');

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), $this->supplierPayload($supplier, $variety))
            ->assertRedirect(route('operational.submissions.index'))
            ->assertSessionHas('success');

        $submission = OperationalSubmission::firstOrFail();

        $this->assertSame(Batch::SOURCE_SUPPLIER, $submission->payload['source']);
        $this->assertSame($supplier->supplier_id, $submission->payload['supplier_id']);
        $this->assertSame('2026-11-05', $submission->payload['delivery_date']);
        $this->assertSame('2026-11-05', $submission->payload['grafted_date']);
        $this->assertSame($variety->scion_variety_id, $submission->payload['scion_variety_id']);
        $this->assertSame(120, $submission->payload['plants_delivered']);
        $this->assertArrayNotHasKey('seedlings_used', $submission->payload);
        $this->assertArrayNotHasKey('scion_used', $submission->payload);

        $this->assertSame(0, Batch::count());
        $this->assertSame(0, StockBalances::seedlingsUsed());
        $this->assertSame(0, $variety->fresh()->usedScionQuantity());
    }

    public function test_own_nursery_limits_are_blocked_at_submission_with_clear_messages(): void
    {
        $staff = $this->staff();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), $this->ownPayload($variety, [
                'seedlings_used' => 101,
                'plants_grafted' => 25,
            ]))
            ->assertSessionHasErrors(['seedlings_used'], null, 'submission_batch');

        $this->assertSame(
            'Only 100 seedlings are available. Enter 100 or fewer.',
            session('errors')->getBag('submission_batch')->first('seedlings_used'),
        );

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), $this->ownPayload($variety, [
                'scion_used' => 41,
                'plants_grafted' => 25,
            ]))
            ->assertSessionHasErrors(['scion_used'], null, 'submission_batch');

        $this->assertSame(
            'Only 40 Batangas scion is available. Enter 40 or fewer.',
            session('errors')->getBag('submission_batch')->first('scion_used'),
        );

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), $this->ownPayload($variety, [
                'seedlings_used' => 20,
                'scion_used' => 10,
                'plants_grafted' => 21,
            ]))
            ->assertSessionHasErrors(['plants_grafted'], null, 'submission_batch');

        $this->assertSame(
            "Plants grafted can't be more than the seedlings or scion used.",
            session('errors')->getBag('submission_batch')->first('plants_grafted'),
        );

        $this->assertSame(0, OperationalSubmission::count());
    }

    public function test_source_specific_required_messages_are_used(): void
    {
        $staff = $this->staff();
        $variety = $this->variety();

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), [
                'source' => Batch::SOURCE_OWN,
            ])
            ->assertSessionHasErrors([
                'seedlings_used',
                'scion_variety_id',
                'scion_used',
                'plants_grafted',
            ], null, 'submission_batch');

        $ownBag = session('errors')->getBag('submission_batch');
        $this->assertSame('Enter how many seedlings were used.', $ownBag->first('seedlings_used'));
        $this->assertSame('Choose a scion variety.', $ownBag->first('scion_variety_id'));
        $this->assertSame('Enter how many scion were used.', $ownBag->first('scion_used'));
        $this->assertSame('Enter how many plants were grafted.', $ownBag->first('plants_grafted'));

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), [
                'source' => Batch::SOURCE_SUPPLIER,
            ])
            ->assertSessionHasErrors([
                'supplier_id',
                'delivery_date',
                'scion_variety_id',
                'plants_delivered',
            ], null, 'submission_batch');

        $supplierBag = session('errors')->getBag('submission_batch');
        $this->assertSame('Choose a supplier.', $supplierBag->first('supplier_id'));
        $this->assertSame('Enter the delivery date.', $supplierBag->first('delivery_date'));
        $this->assertSame('Choose a scion variety.', $supplierBag->first('scion_variety_id'));
        $this->assertSame('Enter how many plants were delivered.', $supplierBag->first('plants_delivered'));

        $this->assertSame(0, OperationalSubmission::count());
    }

    public function test_a_supplier_delivery_date_cannot_be_in_the_future(): void
    {
        $this->actingAs($this->staff())
            ->post(route('operational.submissions.batch-creation.store'), $this->supplierPayload(
                $this->supplier(),
                $this->variety(),
                ['delivery_date' => now()->addDay()->toDateString()],
            ))
            ->assertSessionHasErrors(['delivery_date'], null, 'submission_batch');

        $this->assertSame(
            'The delivery date cannot be in the future.',
            session('errors')->getBag('submission_batch')->first('delivery_date'),
        );
    }

    public function test_an_archived_supplier_or_scion_variety_is_rejected_at_submission(): void
    {
        $supplier = $this->supplier();
        $supplier->update(['archived_at' => now()]);
        $variety = $this->variety();
        $variety->update(['archived_at' => now()]);

        $this->actingAs($this->staff())
            ->post(route('operational.submissions.batch-creation.store'), $this->supplierPayload($supplier, $variety))
            ->assertSessionHasErrors(['supplier_id', 'scion_variety_id'], null, 'submission_batch');

        $this->assertSame(
            'Choose a supplier.',
            session('errors')->getBag('submission_batch')->first('supplier_id'),
        );
        $this->assertSame(
            'Choose a scion variety.',
            session('errors')->getBag('submission_batch')->first('scion_variety_id'),
        );
    }

    public function test_index_queue_and_show_render_the_source_specific_details(): void
    {
        $staff = $this->staff();
        $manager = User::factory()->role(Role::MANAGER)->create();
        $variety = $this->variety();
        $this->stockIn(100);
        $this->stockIn(40, $variety);

        $this->actingAs($staff)
            ->post(route('operational.submissions.batch-creation.store'), $this->ownPayload($variety));

        $this->actingAs($staff)
            ->get(route('operational.submissions.index'))
            ->assertOk()
            ->assertSee('Batch Creation')
            ->assertSee('Own nursery batch')
            ->assertSee('Assigned at approval');

        $submission = OperationalSubmission::firstOrFail();

        $this->actingAs($staff)
            ->get(route('operational.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Own nursery batch')
            ->assertSee('Seedlings Used')
            ->assertSee('Scion Used')
            ->assertSee('Plants Grafted')
            ->assertDontSee('Approve Submission');

        $this->actingAs($manager)
            ->get(route('operational.submissions.queue'))
            ->assertOk()
            ->assertSee('Batch Creation')
            ->assertSee('Own nursery batch')
            ->assertSee('Plants grafted: 25');
    }
}
