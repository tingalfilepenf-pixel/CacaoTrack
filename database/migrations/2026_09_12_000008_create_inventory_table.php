<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Inventory.
     * Stores the current inventory/balance associated with a batch.
     * The source ERD marks Batch_ID as FK + UNIQUE: one inventory row per batch.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->bigIncrements('inventory_id');
            $table->foreignId('batch_id')
                ->unique()
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
            $table->unsignedInteger('current_quantity');
            $table->string('status');
            $table->dateTime('last_update');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
