<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Approved ERD revision §9.8: master-data archive/restore.
     * A nullable `archived_at` is added to each reference/master entity so the
     * Management pages can archive a record instead of hard-deleting it
     * (mirroring the Daily Checklist pattern, ERD §2.4/§9.6). A NULL value
     * means active; a set value hides the record from the active listing and
     * from new-record selectors while preserving all history. No new table.
     */
    private const TABLES = [
        'suppliers' => 'address',
        'clients' => 'address',
        'varieties' => 'description',
        'scions' => 'description',
        'pot_types' => 'description',
        'batches' => 'status',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            Schema::table($table, function (Blueprint $blueprint) use ($after): void {
                $blueprint->timestamp('archived_at')->nullable()->after($after);
                $blueprint->index('archived_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['archived_at']);
                $blueprint->dropColumn('archived_at');
            });
        }
    }
};
