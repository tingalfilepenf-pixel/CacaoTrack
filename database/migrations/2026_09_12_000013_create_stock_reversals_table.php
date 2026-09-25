<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Stock_Reversal.
     * Records corrective stock transaction reversals, associated with either a
     * Stock_In or a Stock_Out and the user who performed the reversal.
     *
     * ERD revision: the source ERD labels the transaction reference as
     * "Stock_In_ID OR Stock_Out_ID". That is implemented here as two nullable
     * FKs (stock_in_id / stock_out_id) with exactly one populated per row,
     * preserving referential integrity. The FKs use restrictOnDelete so stock
     * history cannot be orphaned.
     */
    public function up(): void
    {
        Schema::create('stock_reversals', function (Blueprint $table) {
            $table->bigIncrements('reversal_id');
            $table->foreignId('stock_in_id')
                ->nullable()
                ->constrained('stock_ins', 'stock_in_id')
                ->restrictOnDelete();
            $table->foreignId('stock_out_id')
                ->nullable()
                ->constrained('stock_outs', 'stock_out_id')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->datetime('reversed_at');
            $table->index('reversed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reversals');
    }
};
