<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePermission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardRestrictionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Restricted User',
            'email' => 'restricted.user@example.com',
            'role' => Role::STAFF,
            'permissions' => ['record_operations'],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_administrator_only_permissions_are_rejected_for_custom_roles(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (Role::ADMIN_ONLY_PERMISSIONS as $key) {
            $email = "custom.{$key}@example.com";

            $this->actingAs($admin)
                ->post(route('users.manage.store'), $this->payload([
                    'email' => $email,
                    'role' => 'Supervisor',
                    'permissions' => [$key],
                ]))
                ->assertSessionHasErrors('permissions', null, 'user');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_an_administrator_may_hold_administrator_only_permissions_without_gaining_correct_records(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->payload([
                'email' => 'second.admin@example.com',
                'role' => Role::ADMINISTRATOR,
                'permissions' => ['view_activity'],
            ]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $created = User::where('email', 'second.admin@example.com')->firstOrFail();

        $this->assertTrue($created->hasPermission('manage_users'));
        $this->assertFalse($created->hasPermission('correct_records'));
    }

    public function test_reserved_administrator_role_name_variants_are_rejected_on_save(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['administrator', 'ADMINISTRATOR'] as $index => $variant) {
            $email = "variant.{$index}@example.com";

            $this->actingAs($admin)
                ->post(route('users.manage.store'), $this->payload([
                    'email' => $email,
                    'role' => $variant,
                    'permissions' => ['view_activity'],
                ]))
                ->assertSessionHasErrors('role', null, 'user');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_a_whitespace_padded_administrator_role_name_is_rejected_when_it_reaches_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withoutMiddleware(TrimStrings::class)
            ->post(route('users.manage.store'), $this->payload([
                'email' => 'padded.admin@example.com',
                'role' => ' Administrator ',
                'permissions' => ['view_activity'],
            ]))
            ->assertSessionHasErrors('role', null, 'user');

        $this->assertDatabaseMissing('users', ['email' => 'padded.admin@example.com']);
    }

    public function test_an_existing_whitespace_padded_administrator_account_is_flagged_and_never_rewritten(): void
    {
        $padded = User::factory()->create([
            'role' => ' Administrator ',
            'permissions' => ['view_activity'],
        ]);

        $this->assertFalse($padded->isAdministrator());
        $this->assertTrue($padded->needsPermissionCorrection());

        $this->actingAs($padded)
            ->get(route('inventory.index'))
            ->assertForbidden();

        $this->assertSame(' Administrator ', $padded->fresh()->role);
        $this->assertSame(['view_activity'], $padded->fresh()->permissions);
    }

    public function test_only_an_existing_administrator_can_assign_the_administrator_role(): void
    {
        $actor = User::factory()->create([
            'role' => 'Supervisor',
            'permissions' => ['manage_users'],
        ]);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->withoutMiddleware(EnsurePermission::class)
            ->post(route('users.manage.store'), $this->payload([
                'email' => 'not.allowed@example.com',
                'role' => Role::ADMINISTRATOR,
                'permissions' => ['manage_users'],
            ]))
            ->assertSessionHasErrors('role', null, 'user');

        $this->assertDatabaseMissing('users', ['email' => 'not.allowed@example.com']);

        $this->actingAs($actor)
            ->withoutMiddleware(EnsurePermission::class)
            ->put(route('users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'role' => Role::ADMINISTRATOR,
                'permissions' => ['manage_users'],
            ])
            ->assertSessionHasErrors('role', null, 'user');

        $this->assertSame(Role::STAFF, $target->fresh()->roleLabel());
    }

    public function test_a_non_administrator_cannot_edit_an_administrator_account(): void
    {
        $admin = User::factory()->admin()->create();
        $actor = User::factory()->create([
            'role' => 'Supervisor',
            'permissions' => ['manage_users'],
        ]);

        $this->actingAs($actor)
            ->withoutMiddleware(EnsurePermission::class)
            ->put(route('users.update', $admin), [
                'name' => 'Renamed Administrator',
                'email' => $admin->email,
                'role' => Role::ADMINISTRATOR,
                'permissions' => ['manage_users'],
            ])
            ->assertSessionHasErrors('role', null, 'user');

        $this->assertSame($admin->name, $admin->fresh()->name);
    }

    public function test_multi_key_routes_recheck_hard_restrictions_before_any_of_access(): void
    {
        $invalid = User::factory()->create([
            'role' => Role::MANAGER,
            'permissions' => ['manage_users', 'manage_inventory'],
        ]);

        $this->actingAs($invalid)
            ->get(route('inventory.index'))
            ->assertForbidden();

        $valid = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($valid)
            ->get(route('inventory.index'))
            ->assertOk();
    }

    public function test_reserved_role_name_variants_are_not_the_administrator_identity(): void
    {
        $variant = User::factory()->create([
            'role' => 'administrator',
            'permissions' => ['manage_inventory'],
        ]);

        $this->assertFalse($variant->isAdministrator());
        $this->assertTrue($variant->needsPermissionCorrection());

        $this->actingAs($variant)
            ->get(route('inventory.index'))
            ->assertForbidden();
    }

    public function test_user_management_flags_invalid_accounts_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $invalid = User::factory()->create([
            'role' => Role::MANAGER,
            'permissions' => ['manage_users', 'manage_inventory'],
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Needs correction')
            ->assertSee($invalid->name);

        $this->assertSame(Role::MANAGER, $invalid->fresh()->roleLabel());
        $this->assertEqualsCanonicalizing(['manage_users', 'manage_inventory'], $invalid->fresh()->permissions);
    }

    public function test_user_management_does_not_flag_valid_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('Needs correction');
    }

    public function test_user_management_flags_reserved_role_name_variants_without_rewriting_them(): void
    {
        $admin = User::factory()->admin()->create();
        $padded = User::factory()->create([
            'role' => ' Administrator ',
            'permissions' => ['view_activity'],
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Needs correction');

        $this->assertSame(' Administrator ', $padded->fresh()->role);
        $this->assertSame(['view_activity'], $padded->fresh()->permissions);
    }

    public function test_submit_and_verify_are_mutually_exclusive_for_custom_roles(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->payload([
                'email' => 'submit.verify@example.com',
                'role' => 'Supervisor',
                'permissions' => ['submit_operations', 'verify_operations'],
            ]))
            ->assertSessionHasErrors('permissions', null, 'user');

        $this->assertDatabaseMissing('users', ['email' => 'submit.verify@example.com']);
    }

    public function test_an_administrator_cannot_hold_submit_or_verify(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['submit_operations', 'verify_operations'] as $key) {
            $email = "admin.{$key}@example.com";

            $this->actingAs($admin)
                ->post(route('users.manage.store'), $this->payload([
                    'email' => $email,
                    'role' => Role::ADMINISTRATOR,
                    'permissions' => [$key],
                ]))
                ->assertSessionHasErrors('permissions', null, 'user');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_a_custom_verify_holder_is_a_valid_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.manage.store'), $this->payload([
                'email' => 'custom.verifier@example.com',
                'role' => 'Checker',
                'permissions' => ['verify_operations'],
            ]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $created = User::where('email', 'custom.verifier@example.com')->firstOrFail();

        $this->assertTrue($created->hasPermission('verify_operations'));
        $this->assertFalse($created->needsPermissionCorrection());
    }

    public function test_an_invalid_submit_verify_account_is_denied_and_flagged_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $invalid = User::factory()->create([
            'role' => Role::MANAGER,
            'permissions' => ['submit_operations', 'verify_operations', 'manage_inventory'],
        ]);

        $this->assertTrue($invalid->needsPermissionCorrection());

        $this->actingAs($invalid)
            ->get(route('inventory.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Needs correction');

        $this->assertEqualsCanonicalizing(
            ['submit_operations', 'verify_operations', 'manage_inventory'],
            $invalid->fresh()->permissions,
        );
    }
}
