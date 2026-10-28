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
     * is stored inside `users.permissions`; the value is the plain-language
     * label shown in the interface.
     *
     * @var array<string, string>
     */
    public const PERMISSIONS = [
        'manage_users' => 'Manage Users and Roles',
        'manage_inventory' => 'Maintain Scion Variety, Client and Supplier lists',
        'manage_stock' => 'Record Stock In and Stock Out',
        'record_operations' => 'Record Mortality, Regraft, Batches and Monthly Inventory',
        'manage_checklist' => 'Manage and verify Daily Checklist tasks',
        'complete_checklist' => 'Mark assigned Daily Checklist tasks as completed',
        'correct_records' => 'Correct Mistakes (Edit Recorded Entries)',
        'view_analytics' => 'View Analytics',
        'generate_reports' => 'Generate Reports',
        'view_activity' => 'View Activity History',
    ];

    /**
     * One-line, plain-language descriptions for every access restriction. Kept
     * beside {@see PERMISSIONS} so labels and descriptions can never drift
     * apart (RULE 6).
     *
     * @var array<string, string>
     */
    public const PERMISSION_DESCRIPTIONS = [
        'manage_users' => 'Add, edit and remove user accounts and their access.',
        'manage_inventory' => 'Keep the Scion Variety, Client and Supplier lists up to date.',
        'manage_stock' => 'Record seedlings and scions received and released.',
        'record_operations' => 'Record mortality, regraft sub-batches, batches and monthly inventory snapshots.',
        'manage_checklist' => 'Create, edit, delete and verify Daily Checklist tasks.',
        'complete_checklist' => 'Mark the Daily Checklist tasks assigned to you as completed.',
        'correct_records' => 'Fix a recorded entry and keep the before/after audit trail.',
        'view_analytics' => 'Open the analytics pages, charts and exports.',
        'generate_reports' => 'Save a report snapshot with the Generate Report action.',
        'view_activity' => 'Read the Activity History audit trail.',
    ];

    /**
     * Default access restrictions for the built-in roles.
     *
     * provisional — pending panel confirmation
     *
     * Staff may complete checklist tasks but may not manage or verify them;
     * Managers manage and verify the checklist but do not take stock or record
     * operational entries. These defaults are only applied when an
     * administrator explicitly applies them — stored permissions on existing
     * accounts are never changed automatically.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_PERMISSIONS_BY_ROLE = [
        self::ADMINISTRATOR => [
            'manage_users',
            'manage_inventory',
            'manage_stock',
            'record_operations',
            'manage_checklist',
            'complete_checklist',
            'correct_records',
            'view_analytics',
            'generate_reports',
            'view_activity',
        ],
        self::MANAGER => [
            'manage_inventory',
            'manage_checklist',
            'view_analytics',
            'generate_reports',
            'view_activity',
        ],
        self::STAFF => [
            'record_operations',
            'manage_stock',
            'complete_checklist',
        ],
    ];

    /**
     * Access restrictions that are administrator-only by default.
     *
     * @var list<string>
     */
    public const ADMIN_ONLY_PERMISSIONS = ['manage_users', 'correct_records'];

    public static function isSystem(string $roleName): bool
    {
        return in_array($roleName, self::SYSTEM_ROLES, true);
    }

    /**
     * Default access restrictions for a role name, used when seeding, building
     * factory/test users and applying a role template in User Management.
     *
     * The defaults live in one place — {@see DEFAULT_PERMISSIONS_BY_ROLE}
     * (RULE 6). A custom role name has an empty template until an administrator
     * ticks the permissions it needs.
     *
     * @return list<string>
     */
    public static function defaultPermissionsFor(string $roleName): array
    {
        return self::DEFAULT_PERMISSIONS_BY_ROLE[$roleName] ?? [];
    }

    /**
     * Role templates offered by the User Management form: the three built-in
     * roles plus a blank starting point.
     *
     * @return list<string>
     */
    public static function templateRoleNames(): array
    {
        return [...self::ROLES, 'Blank'];
    }
}
