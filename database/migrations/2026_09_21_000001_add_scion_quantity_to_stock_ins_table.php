<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approved ERD revision §2.15 / §9.9: add `scion_quantity` to `stock_ins`
     * so a stock-in transaction can record the total number of scions received
     * alongside the stock. Existing rows default to 0 (no data is guessed).
     */
    public function up(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->unsignedInteger('scion_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropColumn('scion_quantity');
        });
    }
};
