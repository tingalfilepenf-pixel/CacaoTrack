<?php

namespace Tests\Feature;

use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionStockInDeletionGuardTest extends TestCase
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function entries(Supplier $supplier, ScionVariety $variety): array
    {
        return [
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

    public function test_a_pending_stock_in_submission_blocks_scion_variety_deletion(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_IN, $this->entries($supplier, $variety))
            ->create(['submitted_by_id' => $staff->id]);

        $this->assertContains('pending stock-in submissions', $variety->deletionBlockers());

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('operational.scion-varieties.destroy', $variety))
            ->assertRedirect(route('operational.scion-varieties'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('scion_varieties', [
            'scion_variety_id' => $variety->scion_variety_id,
        ]);
    }

    public function test_a_rejected_stock_in_submission_does_not_block_scion_variety_deletion(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        $submission = OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_IN, $this->entries($supplier, $variety))
            ->create(['submitted_by_id' => $staff->id]);
        $submission->reject($this->manager()->id, 'Not needed.');

        $this->assertNotContains('pending stock-in submissions', $variety->deletionBlockers());

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('operational.scion-varieties.destroy', $variety))
            ->assertRedirect(route('operational.scion-varieties'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('scion_varieties', [
            'scion_variety_id' => $variety->scion_variety_id,
        ]);
    }

    public function test_a_supplier_with_a_pending_stock_in_submission_can_still_be_archived(): void
    {
        $staff = $this->staff();
        $supplier = $this->supplier();
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);

        OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_IN, $this->entries($supplier, $variety))
            ->create(['submitted_by_id' => $staff->id]);

        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('inventory.suppliers.archive', $supplier))
            ->assertRedirect(route('inventory.suppliers'))
            ->assertSessionHas('success');

        $this->assertNotNull($supplier->fresh()->archived_at);
    }
}
