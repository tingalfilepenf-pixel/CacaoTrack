<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approved ERD revision §9.11 (Batch Monitoring): the `Batch_Monitoring`
     * entity stores one daily plant-status observation per batch per day
     * (plant condition, survivors, newly observed mortality, remark) and gives
     * the feature its complete monitoring history. It references the existing
     * `Batch` row — no batch master data is duplicated.
     */
    public function up(): void
    {
        Schema::create('batch_monitorings', function (Blueprint $table): void {
            $table->bigIncrements('monitoring_id');
            $table->foreignId('batch_id')->constrained('batches', 'batch_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->restrictOnDelete();
            $table->date('monitoring_date');
            $table->unsignedInteger('monitoring_day');
            $table->string('status');
            $table->unsignedInteger('survivors');
            $table->unsignedInteger('mortality_quantity')->default(0);
            $table->string('remark')->nullable();

            $table->unique(['batch_id', 'monitoring_date']);
            $table->index('monitoring_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_monitorings');
    }
};
