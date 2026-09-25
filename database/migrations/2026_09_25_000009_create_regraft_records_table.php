<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * P5 approved schema exception: `Regraft_Record` records Regraft as a
     * discrete, DATED event per batch (and therefore per scion variety), which
     * the client's real stock ledger needs. `inventories.regraft` (the monthly
     * aggregate) is deliberately left untouched — this table is an additional,
     * more granular record alongside it, not a replacement.
     */
    public function up(): void
    {
        Schema::create('regraft_records', function (Blueprint $table) {
            $table->bigIncrements('regraft_id');
            $table->foreignId('batch_id')->constrained('batches', 'batch_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->date('regraft_date');
            $table->string('remark')->nullable();

            $table->index('regraft_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regraft_records');
    }
};
