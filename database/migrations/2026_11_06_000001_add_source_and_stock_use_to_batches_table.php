<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4b: record where an official Batch came from and, for an own-nursery
 * batch created through the approval workflow, how much seedling and scion
 * stock it consumed.
 *
 * Additive and reversible. Existing batches are nursery-produced (owner
 * decision), so every existing row is backfilled to `source = 'own'` without
 * fabricating any used quantities: `seedlings_used` and `scion_used` stay null,
 * which means historical batches are not deducted from the derived balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('source', 20)->default('own')->after('batch_id');
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('source')
                ->constrained('suppliers', 'supplier_id')
                ->restrictOnDelete();
            $table->unsignedInteger('seedlings_used')->nullable()->after('total_grafted');
            $table->unsignedInteger('scion_used')->nullable()->after('seedlings_used');
            $table->index('source');
        });

        // Explicit provenance backfill for every existing batch (owner decision
        // 5). Used quantities deliberately stay null: no opening adjustment and
        // no historical deduction.
        DB::table('batches')->update(['source' => 'own']);
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'supplier_id', 'seedlings_used', 'scion_used']);
        });
    }
};
