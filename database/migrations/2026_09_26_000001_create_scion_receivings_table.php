<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New entity: Scion_Receiving.
     *
     * Records the receipt of scion material for a scion variety. `supplier_id`
     * is nullable because scions are not always tied to a formal Supplier
     * record. This is a standalone, additive record: it holds no stock balance
     * on `scion_varieties` and does not touch Regraft/Replanting.
     *
     * A freshness/viability rule is planned but deliberately NOT implemented
     * yet (no usable_until column, no warning, no blocking) pending client
     * confirmation of the allowed viability window and whether it should warn
     * or block.
     */
    public function up(): void
    {
        Schema::create('scion_receivings', function (Blueprint $table) {
            $table->bigIncrements('scion_receiving_id');
            $table->foreignId('scion_variety_id')
                ->constrained('scion_varieties', 'scion_variety_id')
                ->restrictOnDelete();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers', 'supplier_id')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->date('date_received');
            $table->string('origin')->nullable();
            $table->text('notes')->nullable();

            $table->index('date_received');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scion_receivings');
    }
};
