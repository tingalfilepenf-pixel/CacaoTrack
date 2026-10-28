<?php

namespace Tests\Feature;

use App\Models\ActivityTask;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\TaskComplete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role defaults, server-side route authorization and the user-facing surfaces
 * that must follow them (FR-02, PRD §13).
 */
class RolePermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, string $role = 'Custom Role'): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    private function createBatch(User $user, string $code = 'B001'): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo '.$code]);

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => 100,
            'regraft' => 0,
            'available_count' => 100,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function createTask(string $name = 'Inspect seedlings'): ActivityTask
    {
        return ActivityTask::create(['task_name' => $name, 'priority' => 'medium']);
    }

    public function test_permission_catalog_has_labels_and_descriptions_for_every_key(): void
    {
        $this->assertArrayHasKey('manage_checklist', Role::PERMISSIONS);
        $this->assertArrayHasKey('complete_checklist', Role::PERMISSIONS);
        $this->assertSame(
            'Manage and verify Daily Checklist tasks',
            Role::PERMISSIONS['manage_checklist']
        );
        $this->assertSame(
            'Mark assigned Daily Checklist tasks as completed',
            Role::PERMISSIONS['complete_checklist']
        );

        foreach (array_keys(Role::PERMISSIONS) as $key) {
            $this->assertArrayHasKey($key, Role::PERMISSION_DESCRIPTIONS);
            $this->assertNotSame('', Role::PERMISSION_DESCRIPTIONS[$key]);
        }
    }

    public function test_built_in_role_defaults_match_the_confirmed_workflow(): void
    {
        $this->assertEqualsCanonicalizing(
            ['record_operations', 'manage_stock', 'complete_checklist'],
            Role::defaultPermissionsFor(Role::STAFF)
        );
        $this->assertEqualsCanonicalizing(
            ['manage_inventory', 'manage_checklist', 'view_analytics', 'generate_reports', 'view_activity'],
            Role::defaultPermissionsFor(Role::MANAGER)
        );
        $this->assertEqualsCanonicalizing(
            array_keys(Role::PERMISSIONS),
            Role::defaultPermissionsFor(Role::ADMINISTRATOR)
        );
        $this->assertSame([], Role::defaultPermissionsFor('Custom Role'));
    }

    public function test_staff_route_access_matrix(): void
    {
        $staff = User::factory()->create();
        $task = $this->createTask();
        $completion = TaskComplete::create([
            'task_id' => $task->task_id,
            'user_id' => $staff->id,
            'completion_date' => now()->toDateString(),
        ]);

        $this->actingAs($staff)->get(route('operational.checklist'))->assertOk();
        $this->actingAs($staff)->get(route('operational.mortality'))->assertOk();
        $this->actingAs($staff)->get(route('operational.regraft'))->assertOk();
        $this->actingAs($staff)->get(route('operational.batches'))->assertOk();
        $this->actingAs($staff)->get(route('inventory.index'))->assertOk();
        $this->actingAs($staff)->get(route('inventory.stock-in'))->assertOk();
        $this->actingAs($staff)->get(route('inventory.stock-out'))->assertOk();

        $this->actingAs($staff)->get(route('operational.scion-varieties'))->assertForbidden();
        $this->actingAs($staff)->get(route('inventory.suppliers'))->assertForbidden();
        $this->actingAs($staff)->get(route('inventory.clients'))->assertForbidden();
        $this->actingAs($staff)->get(route('analytics.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('activity.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();

        $this->actingAs($staff)->post(route('operational.checklist.tasks.store'), [
            'task_name' => 'Not allowed',
            'priority' => 'low',
        ])->assertForbidden();
        $this->actingAs($staff)->put(route('operational.checklist.tasks.update', $task), [
            'task_name' => 'Not allowed',
            'priority' => 'low',
        ])->assertForbidden();
        $this->actingAs($staff)->delete(route('operational.checklist.tasks.destroy', $task))->assertForbidden();
        $this->actingAs($staff)->post(route('operational.checklist.completions.verify', $completion))->assertForbidden();
    }

    public function test_manager_route_access_matrix(): void
    {
        $manager = User::factory()->role(Role::MANAGER)->create();
        $staff = User::factory()->create();
        $task = $this->createTask();
        $completion = TaskComplete::create([
            'task_id' => $task->task_id,
            'user_id' => $staff->id,
            'completion_date' => now()->toDateString(),
        ]);
        $mortality = MortalityRecord::create([
            'batch_id' => $this->createBatch($staff)->batch_id,
            'user_id' => $staff->id,
            'quantity' => 1,
            'cause' => 'Disease',
            'recorded_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('operational.checklist'))->assertOk();
        $this->actingAs($manager)->post(route('operational.checklist.tasks.store'), [
            'task_name' => 'Manager task',
            'priority' => 'high',
        ])->assertRedirect(route('operational.checklist'));
        $this->actingAs($manager)->put(route('operational.checklist.tasks.update', $task), [
            'task_name' => 'Manager renamed task',
            'priority' => 'low',
        ])->assertRedirect(route('operational.checklist'));
        $this->actingAs($manager)->post(route('operational.checklist.completions.verify', $completion))
            ->assertRedirect(route('operational.checklist'));

        $this->actingAs($manager)->post(route('operational.checklist.tasks.complete', $task))->assertForbidden();
        $this->actingAs($manager)->get(route('operational.mortality'))->assertForbidden();
        $this->actingAs($manager)->get(route('operational.regraft'))->assertForbidden();
        $this->actingAs($manager)->get(route('operational.batches'))->assertForbidden();
        $this->actingAs($manager)->get(route('inventory.stock-in'))->assertForbidden();
        $this->actingAs($manager)->get(route('inventory.stock-out'))->assertForbidden();
        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('corrections.mortality', $mortality))->assertForbidden();

        $this->actingAs($manager)->get(route('inventory.index'))->assertOk();
        $this->actingAs($manager)->get(route('operational.scion-varieties'))->assertOk();
        $this->actingAs($manager)->get(route('inventory.suppliers'))->assertOk();
        $this->actingAs($manager)->get(route('inventory.clients'))->assertOk();
        $this->actingAs($manager)->get(route('analytics.index'))->assertOk();
        $this->actingAs($manager)->get(route('activity.index'))->assertOk();
    }

    public function test_administrator_route_access_matrix(): void
    {
        $admin = User::factory()->admin()->create();
        $task = $this->createTask();

        foreach ([
            'operational.checklist',
            'operational.mortality',
            'operational.regraft',
            'operational.batches',
            'operational.scion-varieties',
            'inventory.index',
            'inventory.stock-in',
            'inventory.stock-out',
            'inventory.suppliers',
            'inventory.clients',
            'analytics.index',
            'analytics.stock-ledger',
            'activity.index',
            'users.index',
        ] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }

        $this->actingAs($admin)->post(route('operational.checklist.tasks.store'), [
            'task_name' => 'Admin task',
            'priority' => 'medium',
        ])->assertRedirect(route('operational.checklist'));
        $this->actingAs($admin)->post(route('operational.checklist.tasks.complete', $task))
            ->assertRedirect(route('operational.checklist'));
    }

    public function test_monthly_inventory_view_does_not_imply_record_authority(): void
    {
        foreach ([['manage_stock', 'B001'], ['manage_inventory', 'B002']] as [$permissions, $code]) {
            $user = $this->userWith([$permissions]);
            $batch = $this->createBatch($user, $code);

            $this->actingAs($user)->get(route('inventory.index'))->assertOk();
            $this->actingAs($user)->post(route('inventory.snapshots.store'), [
                'batch_id' => $batch->batch_id,
                'inventory_date' => '2026-09-15',
            ])->assertForbidden();
        }
    }

    public function test_record_operations_can_record_a_monthly_inventory_snapshot(): void
    {
        $user = $this->userWith(['record_operations']);
        $batch = $this->createBatch($user);

        $this->actingAs($user)->get(route('inventory.index'))->assertOk();
        $this->actingAs($user)->post(route('inventory.snapshots.store'), [
            'batch_id' => $batch->batch_id,
            'inventory_date' => '2026-09-15',
        ])->assertRedirect(route('inventory.index'));

        $this->assertDatabaseHas('inventories', ['batch_id' => $batch->batch_id]);
    }

    public function test_staff_cannot_create_inline_suppliers_or_clients(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->post(route('inventory.suppliers.store'), [
            'supplier_name' => 'Nope Supplier',
            'contact_number' => '0999',
            'address' => 'Nowhere',
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('inventory.clients.store'), [
            'client_name' => 'Nope Client',
            'contact_number' => '0999',
            'address' => 'Nowhere',
        ])->assertForbidden();
    }

    public function test_manager_and_administrator_can_create_inline_suppliers_and_clients(): void
    {
        foreach ([User::factory()->role(Role::MANAGER)->create(), User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->post(route('inventory.suppliers.store'), [
                'supplier_name' => 'Supplier '.$actor->id,
                'contact_number' => '0999',
                'address' => 'Somewhere',
            ])->assertRedirect(route('inventory.stock-in'));

            $this->actingAs($actor)->post(route('inventory.clients.store'), [
                'client_name' => 'Client '.$actor->id,
                'contact_number' => '0999',
                'address' => 'Somewhere',
            ])->assertRedirect(route('inventory.stock-out'));
        }

        $this->assertDatabaseCount('suppliers', 2);
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_inline_master_data_buttons_follow_manage_inventory(): void
    {
        $staff = User::factory()->create();
        $both = $this->userWith(['manage_stock', 'manage_inventory']);

        $this->actingAs($staff)->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Not listed? Ask a Manager to add it.')
            ->assertDontSee('New Supplier')
            ->assertDontSee('id="supplierModal"', false);

        $this->actingAs($staff)->get(route('inventory.stock-out'))
            ->assertOk()
            ->assertSee('Not listed? Ask a Manager to add it.')
            ->assertDontSee('New Client')
            ->assertDontSee('id="clientModal"', false);

        $this->actingAs($both)->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('New Supplier')
            ->assertSee('id="supplierModal"', false);

        $this->actingAs($both)->get(route('inventory.stock-out'))
            ->assertOk()
            ->assertSee('New Client')
            ->assertSee('id="clientModal"', false);
    }

    public function test_staff_cannot_manage_or_verify_checklist_tasks_but_can_complete_one(): void
    {
        $staff = User::factory()->create();
        $task = $this->createTask();
        $otherTask = $this->createTask('Other task');

        $this->actingAs($staff)->get(route('operational.checklist'))
            ->assertOk()
            ->assertSee('Daily Checklist')
            ->assertDontSee('Add Task')
            ->assertDontSee('Edit Task')
            ->assertDontSee('Delete Task');

        $this->actingAs($staff)->post(route('operational.checklist.tasks.store'), [
            'task_name' => 'Forbidden task',
            'priority' => 'low',
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('operational.checklist.tasks.complete', $task), [
            'remark' => 'Done.',
        ])->assertRedirect(route('operational.checklist'));

        $completion = TaskComplete::where('task_id', $task->task_id)->firstOrFail();
        $this->assertNull($completion->verified_by_id);
        $this->assertNull($completion->verified_at);

        $this->actingAs($staff)->post(route('operational.checklist.completions.verify', $completion))
            ->assertForbidden();

        $this->assertNotNull($otherTask->fresh());
    }

    public function test_manager_can_manage_and_verify_but_cannot_complete(): void
    {
        $manager = User::factory()->role(Role::MANAGER)->create();
        $staff = User::factory()->create();
        $task = $this->createTask();

        TaskComplete::create([
            'task_id' => $task->task_id,
            'user_id' => $staff->id,
            'completion_date' => now()->toDateString(),
            'remark' => 'Staff remark.',
        ]);

        $completion = TaskComplete::where('task_id', $task->task_id)->firstOrFail();

        $this->actingAs($manager)->get(route('operational.checklist'))
            ->assertOk()
            ->assertSee('Add Task')
            ->assertSee('Verify');

        $this->actingAs($manager)->post(route('operational.checklist.completions.verify', $completion))
            ->assertRedirect(route('operational.checklist'));

        $completion->refresh();
        $this->assertSame($manager->id, $completion->verified_by_id);
        $this->assertNotNull($completion->verified_at);

        $this->actingAs($manager)->post(route('operational.checklist.completions.verify', $completion))
            ->assertRedirect(route('operational.checklist'))
            ->assertSessionHas('error');

        $second = $this->createTask('Second task');
        $this->actingAs($manager)->post(route('operational.checklist.tasks.complete', $second))
            ->assertForbidden();
        $this->assertDatabaseMissing('task_completes', ['task_id' => $second->task_id]);
    }

    public function test_user_management_shows_templates_descriptions_and_help(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk()
            ->assertSee('What each role can do')
            ->assertSee('Apply role defaults')
            ->assertSee('To create a new role, type a new role name and tick what it can do. Roles are saved per user.')
            ->assertSee('id="addUserTemplate"', false)
            ->assertSee('id="editUserTemplate"', false)
            ->assertSee('Maintain Scion Variety, Client and Supplier lists')
            ->assertSee('Record Stock In and Stock Out')
            ->assertSee('Manage and verify Daily Checklist tasks')
            ->assertSee('Mark assigned Daily Checklist tasks as completed')
            ->assertSee('Create, edit, delete and verify Daily Checklist tasks.')
            ->assertSee('"Staff":["record_operations","manage_stock","complete_checklist"]', false);
    }

    public function test_saving_a_custom_role_and_permissions_does_not_auto_apply_defaults(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.manage.store'), [
            'name' => 'Field Supervisor',
            'email' => 'supervisor@example.com',
            'role' => 'Field Supervisor',
            'permissions' => ['complete_checklist', 'view_activity'],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'supervisor@example.com')->firstOrFail();
        $this->assertSame('Field Supervisor', $created->role);
        $this->assertEqualsCanonicalizing(['complete_checklist', 'view_activity'], $created->permissions);
    }

    public function test_sidebar_only_shows_authorized_sections_and_the_role(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Daily Checklist')
            ->assertSee('Signed in')
            ->assertSee('Staff')
            ->assertDontSee('Operational Analytics')
            ->assertDontSee('User Management')
            ->assertDontSee('Stock Ledger');

        $manager = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Daily Checklist')
            ->assertSee('Operational Analytics')
            ->assertSee('Management')
            ->assertSee('Supplier')
            ->assertDontSee('Stock In');

        $custom = $this->userWith(['complete_checklist']);

        $this->actingAs($custom)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Daily Checklist')
            ->assertDontSee('<h6 class="nav-title">Inventory</h6>', false)
            ->assertDontSee('<h6 class="nav-title">Analytics</h6>', false);
    }

    public function test_dashboard_hides_analytics_without_view_analytics(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Quick Actions')
            ->assertSee('Record Mortality')
            ->assertSee('Stock In')
            ->assertSee('Daily Checklist')
            ->assertDontSee('Total Grafted')
            ->assertDontSee('Crude Mortality Rate')
            ->assertDontSee('Recent Activities');

        $manager = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Grafted')
            ->assertSee('Total Active Inventory')
            ->assertSee('Crude Mortality Rate')
            ->assertSee('Reports')
            ->assertDontSee('Stock In');

        $minimal = $this->userWith(['manage_users']);

        $this->actingAs($minimal)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Quick Actions')
            ->assertDontSee('Total Grafted')
            ->assertDontSee('Recent Activities');
    }

    public function test_friendly_403_page_name_the_role_and_required_access(): void
    {
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->get(route('analytics.index'));

        $response->assertForbidden()
            ->assertSee("This area isn't available for your role", false)
            ->assertSee('Staff')
            ->assertSee('View Analytics')
            ->assertSee('Back to Dashboard')
            ->assertSee('Ask an Administrator if you need access.')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('Stack trace')
            ->assertDontSee('You do not have permission to access this area.');
    }

    public function test_friendly_404_page_has_no_technical_details(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee("We couldn't find that page", false)
            ->assertSee('Back to Dashboard')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('Stack trace');
    }
}
