<?php

namespace App\Models;

/**
 * Fixed role + access-restriction catalog (RULE 6).
 *
 * This is a **code-only catalog helper** — it is NOT an Eloquent model and has
 * **no database table**. After the approved denormalization (ERD §2.1/§9.1),
 * a user's role name and access restrictions live directly on `Users`
 * (`users.role` string + `users.permissions` JSON); this class remains the
 * single source of truth for the built-in role names and the permission keys.
 */
class Role
{
    public const ADMINISTRATOR = 'Administrator';

    public const MANAGER = 'Manager';

    public const STAFF = 'Staff';

    /**
     * Default role assigned to a new account when none is supplied.
     */
    public const DEFAULT_ROLE = self::STAFF;

    /**
     * Built-in role names offered by the User Management form.
     *
     * @var list<string>
     */
    public const ROLES = [self::ADMINISTRATOR, self::MANAGER, self::STAFF];

    /**
     * Protected system roles. `Administrator` always holds `manage_users` and
     * cannot be demoted when it is the last administrator account.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLES = [self::ADMINISTRATOR];

    /**
     * Roles authorized to permanently delete an archived, history-free
     * checklist task (FR-03 refinement). This is a role-based restriction, not
     * a permission-catalog key, so it introduces no invented permission.
     *
     * @var list<string>
     */
    public const CHECKLIST_DELETE_ROLES = [self::ADMINISTRATOR, self::MANAGER];

    /**
     * The single source of truth for access-restriction keys (RULE 6). The key
     * is stored inside `users.permissions`; the value is the human-readable
     * label.
     *
     * @var array<string, string>
     */
    public const PERMISSIONS = [
        'manage_users' => 'Manage Users & Roles',
        'manage_inventory' => 'Manage Master Data & Batches',
        'manage_stock' => 'Perform Stock Transactions',
        'record_operations' => 'Record Operations (Checklist, Mortality, Replanting)',
        'view_analytics' => 'View Analytics',
        'generate_reports' => 'Generate Reports',
        'view_activity' => 'View Activity History',
    ];

    public static function isSystem(string $roleName): bool
    {
        return in_array($roleName, self::SYSTEM_ROLES, true);
    }

    /**
     * Default access restrictions for a role name, used when seeding or
     * building factory/test users. Administrator keeps every permission;
     * every other role gets all except `manage_users`.
     *
     * @return list<string>
     */
    public static function defaultPermissionsFor(string $roleName): array
    {
        $all = array_keys(self::PERMISSIONS);

        return $roleName === self::ADMINISTRATOR
            ? $all
            : array_values(array_diff($all, ['manage_users']));
    }
}
