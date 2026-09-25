<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Locked decision 2: the old `varieties` rows become `scion_varieties`,
     * preserving IDs so Batches can be repointed variety_id -> scion_variety_id.
     */
    public function up(): void
    {
        if (! Schema::hasTable('varieties')) {
            return;
        }

        foreach (DB::table('varieties')->orderBy('variety_id')->get() as $variety) {
            DB::table('scion_varieties')->insert([
                'scion_variety_id' => $variety->variety_id,
                'scion_variety_name' => $variety->variety_name,
                'description' => $variety->description,
                'archived_at' => $variety->archived_at,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('varieties')) {
            return;
        }

        $ids = DB::table('varieties')->pluck('variety_id');

        DB::table('scion_varieties')->whereIn('scion_variety_id', $ids)->delete();
    }
};
