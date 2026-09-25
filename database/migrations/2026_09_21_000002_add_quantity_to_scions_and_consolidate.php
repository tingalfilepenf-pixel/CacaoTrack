<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approved ERD revision §2.8/§9.10 — scion stock pool.
     *
     * Adds `scions.quantity` (the current available scion balance) and consolidates the
     * existing named scions into a single generic `Scion` row: every batch is repointed to
     * it, its starting balance becomes the real received-less-regrafted figure, and the
     * superseded rows are removed. No new table (RULE 4) and no `migrate:fresh` — existing
     * data is migrated in place.
     */
    public function up(): void
    {
        Schema::table('scions', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->after('scion_name');
        });

        $canonicalId = DB::table('scions')->min('scion_id');

        // Fresh database (tests): nothing to consolidate.
        if ($canonicalId === null) {
            return;
        }

        $received = (int) DB::table('stock_ins')->sum('scion_quantity');
        $regrafted = (int) DB::table('replanting_records')->sum('quantity');

        // Repoint every batch to the single pool before removing the other scions
        // (batches.scion_id is restrictOnDelete, so this cannot orphan a batch).
        DB::table('batches')->update(['scion_id' => $canonicalId]);

        DB::table('scions')->where('scion_id', $canonicalId)->update([
            'scion_name' => 'Scion',
            'description' => null,
            'quantity' => max(0, $received - $regrafted),
            'archived_at' => null,
        ]);

        DB::table('scions')->where('scion_id', '!=', $canonicalId)->delete();
    }

    /**
     * Reverse the migration. The `quantity` column is dropped; the consolidation of the
     * superseded scion rows is not reversible (the pre-migration data lives in the backup
     * taken before this migration ran).
     */
    public function down(): void
    {
        Schema::table('scions', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
