<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the `Replanting_Record` entity to `Regraft_Record`.
     *
     * Regraft and Replanting are the same recorded procedure; the company's
     * term (and the UI) is **Regraft**. This renames the table and its columns
     * to match, and realigns the stored `activity_logs.activity_type` value.
     *
     * The historical create/drop migrations for the short-lived P5
     * `regraft_records` exception (`2026_09_25_000009` / `2026_09_26_000002`)
     * are left intact as history — by the time this migration runs the table
     * does not exist, so the rename is uncontested.
     */
    public function up(): void
    {
        Schema::rename('replanting_records', 'regraft_records');

        Schema::table('regraft_records', function (Blueprint $table) {
            $table->renameColumn('replanting_id', 'regraft_id');
        });

        Schema::table('regraft_records', function (Blueprint $table) {
            $table->renameColumn('replanting_date', 'regraft_date');
        });

        DB::table('activity_logs')
            ->where('activity_type', 'replanting_recorded')
            ->update(['activity_type' => 'regraft_recorded']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('activity_logs')
            ->where('activity_type', 'regraft_recorded')
            ->update(['activity_type' => 'replanting_recorded']);

        Schema::table('regraft_records', function (Blueprint $table) {
            $table->renameColumn('regraft_id', 'replanting_id');
        });

        Schema::table('regraft_records', function (Blueprint $table) {
            $table->renameColumn('regraft_date', 'replanting_date');
        });

        Schema::rename('regraft_records', 'replanting_records');
    }
};
