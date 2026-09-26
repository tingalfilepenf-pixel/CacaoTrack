<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retract the P5 Part B `Regraft_Record` schema exception.
     *
     * `regraft_records` duplicated `replanting_records`: in the company's
     * process Regraft and Replanting are the same recorded procedure, so the
     * separate dated-event table was never populated (0 rows) and is dropped.
     * The Stock Ledger now reads `replanting_records` directly. No data
     * migration is required because the table held no rows.
     *
     * The original create migration `2026_09_25_000009` is left intact as
     * history (reverse it, do not edit it).
     */
    public function up(): void
    {
        Schema::dropIfExists('regraft_records');
    }

    /**
     * Reverse the migrations.
     *
     * Recreates the (empty) table so the migration stays reversible.
     */
    public function down(): void
    {
        if (Schema::hasTable('regraft_records')) {
            return;
        }

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
};
