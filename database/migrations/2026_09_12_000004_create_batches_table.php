<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->bigIncrements('batch_id');
            $table->foreignId('variety_id')->constrained('varieties', 'variety_id')->restrictOnDelete();
            $table->foreignId('scion_id')->constrained('scions', 'scion_id')->restrictOnDelete();
            $table->foreignId('pot_id')->constrained('pot_types', 'pot_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->restrictOnDelete();
            $table->string('batch_code')->unique();
            $table->unsignedInteger('total_grafted');
            $table->unsignedInteger('viable_count');
            $table->unsignedInteger('available_count');
            $table->date('grafted_date');
            $table->dateTime('created_at');
            $table->string('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
