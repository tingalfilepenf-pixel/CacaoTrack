<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create([
            'role' => Role::STAFF,
            'permissions' => ['submit_operations', 'record_operations', 'manage_stock', 'complete_checklist'],
        ]);
    }

    private function batch(User $owner, string $code = 'B001'): Batch
    {
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
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_a_pending_mortality_submission_blocks_batch_deletion(): void
    {
        $staff = $this->staff();
        $batch = $this->batch($staff);

        OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $staff->id,
        ]);

        $this->assertContains('operational submissions', $batch->deletionBlockers());

        $this->actingAs($staff)
            ->delete(route('operational.batches.destroy', $batch))
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('batches', ['batch_id' => $batch->batch_id]);
    }

    public function test_a_pending_stock_out_submission_blocks_every_batch_in_its_payload_details(): void
    {
        $staff = $this->staff();
        $first = $this->batch($staff, 'B001');
        $second = $this->batch($staff, 'B002');
        $client = Client::create([
            'client_name' => 'Client A',
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);

        OperationalSubmission::factory()
            ->ofType(OperationalSubmission::TYPE_STOCK_OUT, [
                'client_id' => $client->client_id,
                'release_date' => '2026-11-04',
                'details' => [
                    ['batch_id' => $first->batch_id, 'quantity' => 5, 'unit' => 'pots', 'unit_price' => null],
                    ['batch_id' => $second->batch_id, 'quantity' => 5, 'unit' => 'pots', 'unit_price' => null],
                ],
            ])
            ->create(['submitted_by_id' => $staff->id]);

        $this->assertContains('pending stock-out submissions', $first->deletionBlockers());
        $this->assertContains('pending stock-out submissions', $second->deletionBlockers());

        $this->actingAs($staff)
            ->delete(route('operational.batches.destroy', $first))
            ->assertSessionHas('error');

        $this->actingAs($staff)
            ->delete(route('operational.batches.destroy', $second))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('batches', ['batch_id' => $first->batch_id]);
        $this->assertDatabaseHas('batches', ['batch_id' => $second->batch_id]);
    }

    public function test_a_submission_submitter_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = $this->staff();
        $batch = $this->batch($admin);

        OperationalSubmission::factory()->forBatch($batch)->create([
            'submitted_by_id' => $staff->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $staff))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }
}
