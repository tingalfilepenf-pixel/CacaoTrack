<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Activity_Log.
     * Records operational activities performed by users, optionally associated
     * with a batch (FR-03 / FR-17 / PRD §24).
     *
     * The source ERD lists Batch_ID as a foreign key but does not mark it
     * NOT NULL; activities such as task completion and report generation are
     * not batch-specific, so batch_id is nullable. FKs use restrictOnDelete so
     * the audit history cannot be orphaned.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->bigIncrements('activity_id');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
            $table->string('activity_type');
            $table->string('description');
            $table->datetime('activity_date');
            $table->index('activity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
