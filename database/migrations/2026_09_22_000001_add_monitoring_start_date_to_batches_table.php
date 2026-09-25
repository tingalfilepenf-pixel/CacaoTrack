<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approved ERD revision §9.11 (Batch Monitoring): a nullable
     * `monitoring_start_date` is added to `batches` — the date the batch's
     * 21-day monitoring lifecycle begins. No new table here.
     *
     * Existing batches are backfilled from their real `grafted_date`
     * (never fabricated) so every batch has a lifecycle day immediately;
     * NULL still means "monitoring not started" for future batches until a
     * start date is set.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->date('monitoring_start_date')->nullable()->after('grafted_date');
        });

        DB::table('batches')
            ->whereNull('monitoring_start_date')
            ->update(['monitoring_start_date' => DB::raw('grafted_date')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->dropColumn('monitoring_start_date');
        });
    }
};
