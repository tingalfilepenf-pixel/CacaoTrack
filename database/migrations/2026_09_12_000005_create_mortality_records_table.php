<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Mortality_Record.
     * Records cacao mortality quantities, causes, batches, and recording users.
     */
    public function up(): void
    {
        Schema::create('mortality_records', function (Blueprint $table) {
            $table->bigIncrements('mortality_id');
            $table->foreignId('batch_id')->constrained('batches', 'batch_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('cause');
            $table->dateTime('recorded_at');

            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mortality_records');
    }
};
