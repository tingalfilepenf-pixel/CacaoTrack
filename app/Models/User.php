<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rule;

#[Fillable(['name', 'email', 'password', 'role', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    /**
     * Does the user hold the given access restriction? (FR-02)
     *
     * Access restrictions live directly on the user (`users.permissions` JSON)
     * after the approved denormalization; the key catalog is
     * `Role::PERMISSIONS` (RULE 6).
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    public function isAdministrator(): bool
    {
        return $this->roleLabel() === Role::ADMINISTRATOR;
    }

    /**
     * Whether the user may permanently delete checklist tasks. This is a
     * Manager/Administrator role restriction (FR-03 refinement) centralized on
     * `Role::CHECKLIST_DELETE_ROLES` (RULE 6).
     */
    public function canDeleteChecklistTasks(): bool
    {
        return in_array($this->roleLabel(), Role::CHECKLIST_DELETE_ROLES, true);
    }

    /**
     * Display label for the user's role (presentation only).
     */
    public function roleLabel(): string
    {
        return $this->role ?: Role::DEFAULT_ROLE;
    }

    /**
     * Centralized validation for own-profile updates (RULE 6).
     *
     * Note: role/permissions are deliberately absent — a user cannot change
     * their own role or access restrictions. Only name/email validated here
     * survive into the update because the controller persists
     * `$validator->validated()`.
     *
     * @return array<string, mixed>
     */
    public static function profileRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
        ];
    }

    /**
     * Centralized validation for own-password changes (RULE 6).
     *
     * @return array<string, mixed>
     */
    public static function passwordRules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Centralized validation for manage-users user creation (RULE 6, FR-02).
     * Password is required on creation and hashed by the model's `hashed`
     * cast; it is never displayed.
     *
     * @return array<string, mixed>
     */
    public static function managementCreateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(array_keys(Role::PERMISSIONS))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Centralized validation for manage-users user updates (RULE 6, FR-02).
     * Password changes stay on the Profile page; management edits cover
     * name/email/role/permissions so no plaintext handling is introduced here.
     *
     * @return array<string, mixed>
     */
    public static function managementUpdateRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'role' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(array_keys(Role::PERMISSIONS))],
        ];
    }
}
