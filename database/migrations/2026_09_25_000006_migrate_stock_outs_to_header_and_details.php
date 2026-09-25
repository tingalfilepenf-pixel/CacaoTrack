<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revised Stock_Out is a header only. Each existing single-batch
     * Stock Out becomes one Stock_Out_Detail line (batch + its scion
     * variety). The old batch_id/quantity columns are then dropped.
     */
    public function up(): void
    {
        $scionVarietyByBatch = DB::table('batches')->pluck('scion_variety_id', 'batch_id');

        foreach (DB::table('stock_outs')->orderBy('stock_out_id')->get() as $stockOut) {
            DB::table('stock_out_details')->insert([
                'stock_out_id' => $stockOut->stock_out_id,
                'batch_id' => $stockOut->batch_id,
                'scion_variety_id' => $scionVarietyByBatch[$stockOut->batch_id] ?? null,
                'quantity' => $stockOut->quantity,
                'unit' => 'seedlings',
                'unit_price' => null,
                'amount' => $stockOut->total_amount,
            ]);
        }

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('client_id');
            $table->unsignedInteger('quantity')->default(0)->after('user_id');
        });

        foreach (DB::table('stock_out_details')->get()->groupBy('stock_out_id') as $stockOutId => $details) {
            DB::table('stock_outs')->where('stock_out_id', $stockOutId)->update([
                'batch_id' => $details->first()->batch_id,
                'quantity' => $details->sum('quantity'),
            ]);
        }

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->foreign('batch_id')->references('batch_id')->on('batches')->restrictOnDelete();
        });
    }
};
