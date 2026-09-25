<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revised ERD entity: Stock_Out_Detail. One row per batch/scion-variety
     * quantity inside a Stock Out header. `unit` is a fixed-list value
     * (seedlings, pieces, bags); unit_price/amount are optional.
     */
    public function up(): void
    {
        Schema::create('stock_out_details', function (Blueprint $table) {
            $table->bigIncrements('stock_out_detail_id');
            $table->foreignId('stock_out_id')
                ->constrained('stock_outs', 'stock_out_id')
                ->restrictOnDelete();
            $table->foreignId('batch_id')
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
            $table->foreignId('scion_variety_id')
                ->constrained('scion_varieties', 'scion_variety_id')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('unit');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_out_details');
    }
};
