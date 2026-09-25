<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revised Inventory: monthly per-batch snapshots.
     * Adds inventory_date/total_grafted/viable/regraft/mortality, seeds each
     * existing row from real Batch/Mortality/Replanting data, moves the old
     * per-batch balance into batches.available_count, then drops the old
     * current_quantity column and its batch_id UNIQUE constraint.
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->date('inventory_date')->nullable()->after('batch_id');
            $table->unsignedInteger('total_grafted')->nullable()->after('inventory_date');
            $table->unsignedInteger('viable')->nullable()->after('total_grafted');
            $table->unsignedInteger('regraft')->nullable()->after('viable');
            $table->unsignedInteger('mortality')->nullable()->after('regraft');
        });

        $batches = DB::table('batches')->get()->keyBy('batch_id');
        $replanted = DB::table('replanting_records')
            ->select('batch_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('batch_id')
            ->pluck('total', 'batch_id');
        $mortality = DB::table('mortality_records')
            ->select('batch_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('batch_id')
            ->pluck('total', 'batch_id');

        foreach (DB::table('inventories')->get() as $inventory) {
            $batch = $batches[$inventory->batch_id] ?? null;

            DB::table('batches')->where('batch_id', $inventory->batch_id)->update([
                'available_count' => $inventory->current_quantity,
            ]);

            DB::table('inventories')->where('inventory_id', $inventory->inventory_id)->update([
                'inventory_date' => $inventory->last_update ? date('Y-m-d', strtotime($inventory->last_update)) : null,
                'total_grafted' => $batch->total_grafted ?? 0,
                'viable' => $batch->viable_count ?? 0,
                'regraft' => $replanted[$inventory->batch_id] ?? 0,
                'mortality' => $mortality[$inventory->batch_id] ?? 0,
            ]);
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->date('inventory_date')->nullable(false)->change();
            $table->unsignedInteger('total_grafted')->nullable(false)->change();
            $table->unsignedInteger('viable')->nullable(false)->change();
            $table->unsignedInteger('regraft')->nullable(false)->change();
            $table->unsignedInteger('mortality')->nullable(false)->change();
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropUnique(['batch_id']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->index('batch_id');
            $table->foreign('batch_id')->references('batch_id')->on('batches')->restrictOnDelete();
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('current_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->unsignedInteger('current_quantity')->default(0)->after('batch_id');
        });

        foreach (DB::table('inventories')->get() as $inventory) {
            $available = DB::table('batches')->where('batch_id', $inventory->batch_id)->value('available_count');

            DB::table('inventories')->where('inventory_id', $inventory->inventory_id)->update([
                'current_quantity' => $available ?? 0,
            ]);
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->unique('batch_id');
            $table->foreign('batch_id')->references('batch_id')->on('batches')->restrictOnDelete();
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn(['inventory_date', 'total_grafted', 'viable', 'regraft', 'mortality']);
        });
    }
};
