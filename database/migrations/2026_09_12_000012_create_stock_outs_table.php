<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Stock_Out.
     * Records stock released to clients, associated with a batch and user.
     * The FKs use restrictOnDelete so stock history cannot be orphaned.
     * Total_Amount is nullable because the PRD records it only "where applicable".
     */
    public function up(): void
    {
        Schema::create('stock_outs', function (Blueprint $table) {
            $table->bigIncrements('stock_out_id');
            $table->foreignId('client_id')
                ->constrained('clients', 'client_id')
                ->restrictOnDelete();
            $table->foreignId('batch_id')
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->date('release_date');
            $table->index('release_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_outs');
    }
};
