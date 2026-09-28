<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7A — align `batches` with the client-approved ERD §3.8.
     *
     * The approved baseline records `Total_Grafted`, `Regraft` and
     * `Available_Count` on a Batch. It has **no** `viable_count` (the earlier
     * five-day recounted-seedling quantity was removed from Stock In and Batch),
     * so this migration drops `batches.viable_count` and adds:
     *  - `regraft` (unsigned int, default 0) — the batch's regraft counter, and
     *  - `parent_batch_id` (nullable FK -> batches.batch_id, restrictOnDelete,
     *    indexed) — links a regraft sub-batch to its parent Batch (ERD §3.8).
     *
     * Reversible: `down()` restores `viable_count` (defaulted to 0 so the
     * re-added NOT NULL column is valid on a non-empty table) and removes the
     * two new columns plus their foreign key.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('viable_count');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedInteger('regraft')->default(0)->after('total_grafted');
            $table->foreignId('parent_batch_id')
                ->nullable()
                ->after('scion_variety_id')
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['parent_batch_id']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['regraft', 'parent_batch_id']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedInteger('viable_count')->default(0)->after('total_grafted');
        });
    }
};
