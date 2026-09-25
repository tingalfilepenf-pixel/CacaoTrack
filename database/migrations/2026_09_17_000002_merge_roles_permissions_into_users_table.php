<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approved ERD revision (ERD §2.1/§9.1): merge the `roles` and
     * `role_permissions` tables into `users` to reduce the table count.
     *
     * This is a deliberate denormalization for a simplified conceptual model:
     * a user stores its role name (`users.role`) and its access restrictions
     * (`users.permissions` JSON) directly. No data is fabricated — the new
     * columns are backfilled from the existing normalized rows before the two
     * tables are dropped.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 100)->default('Staff')->after('password');
            $table->json('permissions')->nullable()->after('role');
        });

        if (Schema::hasTable('roles') && Schema::hasColumn('users', 'role_id')) {
            foreach (DB::table('roles')->get() as $role) {
                $permissions = DB::table('role_permissions')
                    ->where('role_id', $role->role_id)
                    ->orderBy('permission')
                    ->pluck('permission')
                    ->values()
                    ->all();

                DB::table('users')
                    ->where('role_id', $role->role_id)
                    ->update([
                        'role' => $role->role_name,
                        'permissions' => json_encode($permissions),
                    ]);
            }

            // Any user without a matching role falls back to the default role
            // with the default (non-manage_users) access restrictions.
            $defaults = json_encode([
                'manage_inventory', 'manage_stock', 'record_operations',
                'view_analytics', 'generate_reports', 'view_activity',
            ]);

            DB::table('users')
                ->where(function ($query): void {
                    $query->whereNull('role')->orWhere('role', '');
                })
                ->update(['role' => 'Staff', 'permissions' => $defaults]);
        }

        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }

    /**
     * Reverse the migration: recreate the normalized access-control tables and
     * rebuild them from the merged `users.role` / `users.permissions` columns.
     */
    public function down(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('role_id');
            $table->string('role_name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->bigIncrements('role_permission_id');
            $table->foreignId('role_id')->constrained('roles', 'role_id')->cascadeOnDelete();
            $table->string('permission');
            $table->unique(['role_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')
                ->constrained('roles', 'role_id')->restrictOnDelete();
        });

        foreach (DB::table('users')->select('role')->distinct()->pluck('role') as $roleName) {
            $roleName = $roleName ?: 'Staff';

            $roleId = DB::table('roles')->insertGetId([
                'role_name' => $roleName,
                'description' => null,
                'is_system' => in_array($roleName, ['Administrator'], true) ? 1 : 0,
            ]);

            $raw = DB::table('users')->where('role', $roleName)->value('permissions');
            $permissions = $raw ? (json_decode($raw, true) ?: []) : [];

            foreach (array_unique($permissions) as $permission) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission' => $permission,
                ]);
            }

            DB::table('users')->where('role', $roleName)->update(['role_id' => $roleId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions']);
        });
    }
};
