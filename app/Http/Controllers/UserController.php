<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * FR-02 User Management listing. Readable by any authenticated user
     * (mirrors the other Management pages); Add/Edit/Delete UI and routes
     * are restricted to holders of the `manage_users` access restriction
     * (see requireManageUsers() and the `permission:manage_users` middleware).
     *
     * A user's role name and access restrictions live directly on the user
     * (`users.role` + `users.permissions`), so this page is the single
     * management surface — no separate Roles table exists (ERD §2.1).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        $base = User::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, function ($query, $role): void {
                $query->where('role', $role);
            });

        $users = (clone $base)
            ->withCount('activityLogs')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $roleOptions = User::query()
            ->whereNotNull('role')
            ->distinct()
            ->orderBy('role')
            ->pluck('role')
            ->merge(Role::ROLES)
            ->unique()
            ->sort()
            ->values();

        $roleCounts = User::query()
            ->select('role', DB::raw('count(*) as aggregate'))
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'roleOptions' => $roleOptions,
            'permissionCatalog' => Role::PERMISSIONS,
            'roleCounts' => $roleCounts,
            'totalUsers' => (clone $base)->count(),
            'administratorsCount' => (clone $base)->where('role', Role::ADMINISTRATOR)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireManageUsers();

        $validator = Validator::make($request->all(), User::managementCreateRules());

        if ($validator->fails()) {
            return redirect()
                ->route('users.index')
                ->withErrors($validator, 'user')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['permissions'] = $this->normalizePermissions($validated['role'], $validated['permissions']);

        DB::transaction(function () use ($validated): void {
            $user = User::create($validated);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_USER_CREATED,
                "Created user account for {$user->email} ({$user->roleLabel()})."
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'User added successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->requireManageUsers();

        $validator = Validator::make($request->all(), User::managementUpdateRules($user->id));

        if ($validator->fails()) {
            return redirect()
                ->route('users.index')
                ->withErrors($validator, 'user')
                ->withInput(['edit_user_id' => $user->id]);
        }

        $validated = $validator->validated();

        if ((int) $request->user()->id === (int) $user->id && $validated['role'] !== $user->role) {
            return redirect()
                ->route('users.index')
                ->with('error', 'You cannot change your own role. Ask another administrator.');
        }

        if ($user->isAdministrator() && $validated['role'] !== Role::ADMINISTRATOR && $this->administratorCount() <= 1) {
            return redirect()
                ->route('users.index')
                ->with('error', 'This administrator cannot be demoted because it is the last administrator account.');
        }

        $validated['permissions'] = $this->normalizePermissions($validated['role'], $validated['permissions']);

        DB::transaction(function () use ($user, $validated): void {
            $user->update($validated);

            ActivityLog::record(
                null,
                ActivityLog::TYPE_USER_UPDATED,
                "Updated user account for {$user->email} ({$user->roleLabel()})."
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->requireManageUsers();

        if ((int) $request->user()->id === (int) $user->id) {
            return redirect()
                ->route('users.index')
                ->with('error', 'You cannot delete your own signed-in account.');
        }

        if ($user->isAdministrator() && $this->administratorCount() <= 1) {
            return redirect()
                ->route('users.index')
                ->with('error', 'This administrator cannot be deleted because it is the last administrator account.');
        }

        if ($this->hasOperationalRecords($user->id)) {
            return redirect()
                ->route('users.index')
                ->with('error', 'This user cannot be deleted because it has operational records (batches, transactions, or activity history).');
        }

        $email = $user->email;

        DB::transaction(function () use ($user, $email): void {
            $user->delete();

            ActivityLog::record(
                null,
                ActivityLog::TYPE_USER_DELETED,
                "Deleted user account for {$email}."
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Keep only catalog permission keys and guarantee the protected
     * Administrator role always holds `manage_users` (FR-02).
     *
     * @param  array<int, string>  $permissions
     * @return list<string>
     */
    private function normalizePermissions(string $role, array $permissions): array
    {
        $permissions = array_values(array_unique(array_intersect($permissions, array_keys(Role::PERMISSIONS))));

        if ($role === Role::ADMINISTRATOR && ! in_array('manage_users', $permissions, true)) {
            $permissions[] = 'manage_users';
        }

        return $permissions;
    }

    /**
     * Server-side authorization (FR-02/PRD §21): only holders of the
     * `manage_users` access restriction may mutate accounts (RULE 3 — no
     * invented permissions; the catalog is the single source).
     */
    private function requireManageUsers(): void
    {
        $viewer = request()->user();

        if (! $viewer || ! $viewer->hasPermission('manage_users')) {
            abort(403, 'Only administrators may manage user accounts.');
        }
    }

    private function administratorCount(): int
    {
        return User::where('role', Role::ADMINISTRATOR)->count();
    }

    /**
     * Users are referenced by operational tables with restrictOnDelete —
     * block deletion with a readable message instead of a query exception.
     *
     * The revised 15-entity model has no `stock_reversals` or
     * `mortality_adjustments` tables (removed in P1), so those legacy checks
     * are gone. Every remaining FK that references a user is covered:
     * batches/mortality/replanting/stock_ins/stock_outs/analytics/activity_logs
     * by `user_id`, and task_completes by both `user_id` and `verified_by_id`.
     */
    private function hasOperationalRecords(int $userId): bool
    {
        $tables = [
            'batches' => 'user_id',
            'mortality_records' => 'user_id',
            'replanting_records' => 'user_id',
            'stock_ins' => 'user_id',
            'stock_outs' => 'user_id',
            'analytics' => 'user_id',
            'activity_logs' => 'user_id',
            'task_completes' => 'user_id',
        ];

        foreach ($tables as $table => $column) {
            if (DB::table($table)->where($column, $userId)->exists()) {
                return true;
            }
        }

        return DB::table('task_completes')->where('verified_by_id', $userId)->exists();
    }
}
