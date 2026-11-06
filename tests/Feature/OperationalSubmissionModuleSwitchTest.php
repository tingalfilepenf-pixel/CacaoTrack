<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SubmissionWorkflowSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSubmissionModuleSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return $this->userWithPermissions([
            'manage_stock',
            'record_operations',
            'submit_operations',
            'complete_checklist',
        ]);
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
    private function payload(Supplier $supplier): array
    {
        return [
            'seedling' => [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 25,
                'origin' => 'Davao',
                'date_received' => '2026-11-04',
            ],
        ];
    }

    public function test_the_stock_in_module_is_off_by_default(): void
    {
        $this->assertFalse(config('cacaotrack.operational_submission.enabled'));
        $this->assertFalse(config('cacaotrack.operational_submission.modules.stock_in'));
        $this->assertFalse(SubmissionWorkflowSwitch::stockInEnabled());
    }

    public function test_the_master_switch_always_wins_over_the_stock_in_module(): void
    {
        config([
            'cacaotrack.operational_submission.enabled' => false,
            'cacaotrack.operational_submission.modules.stock_in' => true,
        ]);

        $this->assertFalse(SubmissionWorkflowSwitch::stockInEnabled());
    }

    public function test_direct_stock_in_still_works_while_the_module_is_off(): void
    {
        config(['cacaotrack.operational_submission.enabled' => true]);

        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('inventory.stock-in.store'), $this->payload($this->supplier()))
            ->assertRedirect(route('inventory.stock-in'));

        $this->assertSame(1, StockIn::count());
    }

    public function test_direct_stock_in_is_disabled_while_the_module_is_on(): void
    {
        config([
            'cacaotrack.operational_submission.enabled' => true,
            'cacaotrack.operational_submission.modules.stock_in' => true,
        ]);

        $this->actingAs($this->staff())
            ->post(route('inventory.stock-in.store'), $this->payload($this->supplier()))
            ->assertNotFound();

        $this->assertSame(0, StockIn::count());
    }

    public function test_direct_stock_in_stays_available_when_master_is_off_even_with_the_module_on(): void
    {
        config([
            'cacaotrack.operational_submission.enabled' => false,
            'cacaotrack.operational_submission.modules.stock_in' => true,
        ]);

        $this->actingAs($this->staff())
            ->post(route('inventory.stock-in.store'), $this->payload($this->supplier()))
            ->assertRedirect(route('inventory.stock-in'));

        $this->assertSame(1, StockIn::count());
    }

    public function test_stock_in_history_stays_manage_stock_only_while_the_module_is_off(): void
    {
        $this->actingAs($this->staff())
            ->get(route('inventory.stock-in'))
            ->assertOk();

        $manager = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($manager)
            ->get(route('inventory.stock-in'))
            ->assertForbidden();
    }

    public function test_stock_in_history_denies_non_all_scope_users_while_the_module_is_on(): void
    {
        config([
            'cacaotrack.operational_submission.enabled' => true,
            'cacaotrack.operational_submission.modules.stock_in' => true,
        ]);

        $this->actingAs($this->staff())
            ->get(route('inventory.stock-in'))
            ->assertForbidden();

        $manager = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($manager)
            ->get(route('inventory.stock-in'))
            ->assertForbidden();
    }

    public function test_an_all_scope_user_with_manage_stock_can_still_read_stock_in_history(): void
    {
        config([
            'cacaotrack.operational_submission.enabled' => true,
            'cacaotrack.operational_submission.modules.stock_in' => true,
        ]);

        $allScope = $this->userWithPermissions(
            ['manage_stock', 'verify_operations'],
            Role::MANAGER,
        );

        $this->actingAs($allScope)
            ->get(route('inventory.stock-in'))
            ->assertOk();
    }
}
