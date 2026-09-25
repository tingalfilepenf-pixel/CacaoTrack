<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align the users table with the ERD Users.Role attribute (ERD §2.1).
     *
     * The source ERD stores Role directly inside Users, so no separate
     * roles table is added (ERD §9.1). The column is display-only on the
     * Profile page; role assignment belongs to FR-02 Users management.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('Staff')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
