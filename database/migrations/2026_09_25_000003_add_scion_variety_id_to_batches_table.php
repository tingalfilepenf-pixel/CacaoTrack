<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revised Batch.scion_variety_id (nullable) replaces the removed
     * variety_id / scion_id / pot_id columns. Backfilled from variety_id
     * per locked decision 2.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('scion_variety_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('scion_varieties', 'scion_variety_id')
                ->restrictOnDelete();
        });

        DB::table('batches')->update(['scion_variety_id' => DB::raw('variety_id')]);
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['scion_variety_id']);
            $table->dropColumn('scion_variety_id');
        });
    }
};
