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
        'submit_operations' => 'Submit Operational Transactions',
        'verify_operations' => 'Verify Operational Submissions',
        'view_operational_records' => 'View Operational Records',
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
        'submit_operations' => 'Submit mortality and stock-out entries for Manager verification.',
        'verify_operations' => 'Review, approve or reject operational submissions from other users.',
        'view_operational_records' => 'Read operational submissions and the official records they create.',
        'manage_checklist' => 'Create, edit, delete and verify Daily Checklist tasks.',
        'complete_checklist' => 'Mark the Daily Checklist tasks assigned to you as completed.',
        'correct_records' => 'Fix a recorded entry and keep the before/after audit trail.',
        'view_analytics' => 'Open the analytics pages, charts and exports.',
        'generate_reports' => 'Save a report snapshot with the Generate Report action.',
        'view_activity' => 'Read the Activity History audit trail.',
    ];

    /**
     * One-line plain-language description of each built-in role.
     *
     * The Can and Cannot lists shown in User Management are generated from
     * {@see PERMISSIONS}, {@see PERMISSION_DESCRIPTIONS} and
     * {@see DEFAULT_PERMISSIONS_BY_ROLE}; only these role descriptions are new
     * role text (RULE 6).
     *
     * @var array<string, string>
     */
    public const ROLE_DESCRIPTIONS = [
        self::ADMINISTRATOR => 'Manages user accounts and system access, and reviews operational records read-only; cannot submit or verify operational transactions.',
        self::MANAGER => 'Keeps master data current, verifies operational submissions, manages the Daily Checklist, and reviews analytics, reports and activity history.',
        self::STAFF => 'Records operational entries and stock movements, submits operational transactions for verification, and completes assigned Daily Checklist tasks.',
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
            'view_operational_records',
        ],
        self::MANAGER => [
            'manage_inventory',
            'manage_checklist',
            'view_analytics',
            'generate_reports',
            'view_activity',
            'verify_operations',
        ],
        self::STAFF => [
            'record_operations',
            'manage_stock',
            'complete_checklist',
            'submit_operations',
        ],
    ];

    /**
     * Access restrictions that only the canonical Administrator role may hold.
     *
     * @var list<string>
     */
    public const ADMIN_ONLY_PERMISSIONS = ['manage_users', 'correct_records'];

    public static function isSystem(string $roleName): bool
    {
        return in_array($roleName, self::SYSTEM_ROLES, true);
    }

    /**
     * Whether a role name is the reserved Administrator identity in any
     * capitalization or with surrounding whitespace.
     */
    public static function isReservedRoleName(?string $roleName): bool
    {
        return $roleName !== null
            && mb_strtolower(trim($roleName)) === mb_strtolower(self::ADMINISTRATOR);
    }

    /**
     * Whether a stored role name is the exact canonical Administrator identity.
     */
    public static function isCanonicalAdministrator(?string $roleName): bool
    {
        return $roleName === self::ADMINISTRATOR;
    }

    /**
     * Validation errors for assigning a role name. A reserved variant is never
     * normalized or renamed automatically.
     *
     * @return list<string>
     */
    public static function roleNameErrors(?string $roleName, bool $actorIsAdministrator): array
    {
        if (! self::isReservedRoleName($roleName)) {
            return [];
        }

        if (! self::isCanonicalAdministrator($roleName)) {
            return ['The Administrator role name is reserved and cannot use different capitalization or surrounding spaces.'];
        }

        if (! $actorIsAdministrator) {
            return ['Only an existing Administrator may assign or edit the Administrator role.'];
        }

        return [];
    }

    /**
     * Hard-restriction violations for a stored role name and permission list.
     * Read-only: never rewrites the role or the permissions.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function hardRestrictionViolations(?string $roleName, array $permissions): array
    {
        $violations = [];

        if ($roleName !== null && self::isReservedRoleName($roleName) && ! self::isCanonicalAdministrator($roleName)) {
            $violations[] = 'The Administrator role name is reserved; this account needs a corrected role name.';
        }

        if (! self::isCanonicalAdministrator($roleName)) {
            foreach (self::ADMIN_ONLY_PERMISSIONS as $key) {
                if (in_array($key, $permissions, true)) {
                    $violations[] = (self::PERMISSIONS[$key] ?? $key).' is restricted to the Administrator role.';
                }
            }
        }

        $canSubmit = in_array('submit_operations', $permissions, true);
        $canVerify = in_array('verify_operations', $permissions, true);

        if ($canSubmit && $canVerify) {
            $violations[] = 'Submitting and verifying operational transactions are mutually exclusive permissions.';
        }

        if (self::isCanonicalAdministrator($roleName) && ($canSubmit || $canVerify)) {
            $violations[] = 'Administrators cannot submit or verify operational transactions.';
        }

        return $violations;
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
     * Plain-language description for a built-in role; null for a custom role.
     */
    public static function descriptionFor(string $roleName): ?string
    {
        return self::ROLE_DESCRIPTIONS[$roleName] ?? null;
    }

    /**
     * Access restrictions a built-in role's template grants, in catalog order.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function canDo(string $roleName): array
    {
        $defaults = self::defaultPermissionsFor($roleName);

        return self::permissionDetails(array_filter(
            array_keys(self::PERMISSIONS),
            fn (string $key): bool => in_array($key, $defaults, true),
        ));
    }

    /**
     * Access restrictions outside a built-in role's template, in catalog order.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function cannotDo(string $roleName): array
    {
        $defaults = self::defaultPermissionsFor($roleName);

        return self::permissionDetails(array_filter(
            array_keys(self::PERMISSIONS),
            fn (string $key): bool => ! in_array($key, $defaults, true),
        ));
    }

    /**
     * @param  array<int, string>  $keys
     * @return list<array{key: string, label: string, description: string}>
     */
    private static function permissionDetails(array $keys): array
    {
        return array_values(array_map(
            fn (string $key): array => [
                'key' => $key,
                'label' => self::PERMISSIONS[$key],
                'description' => self::PERMISSION_DESCRIPTIONS[$key] ?? '',
            ],
            $keys,
        ));
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
