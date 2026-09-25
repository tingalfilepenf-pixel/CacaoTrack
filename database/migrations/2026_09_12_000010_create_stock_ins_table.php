<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Stock_In.
     * Records stock received from suppliers, associated with a batch and user.
     * The FKs use restrictOnDelete so stock history cannot be orphaned.
     */
    public function up(): void
    {
        Schema::create('stock_ins', function (Blueprint $table) {
            $table->bigIncrements('stock_in_id');
            $table->foreignId('supplier_id')
                ->constrained('suppliers', 'supplier_id')
                ->restrictOnDelete();
            $table->foreignId('batch_id')
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->date('date_received');
            $table->index('date_received');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ins');
    }
};
