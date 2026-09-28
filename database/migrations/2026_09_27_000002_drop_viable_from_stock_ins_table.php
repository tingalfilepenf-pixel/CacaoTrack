<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7A — align `stock_ins` with the client-approved ERD §3.7.
     *
     * The approved baseline's Stock In records the seedlings actually received
     * (`quantity`) immediately; there is **no** five-day wait and **no**
     * separate seedling viability count. This migration therefore drops the
     * retired `stock_ins.viable` column.
     *
     * Reversible: `down()` re-adds `viable` (defaulted to 0 so the re-added
     * NOT NULL column is valid on a non-empty table) directly after `quantity`.
     */
    public function up(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropColumn('viable');
        });
    }

    public function down(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->unsignedInteger('viable')->default(0)->after('quantity');
        });
    }
};
