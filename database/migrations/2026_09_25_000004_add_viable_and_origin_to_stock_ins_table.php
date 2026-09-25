<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revised Stock_In: add `viable` (five-day recounted usable quantity) and
     * `origin`, and drop the retired `batch_id` + `scion_quantity` columns
     * (Stock In occurs before a Batch is established). Existing rows backfill
     * viable from quantity; origin is a required field with legacy rows
     * defaulted to an empty string.
     */
    public function up(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->unsignedInteger('viable')->default(0)->after('quantity');
            $table->string('origin')->default('')->after('viable');
        });

        DB::table('stock_ins')->update(['viable' => DB::raw('quantity')]);

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'scion_quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('supplier_id');
            $table->unsignedInteger('scion_quantity')->default(0)->after('quantity');
        });

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->foreign('batch_id')->references('batch_id')->on('batches')->restrictOnDelete();
        });

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropColumn(['viable', 'origin']);
        });
    }
};
