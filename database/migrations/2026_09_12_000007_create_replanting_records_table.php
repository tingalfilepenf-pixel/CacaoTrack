<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Replanting_Record.
     * Records replanting activity for a batch.
     */
    public function up(): void
    {
        Schema::create('replanting_records', function (Blueprint $table) {
            $table->bigIncrements('replanting_id');
            $table->foreignId('batch_id')->constrained('batches', 'batch_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->date('replanting_date');
            $table->string('remark')->nullable();

            $table->index('replanting_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replanting_records');
    }
};
