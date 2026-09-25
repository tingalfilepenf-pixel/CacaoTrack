<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Mortality_Adjustments.
     * Adjustment_Quantity is a signed delta applied to the parent mortality
     * record (effective mortality = Quantity + sum of adjustments), so the
     * column is a plain signed integer and no adjustment may be zero.
     */
    public function up(): void
    {
        Schema::create('mortality_adjustments', function (Blueprint $table) {
            $table->bigIncrements('adjustment_id');
            $table->foreignId('mortality_id')->constrained('mortality_records', 'mortality_id')->restrictOnDelete();
            $table->foreignId('approved_by_id')->constrained('users', 'id')->restrictOnDelete();
            $table->integer('adjustment_quantity');
            $table->string('reason');
            $table->dateTime('adjusted_at');

            $table->index('adjusted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mortality_adjustments');
    }
};
