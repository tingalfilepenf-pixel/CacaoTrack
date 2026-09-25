<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * A user carries its role name and access restrictions directly
     * (ERD §2.1); the built-in names and permission keys come from the
     * `Role` catalog.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::DEFAULT_ROLE,
            'permissions' => Role::defaultPermissionsFor(Role::DEFAULT_ROLE),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Place the user in a specific role with that role's default permissions.
     */
    public function role(string $roleName): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $roleName,
            'permissions' => Role::defaultPermissionsFor($roleName),
        ]);
    }

    /**
     * Place the user in the protected Administrator role.
     */
    public function admin(): static
    {
        return $this->role(Role::ADMINISTRATOR);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
