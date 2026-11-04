<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROPOSED Phase 2 foundation (owner-directed, not implemented by any live
 * route/controller/view yet). Additive only: no existing table is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_submissions', function (Blueprint $table) {
            $table->bigIncrements('submission_id');
            $table->string('submission_type', 50);
            $table->string('status', 20)->default('pending');
            $table->json('payload');
            $table->foreignId('submitted_by_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at');
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->boolean('decision_automatic')->default(false);
            $table->foreignId('resubmission_of_id')
                ->nullable()
                ->constrained('operational_submissions', 'submission_id')
                ->restrictOnDelete();
            $table->foreignId('subject_batch_id')
                ->nullable()
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['submission_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_submissions');
    }
};
