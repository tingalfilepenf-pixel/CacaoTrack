<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7A — align `replanting_records` with the client-approved ERD §3.11.
     *
     * A Replanting (Regraft) record may identify the parent Batch for the regraft
     * sub-batch it creates, so this migration adds `parent_batch_id` (nullable
     * FK -> batches.batch_id, restrictOnDelete, indexed).
     *
     * Reversible: `down()` drops the foreign key and the column.
     */
    public function up(): void
    {
        Schema::table('replanting_records', function (Blueprint $table) {
            $table->foreignId('parent_batch_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('replanting_records', function (Blueprint $table) {
            $table->dropForeign(['parent_batch_id']);
        });

        Schema::table('replanting_records', function (Blueprint $table) {
            $table->dropColumn('parent_batch_id');
        });
    }
};
