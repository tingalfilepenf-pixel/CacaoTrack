<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Activity_Task (master task definition).
     * `archived_at` is an explicit, approved ERD revision (see ERD §2.4/§9.6):
     * the checklist uses archive/restore instead of hard deletion so a task's
     * Task_Complete history is never destroyed.
     */
    public function up(): void
    {
        Schema::table('activity_tasks', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('priority');
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_tasks', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
