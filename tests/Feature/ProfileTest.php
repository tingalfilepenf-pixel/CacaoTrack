<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_profile_to_login(): void
    {
        $this->get(route('profile.index'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_update_profile(): void
    {
        $this->put(route('profile.update'), [
            'name' => 'Nobody',
            'email' => 'nobody@example.com',
        ])->assertRedirect(route('login'));
    }

    public function test_guests_cannot_change_password(): void
    {
        $this->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_real_profile_values(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create([
            'name' => 'Real Name',
            'email' => 'real@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('profile.index'))
            ->assertOk()
            ->assertSee('Real Name')
            ->assertSee('real@example.com')
            ->assertSee('Manager')
            ->assertSee('Member Since');
    }

    public function test_profile_details_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHas('success');

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('updated@example.com', $user->fresh()->email);
        $this->assertSame(1, ActivityLog::where('activity_type', ActivityLog::TYPE_PROFILE_UPDATED)->count());
    }

    public function test_profile_update_requires_name_and_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.index'))
            ->put(route('profile.update'), [
                'name' => '',
                'email' => 'not-an-email',
            ]);

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHasErrors(['name', 'email'], null, 'profile');
    }

    public function test_profile_update_rejects_an_email_taken_by_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($user)
            ->from(route('profile.index'))
            ->put(route('profile.update'), [
                'name' => 'Still Me',
                'email' => 'taken@example.com',
            ]);

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHasErrors(['email'], null, 'profile');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertNotSame($other->id, $user->id);
    }

    public function test_users_cannot_escalate_their_own_role_through_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'Administrator',
            ])
            ->assertRedirect(route('profile.index'));

        $this->assertSame(Role::STAFF, $user->fresh()->roleLabel());
    }

    public function test_password_can_be_changed_with_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(1, ActivityLog::where('activity_type', ActivityLog::TYPE_PROFILE_UPDATED)->count());
    }

    public function test_password_change_rejects_a_wrong_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.index'))
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHasErrors(['current_password'], null, 'password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_requires_confirmation_match(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.index'))
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-123',
            ]);

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHasErrors(['password'], null, 'password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_rejects_short_passwords(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.index'))
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertRedirect(route('profile.index'));
        $response->assertSessionHasErrors(['password'], null, 'password');
    }

    public function test_user_role_helpers_expose_the_erd_roles(): void
    {
        $this->assertContains(Role::ADMINISTRATOR, Role::SYSTEM_ROLES);
        $this->assertArrayHasKey('manage_users', Role::PERMISSIONS);

        $user = User::factory()->admin()->create();

        $this->assertSame('Administrator', $user->roleLabel());
        $this->assertTrue($user->hasPermission('manage_users'));
        $this->assertArrayHasKey(ActivityLog::TYPE_PROFILE_UPDATED, ActivityLog::TYPES);
    }
}
