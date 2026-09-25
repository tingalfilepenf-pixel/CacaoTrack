<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize `users.role` into the new `users.role_id` foreign key
     * (approved ERD revision — ERD §2.1/§2.19, replacing ERD §9.1's
     * former "no Roles table" rule).
     *
     * IMPORTANT: this migration only adds the nullable FK column. The old
     * `role` string column is intentionally left in place and is dropped
     * by a separate later migration ONLY after the backfill has been
     * explicitly verified (every user has exactly one valid role_id whose
     * name matches the previous `role` value).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('password')
                ->constrained('roles', 'role_id')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
