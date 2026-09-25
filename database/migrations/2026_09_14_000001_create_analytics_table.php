<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Analytics.
     * Stores generated analytics/report snapshots and associates each generated
     * report with the user who generated it. Reports are generated from the
     * normalized operational data (batches, mortality, replanting, inventory);
     * this table records who generated which analysis, over which period, and
     * the resulting summary (RULE 12 traceability).
     * The user FK uses restrictOnDelete so report history cannot be orphaned.
     */
    public function up(): void
    {
        Schema::create('analytics', function (Blueprint $table) {
            $table->bigIncrements('analytics_id');
            $table->foreignId('user_id')
                ->constrained('users', 'id')
                ->restrictOnDelete();
            $table->string('analysis_type');
            $table->string('analysis_period');
            $table->text('result_summary');
            $table->dateTime('generated_at');
            $table->index('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics');
    }
};
