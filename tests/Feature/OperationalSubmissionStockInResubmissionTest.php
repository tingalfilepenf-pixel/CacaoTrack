<?php

namespace Tests\Feature;

use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionStockInResubmissionTest extends TestCase
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

    private function supplier(): Supplier
    {
        return Supplier::create([
            'supplier_name' => 'Supplier A',
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    private function variety(): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => 'Criollo']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function entries(Supplier $supplier, ScionVariety $variety): array
    {
        return [
            'seedling' => [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 500,
                'origin' => 'Seedling Origin',
                'date_received' => '2026-11-04',
            ],
            'scion' => [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 300,
                'origin' => 'Scion Origin',
                'scion_variety_id' => $variety->scion_variety_id,
                'unit_price' => 12.5,
                'date_received' => '2026-11-04',
            ],
        ];
    }

    private function rejectedSubmission(User $staff, Supplier $supplier, ScionVariety $variety): OperationalSubmission
    {
        $this->actingAs($staff)
            ->post(route('operational.submissions.stock-in.store'), $this->entries($supplier, $variety))
            ->assertSessionHas('success');

        $submission = OperationalSubmission::query()->latest('submission_id')->firstOrFail();

        $submission->reject($this->manager()->id, 'First rejection.');

        return $submission->refresh();
    }

    public function test_only_the_original_submitter_can_open_a_stock_in_resubmission_form(): void
    {
        $staff = $this->staff();
        $other = $this->staff();
        $supplier = $this->supplier();
        $variety = $this->variety();
        $submission = $this->rejectedSubmission($staff, $supplier, $variety);

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $submission))
            ->assertOk()
            ->assertSee('Resubmit Stock In')
            ->assertSee('Seedlings entry')
            ->assertSee('Scion entry')
            ->assertSee($supplier->supplier_name)
            ->assertSee($variety->scion_variety_name);

        $this->actingAs($other)
            ->get(route('operational.submissions.resubmit.create', $submission))
            ->assertNotFound();
    }

    public function test_a_stock_in_resubmission_creates_a_new_linked_pending_row_and_leaves_the_original_unchanged(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();
        $variety = $this->variety();
        $original = $this->rejectedSubmission($staff, $supplier, $variety);

        $this->actingAs($staff)
            ->post(route('operational.submissions.resubmit.stock-in', $original), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 450,
                    'origin' => 'Corrected Origin',
                    'date_received' => '2026-11-04',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $new = OperationalSubmission::query()
            ->where('resubmission_of_id', $original->submission_id)
            ->firstOrFail();

        $this->assertSame(OperationalSubmission::TYPE_STOCK_IN, $new->submission_type);
        $this->assertTrue($new->isPending());
        $this->assertSame($staff->id, (int) $new->submitted_by_id);
        $this->assertSame(450, (int) $new->payload['seedling']['quantity']);
        $this->assertArrayNotHasKey('scion', $new->payload);
        $this->assertSame(0, StockIn::count());

        $original->refresh();
        $this->assertTrue($original->isRejected());
        $this->assertSame('First rejection.', $original->decision_reason);
        $this->assertSame(500, (int) $original->payload['seedling']['quantity']);
    }

    public function test_pending_or_approved_stock_in_submissions_cannot_be_resubmitted(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $supplier = $this->supplier();
        $variety = $this->variety();

        $this->actingAs($staff)
            ->post(route('operational.submissions.stock-in.store'), $this->entries($supplier, $variety));

        $pending = OperationalSubmission::query()->latest('submission_id')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $pending))
            ->assertNotFound();

        $approved = OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_IN, $this->entries($supplier, $variety))
            ->create(['submitted_by_id' => $staff->id]);
        $approved->approve($manager->id);

        $this->actingAs($staff)
            ->get(route('operational.submissions.resubmit.create', $approved))
            ->assertNotFound();
    }

    public function test_stock_in_resubmission_is_disabled_when_the_module_flag_is_off(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();
        $variety = $this->variety();
        $original = $this->rejectedSubmission($staff, $supplier, $variety);

        config(['cacaotrack.operational_submission.modules.stock_in' => false]);

        $this->actingAs($staff)
            ->get(route('operational.submissions.show', $original))
            ->assertOk()
            ->assertDontSee('Resubmit this request');

        $this->actingAs($staff)
            ->post(route('operational.submissions.resubmit.stock-in', $original), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 450,
                    'origin' => 'Corrected Origin',
                    'date_received' => '2026-11-04',
                ],
            ])
            ->assertNotFound();
    }
}
