<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the ERD Users.Settings attribute (ERD §2.1, approved revision §9.5).
     *
     * Stores per-user preferences as JSON. Currently used for notification
     * preferences (master toggle + wanted activity types) consumed by the
     * Notifications entity. No separate settings table is introduced, keeping
     * the change minimal and consistent with the earlier Users.Role alignment.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
