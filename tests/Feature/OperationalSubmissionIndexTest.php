<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\OperationalSubmission;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionIndexTest extends TestCase
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
        ]);
    }

    private function manager(): User
    {
        return User::factory()->role(Role::MANAGER)->create();
    }

    private function batch(User $owner, string $code): Batch
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
            'archived_at' => null,
        ]);
    }

    private function client(string $name): Client
    {
        return Client::create([
            'client_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    private function supplier(string $name): Supplier
    {
        return Supplier::create([
            'supplier_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mortality(User $submitter, Batch $batch, array $payload = []): OperationalSubmission
    {
        return OperationalSubmission::create([
            'submission_type' => OperationalSubmission::TYPE_MORTALITY,
            'status' => OperationalSubmission::STATUS_PENDING,
            'payload' => $payload === [] ? [
                'batch_id' => $batch->batch_id,
                'quantity' => 5,
                'cause' => 'Disease',
                'cause_other' => null,
                'recorded_at' => '2026-11-04',
            ] : $payload,
            'submitted_by_id' => $submitter->id,
            'submitted_at' => now(),
            'subject_batch_id' => $batch->batch_id,
        ]);
    }

    private function stockOut(User $submitter, Client $client, Batch $batch): OperationalSubmission
    {
        return OperationalSubmission::create([
            'submission_type' => OperationalSubmission::TYPE_STOCK_OUT,
            'status' => OperationalSubmission::STATUS_PENDING,
            'payload' => [
                'client_id' => $client->client_id,
                'release_date' => '2026-11-04',
                'details' => [
                    ['batch_id' => $batch->batch_id, 'quantity' => 10, 'unit' => 'pots', 'unit_price' => null],
                ],
            ],
            'submitted_by_id' => $submitter->id,
            'submitted_at' => now(),
            'subject_batch_id' => null,
        ]);
    }

    private function stockIn(User $submitter, Supplier $supplier): OperationalSubmission
    {
        return OperationalSubmission::create([
            'submission_type' => OperationalSubmission::TYPE_STOCK_IN,
            'status' => OperationalSubmission::STATUS_PENDING,
            'payload' => [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 500,
                    'origin' => 'Davao',
                    'notes' => null,
                    'date_received' => '2026-11-04',
                ],
            ],
            'submitted_by_id' => $submitter->id,
            'submitted_at' => now(),
            'subject_batch_id' => null,
        ]);
    }

    public function test_search_matches_a_batch_code(): void
    {
        $staff = $this->staff();
        $this->mortality($staff, $this->batch($staff, 'B001'));
        $this->mortality($staff, $this->batch($staff, 'B002'));

        $this->actingAs($staff)
            ->get(route('operational.submissions.index', ['q' => 'B002']))
            ->assertOk()
            ->assertSee('B002')
            ->assertDontSee('B001');
    }

    public function test_type_filter_limits_the_visible_submissions(): void
    {
        $staff = $this->staff();
        $this->mortality($staff, $this->batch($staff, 'B001'));
        $this->stockOut($staff, $this->client('Client Alpha'), $this->batch($staff, 'B002'));

        $this->actingAs($staff)
            ->get(route('operational.submissions.index', ['type' => 'stock_out']))
            ->assertOk()
            ->assertSee('Stock Out for Client Alpha')
            ->assertSee('B002')
            ->assertDontSee('Mortality Report')
            ->assertDontSee('B001');
    }

    public function test_status_filter_limits_the_visible_submissions(): void
    {
        $staff = $this->staff();
        $approved = $this->mortality($staff, $this->batch($staff, 'B001'));
        $this->mortality($staff, $this->batch($staff, 'B002'));

        $approved->approve($this->manager()->id);

        $this->actingAs($staff)
            ->get(route('operational.submissions.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('B001')
            ->assertSee('Approved')
            ->assertDontSee('B002');
    }

    public function test_search_matches_a_batch_code_inside_a_stock_out_payload(): void
    {
        $staff = $this->staff();
        $this->stockOut($staff, $this->client('Client Alpha'), $this->batch($staff, 'B001'));
        $this->stockOut($staff, $this->client('Client Beta'), $this->batch($staff, 'B002'));

        $this->actingAs($staff)
            ->get(route('operational.submissions.index', ['q' => 'B002']))
            ->assertOk()
            ->assertSee('Stock Out for Client Beta')
            ->assertSee('B002')
            ->assertDontSee('Stock Out for Client Alpha');
    }

    public function test_search_matches_a_stock_out_client_name(): void
    {
        $staff = $this->staff();
        $this->stockOut($staff, $this->client('Client Alpha'), $this->batch($staff, 'B001'));
        $this->stockOut($staff, $this->client('Client Beta'), $this->batch($staff, 'B002'));

        $this->actingAs($staff)
            ->get(route('operational.submissions.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Stock Out for Client Alpha')
            ->assertDontSee('Stock Out for Client Beta');
    }

    public function test_search_matches_a_stock_in_supplier_name(): void
    {
        $staff = $this->staff();
        $this->stockIn($staff, $this->supplier('Supplier Alpha'));
        $this->stockIn($staff, $this->supplier('Supplier Beta'));

        $this->actingAs($staff)
            ->get(route('operational.submissions.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Stock In from Supplier Alpha')
            ->assertDontSee('Stock In from Supplier Beta');
    }
}
