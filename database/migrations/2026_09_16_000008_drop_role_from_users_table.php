<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the legacy `users.role` string now that the backfill to the
     * normalized `users.role_id` foreign key has been explicitly verified
     * (every existing user had exactly one valid role_id whose name matched
     * the previous `role` value).
     *
     * Kept as a SEPARATE migration from 2026_09_16_000007 on purpose so the
     * backfill could be validated before the column was removed.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations: recreate the string column and repopulate it
     * from the authoritative role names.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('Staff')->after('password');
        });

        DB::statement('UPDATE users LEFT JOIN roles ON users.role_id = roles.role_id SET users.role = COALESCE(roles.role_name, \'Staff\')');
    }
};
