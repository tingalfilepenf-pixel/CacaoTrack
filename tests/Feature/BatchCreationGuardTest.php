<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchCreationGuardTest extends TestCase
{
    use RefreshDatabase;

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

    private function batch(
        string $code,
        ?ScionVariety $variety = null,
        ?int $seedlingsUsed = null,
        ?int $scionUsed = null,
        ?Supplier $supplier = null,
        string $source = Batch::SOURCE_OWN,
    ): Batch {
        return Batch::create([
            'source' => $source,
            'supplier_id' => $supplier?->supplier_id,
            'scion_variety_id' => $variety?->scion_variety_id,
            'parent_batch_id' => null,
            'user_id' => $this->staff()->id,
            'batch_code' => $code,
            'total_grafted' => 100,
            'seedlings_used' => $seedlingsUsed,
            'scion_used' => $scionUsed,
            'regraft' => 0,
            'available_count' => 100,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function batchCreationPayload(ScionVariety $variety, array $overrides = []): array
    {
        return array_merge([
            'source' => Batch::SOURCE_OWN,
            'seedlings_used' => 20,
            'scion_variety_id' => $variety->scion_variety_id,
            'scion_used' => 10,
            'plants_grafted' => 10,
            'grafted_date' => now()->toDateString(),
            'seedlings_available' => 100,
            'scion_available' => 50,
        ], $overrides);
    }

    public function test_a_pending_batch_creation_submission_blocks_scion_variety_deletion(): void
    {
        $variety = $this->variety();
        $staff = $this->staff();

        $submission = OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_BATCH_CREATION, $this->batchCreationPayload($variety))
            ->create(['submitted_by_id' => $staff->id]);

        $this->assertContains('pending batch-creation submissions', $variety->deletionBlockers());

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('operational.scion-varieties.destroy', $variety))
            ->assertRedirect(route('operational.scion-varieties'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('scion_varieties', ['scion_variety_id' => $variety->scion_variety_id]);

        $submission->reject($this->manager()->id, 'Not needed.');

        $this->assertNotContains('pending batch-creation submissions', $variety->fresh()->deletionBlockers());
    }

    public function test_a_pending_batch_creation_supplier_can_still_be_archived(): void
    {
        config(['cacaotrack.operational_submission.enabled' => true]);
        $variety = $this->variety();
        $supplier = $this->supplier();

        OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_BATCH_CREATION, $this->batchCreationPayload($variety, [
                'source' => Batch::SOURCE_SUPPLIER,
                'supplier_id' => $supplier->supplier_id,
                'plants_delivered' => 10,
                'delivery_date' => now()->toDateString(),
            ]))
            ->create(['submitted_by_id' => $this->staff()->id]);

        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('inventory.suppliers.archive', $supplier))
            ->assertRedirect(route('inventory.suppliers'))
            ->assertSessionHas('success');

        $this->assertNotNull($supplier->fresh()->archived_at);
    }

    public function test_a_tracked_own_batch_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['record_operations']);
        $batch = $this->batch('B010', $this->variety(), seedlingsUsed: 80, scionUsed: 30);

        $this->assertContains('nursery stock used', $batch->deletionBlockers());
        $this->assertFalse($batch->canBeDeleted());

        $this->actingAs($user)
            ->delete(route('operational.batches.destroy', $batch))
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('batches', ['batch_id' => $batch->batch_id]);
    }

    public function test_a_tracked_own_batch_locks_its_code_variety_and_total_on_edit(): void
    {
        $user = $this->userWithPermissions(['record_operations']);
        $variety = $this->variety();
        $otherVariety = $this->variety('Toledo');
        $batch = $this->batch('B011', $variety, seedlingsUsed: 80, scionUsed: 30);

        $this->actingAs($user)
            ->put(route('operational.batches.update', $batch), [
                'batch_code' => 'HACKED',
                'scion_variety_id' => $otherVariety->scion_variety_id,
                'total_grafted' => 999,
                'grafted_date' => '2026-07-16',
                'status' => Batch::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('success');

        $batch->refresh();

        $this->assertSame('B011', $batch->batch_code);
        $this->assertSame($variety->scion_variety_id, (int) $batch->scion_variety_id);
        $this->assertSame(100, (int) $batch->total_grafted);
        $this->assertSame(100, (int) $batch->available_count);
        $this->assertSame('2026-07-16', $batch->grafted_date->format('Y-m-d'));
        $this->assertSame(Batch::STATUS_INACTIVE, $batch->status);
    }

    public function test_a_stock_in_correction_cannot_reduce_seedlings_below_own_batch_use(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $variety = $this->variety();
        $stockIn = StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => null,
            'user_id' => $admin->id,
            'quantity' => 100,
            'unit_price' => null,
            'origin' => 'Test Origin',
            'date_received' => '2026-11-04',
        ]);
        $this->batch('B012', $variety, seedlingsUsed: 80, scionUsed: 30);

        $this->actingAs($admin)
            ->post(route('corrections.stock-in', $stockIn), [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 70,
                'scion_variety_id' => null,
                'unit_price' => null,
                'origin' => 'Test Origin',
                'notes' => null,
                'date_received' => '2026-11-04',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'stockin_correction');

        $this->assertSame(
            'This correction would leave 70 seedlings received, below the 80 already used by nursery batches.',
            session('errors')->getBag('stockin_correction')->first('quantity'),
        );
        $this->assertSame(100, (int) $stockIn->fresh()->quantity);
    }

    public function test_a_scion_correction_cannot_reduce_below_own_batch_use(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = $this->supplier();
        $variety = $this->variety();
        $stockIn = StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $admin->id,
            'quantity' => 100,
            'unit_price' => 10.00,
            'origin' => 'Test Origin',
            'date_received' => '2026-11-04',
        ]);
        $this->batch('B013', $variety, scionUsed: 80);

        $this->assertSame(100, $variety->fresh()->receivedScionQuantity());
        $this->assertSame(80, $variety->fresh()->usedScionQuantity());

        $this->actingAs($admin)
            ->post(route('corrections.stock-in', $stockIn), [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 70,
                'scion_variety_id' => $variety->scion_variety_id,
                'unit_price' => 10.00,
                'origin' => 'Test Origin',
                'notes' => null,
                'date_received' => '2026-11-04',
            ])
            ->assertSessionHasErrors(['quantity'], null, 'stockin_correction');

        $this->assertSame(
            'This correction would leave Batangas with 70 received scions, below the 80 already consumed.',
            session('errors')->getBag('stockin_correction')->first('quantity'),
        );
        $this->assertSame(100, (int) $stockIn->fresh()->quantity);
        $this->assertSame(80, $variety->fresh()->usedScionQuantity());
    }

    public function test_a_user_with_a_batch_creation_submission_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = $this->staff();
        $variety = $this->variety();
        $this->batch('B014', $variety, seedlingsUsed: 20, scionUsed: 10);

        OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_BATCH_CREATION, $this->batchCreationPayload($variety))
            ->create(['submitted_by_id' => $staff->id]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $staff))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    public function test_the_source_badge_is_only_rendered_while_the_batch_module_is_on(): void
    {
        $user = $this->userWithPermissions(['record_operations']);
        $variety = $this->variety();
        $this->batch('B015', $variety, seedlingsUsed: 20, scionUsed: 10);

        config(['cacaotrack.operational_submission.enabled' => true, 'cacaotrack.operational_submission.modules.batch_creation' => false]);

        $this->actingAs($user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertDontSee('Own nursery');

        config(['cacaotrack.operational_submission.modules.batch_creation' => true]);

        $this->actingAs($user)
            ->get(route('operational.batches'))
            ->assertOk()
            ->assertSee('Own nursery');
    }
}
