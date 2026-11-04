<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROPOSED Phase 2 foundation (owner-directed, not implemented by any live
 * route/controller/view yet). One row per official record created by an
 * approved submission. `record_id` has no FK because the target table depends
 * on `record_type`; integrity is enforced in code and tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_submission_records', function (Blueprint $table) {
            $table->bigIncrements('submission_record_id');
            $table->foreignId('submission_id')
                ->constrained('operational_submissions', 'submission_id')
                ->restrictOnDelete();
            $table->string('record_type', 50);
            $table->unsignedBigInteger('record_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['submission_id', 'record_type', 'record_id'], 'osr_submission_record_unique');
            $table->unique(['record_type', 'record_id'], 'osr_record_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_submission_records');
    }
};
