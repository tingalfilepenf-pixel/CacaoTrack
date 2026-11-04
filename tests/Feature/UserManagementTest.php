<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function lastAdministratorDemotion(User $target, string $role): bool
    {
        $controller = new class extends UserController
        {
            public function check(User $target, string $role): bool
            {
                return $this->isLastAdministratorDemotion($target, $role);
            }
        };

        return $controller->check($target, $role);
    }

    private function controllerWithAdministratorCount(int $count): UserController
    {
        return new class($count) extends UserController
        {
            public function __construct(private int $count) {}

            protected function administratorCount(): int
            {
                return $this->count;
            }
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestAs(User $actor, string $method, string $uri, array $data = []): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setUserResolver(fn () => $actor);
        $this->app->instance('request', $request);

        return $request;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function userPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Staff Member',
            'email' => 'staff.member@example.com',
            'role' => Role::STAFF,
            'permissions' => ['record_operations', 'view_activity'],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    private function createBatchFor(User $owner, string $code = 'B900'): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Test Variety '.$code]);

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

    public function test_guests_are_redirected_from_users_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_users(): void
    {
        $this->post(route('users.manage.store'), $this->userPayload())->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_user_management(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_non_admin_store_is_forbidden(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('users.manage.store'), $this->userPayload(['email' => 'nope@example.com']))
            ->assertForbidden();
    }

    public function test_non_admin_update_is_forbidden(): void
    {
        $staff = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($staff)
            ->put(route('users.update', $victim), [
                'name' => 'Hacked',
                'email' => $victim->email,
                'role' => Role::STAFF,
                'permissions' => ['view_activity'],
            ])
            ->assertForbidden();
    }

    public function test_non_admin_destroy_is_forbidden(): void
    {
        $staff = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($staff)
            ->delete(route('users.destroy', $victim))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_hashed_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->userPayload(['email' => 'newstaff@example.com']))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $created = User::where('email', 'newstaff@example.com')->firstOrFail();
        $this->assertSame(Role::STAFF, $created->roleLabel());
        $this->assertNotSame('password123', $created->password);
        $this->assertEqualsCanonicalizing(['record_operations', 'view_activity'], $created->permissions);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_USER_CREATED,
        ]);
    }

    public function test_duplicate_emails_are_rejected(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->userPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors(['email'], null, 'user');
    }

    public function test_missing_permissions_and_short_password_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->userPayload([
                'email' => 'noperms@example.com',
                'permissions' => [],
            ]))
            ->assertSessionHasErrors(['permissions'], null, 'user');

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->userPayload([
                'email' => 'shortpass@example.com',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]))
            ->assertSessionHasErrors(['password'], null, 'user');
    }

    public function test_invalid_permission_keys_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->userPayload([
                'email' => 'badperm@example.com',
                'permissions' => ['not_a_real_permission'],
            ]))
            ->assertSessionHasErrors(['permissions.0'], null, 'user');
    }

    public function test_administrator_always_keeps_manage_users(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->userPayload([
                'email' => 'newadmin@example.com',
                'role' => Role::ADMINISTRATOR,
                'permissions' => ['view_activity'],
            ]))
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'newadmin@example.com')->firstOrFail();
        $this->assertTrue($created->hasPermission('manage_users'));
    }

    public function test_admin_can_update_a_user_and_their_permissions(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Renamed User',
                'email' => 'renamed@example.com',
                'role' => Role::MANAGER,
                'permissions' => ['manage_inventory', 'view_analytics'],
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Renamed User', $user->name);
        $this->assertSame('renamed@example.com', $user->email);
        $this->assertSame(Role::MANAGER, $user->roleLabel());
        $this->assertEqualsCanonicalizing(['manage_inventory', 'view_analytics'], $user->permissions);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_USER_UPDATED,
        ]);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => Role::STAFF,
                'permissions' => ['view_activity'],
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertSame(Role::ADMINISTRATOR, $admin->fresh()->roleLabel());
    }

    public function test_a_non_administrator_cannot_demote_an_administrator(): void
    {
        $admin = $this->admin();
        $manager = User::factory()->create([
            'role' => Role::MANAGER,
            'permissions' => array_keys(Role::PERMISSIONS),
        ]);

        $this->actingAs($manager)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => Role::STAFF,
                'permissions' => ['view_activity'],
            ])
            ->assertForbidden();

        $this->assertSame(Role::ADMINISTRATOR, $admin->fresh()->roleLabel());
    }

    public function test_the_last_administrator_demotion_predicate_is_true_for_manager_and_staff(): void
    {
        $admin = $this->admin();

        $this->assertTrue($this->lastAdministratorDemotion($admin, Role::MANAGER));
        $this->assertTrue($this->lastAdministratorDemotion($admin, Role::STAFF));
    }

    public function test_the_last_administrator_demotion_predicate_is_false_when_the_role_stays_administrator(): void
    {
        $admin = $this->admin();

        $this->assertFalse($this->lastAdministratorDemotion($admin, Role::ADMINISTRATOR));
    }

    public function test_the_last_administrator_demotion_predicate_is_false_with_two_administrators(): void
    {
        $admin = $this->admin();
        User::factory()->admin()->create();

        $this->assertFalse($this->lastAdministratorDemotion($admin, Role::STAFF));
    }

    public function test_the_last_administrator_demotion_guard_redirects_with_the_exact_message(): void
    {
        $actor = $this->admin();
        $target = User::factory()->admin()->create();

        $this->actingAs($actor);

        $request = $this->requestAs($actor, 'PUT', route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => Role::STAFF,
            'permissions' => ['view_activity'],
        ]);

        $response = $this->controllerWithAdministratorCount(1)->update($request, $target);

        $this->assertSame(route('users.index'), $response->getTargetUrl());
        $this->assertSame(
            'This administrator cannot be demoted because it is the last administrator account.',
            $response->getSession()->get('error'),
        );
        $this->assertSame(Role::ADMINISTRATOR, $target->fresh()->roleLabel());
    }

    public function test_the_last_administrator_delete_guard_redirects_with_the_exact_message(): void
    {
        $actor = $this->admin();
        $target = User::factory()->admin()->create();

        $this->actingAs($actor);

        $request = $this->requestAs($actor, 'DELETE', route('users.destroy', $target));

        $response = $this->controllerWithAdministratorCount(1)->destroy($request, $target);

        $this->assertSame(route('users.index'), $response->getTargetUrl());
        $this->assertSame(
            'This administrator cannot be deleted because it is the last administrator account.',
            $response->getSession()->get('error'),
        );
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_delete_is_blocked_when_the_user_has_operational_records(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $this->createBatchFor($owner);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $owner))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_admin_can_delete_an_unused_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_USER_DELETED,
        ]);
    }

    public function test_search_and_role_filters_work(): void
    {
        $admin = $this->admin();
        User::factory()->role(Role::MANAGER)->create(['name' => 'Filterable Manager', 'email' => 'filterme@example.com']);

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'filterme']))
            ->assertOk()
            ->assertSee('filterme@example.com');

        $this->actingAs($admin)
            ->get(route('users.index', ['role' => Role::MANAGER]))
            ->assertOk()
            ->assertSee('Filterable Manager');
    }

    public function test_empty_state_renders_for_unmatched_filters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'no-such-user-xyz']))
            ->assertOk()
            ->assertSee('No users found for the current filters.');
    }
}
