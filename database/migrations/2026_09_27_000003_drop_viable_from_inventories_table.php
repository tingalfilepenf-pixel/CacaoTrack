<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7A — align `inventories` with the client-approved ERD §3.12.
     *
     * A monthly Inventory snapshot keeps the counts the company actually keeps:
     * `Total_Grafted`, `Regraft`, `Mortality` and `Status`. The approved
     * baseline has **no** `viable` count, so this migration drops the retired
     * `inventories.viable` column.
     *
     * Reversible: `down()` re-adds `viable` (defaulted to 0 so the re-added
     * NOT NULL column is valid on a non-empty table) directly after
     * `total_grafted`.
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('viable');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->unsignedInteger('viable')->default(0)->after('total_grafted');
        });
    }
};
