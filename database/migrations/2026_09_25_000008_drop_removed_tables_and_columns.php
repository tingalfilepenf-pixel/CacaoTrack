<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes everything the revised ERD retires: the Batch_Monitoring /
     * Mortality_Adjustments / Stock_Reversal tables, the Variety / Scions /
     * Pot_Type masters, and the Batch columns variety_id, scion_id, pot_id
     * and monitoring_start_date.
     *
     * Rollback restores the schema and repopulates `varieties` from
     * `scion_varieties` plus the Batch variety mapping. The retired tables
     * (and the scions/pot_types rows) are accepted data loss per the plan,
     * so those tables are recreated empty and the restored Batch columns are
     * nullable where the originals were NOT NULL.
     */
    public function up(): void
    {
        Schema::dropIfExists('batch_monitorings');
        Schema::dropIfExists('mortality_adjustments');
        Schema::dropIfExists('stock_reversals');

        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['variety_id']);
            $table->dropForeign(['scion_id']);
            $table->dropForeign(['pot_id']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['variety_id', 'scion_id', 'pot_id', 'monitoring_start_date']);
        });

        Schema::dropIfExists('varieties');
        Schema::dropIfExists('scions');
        Schema::dropIfExists('pot_types');
    }

    public function down(): void
    {
        $this->createVarieties();
        $this->copyScionVarietiesIntoVarieties();
        $this->createScions();
        $this->createPotTypes();
        $this->createBatchMonitorings();
        $this->createMortalityAdjustments();
        $this->createStockReversals();

        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedBigInteger('variety_id')->nullable()->after('batch_id');
            $table->unsignedBigInteger('scion_id')->nullable()->after('variety_id');
            $table->unsignedBigInteger('pot_id')->nullable()->after('scion_id');
            $table->date('monitoring_start_date')->nullable()->after('grafted_date');
        });

        DB::table('batches')->update([
            'variety_id' => DB::raw('scion_variety_id'),
            'monitoring_start_date' => DB::raw('grafted_date'),
        ]);

        Schema::table('batches', function (Blueprint $table) {
            $table->foreign('variety_id')->references('variety_id')->on('varieties')->restrictOnDelete();
            $table->foreign('scion_id')->references('scion_id')->on('scions')->restrictOnDelete();
            $table->foreign('pot_id')->references('pot_id')->on('pot_types')->restrictOnDelete();
        });
    }

    private function createVarieties(): void
    {
        Schema::create('varieties', function (Blueprint $table) {
            $table->bigIncrements('variety_id');
            $table->string('variety_name');
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    private function copyScionVarietiesIntoVarieties(): void
    {
        foreach (DB::table('scion_varieties')->orderBy('scion_variety_id')->get() as $scionVariety) {
            DB::table('varieties')->insert([
                'variety_id' => $scionVariety->scion_variety_id,
                'variety_name' => $scionVariety->scion_variety_name,
                'description' => $scionVariety->description,
                'archived_at' => $scionVariety->archived_at,
            ]);
        }
    }

    private function createScions(): void
    {
        Schema::create('scions', function (Blueprint $table) {
            $table->bigIncrements('scion_id');
            $table->string('scion_name');
            $table->unsignedInteger('quantity')->default(0);
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    private function createPotTypes(): void
    {
        Schema::create('pot_types', function (Blueprint $table) {
            $table->bigIncrements('pot_id');
            $table->string('pot_name');
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    private function createBatchMonitorings(): void
    {
        Schema::create('batch_monitorings', function (Blueprint $table) {
            $table->bigIncrements('monitoring_id');
            $table->foreignId('batch_id')->constrained('batches', 'batch_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->restrictOnDelete();
            $table->date('monitoring_date');
            $table->unsignedInteger('monitoring_day');
            $table->string('status');
            $table->unsignedInteger('survivors');
            $table->unsignedInteger('mortality_quantity')->default(0);
            $table->string('remark')->nullable();
            $table->unique(['batch_id', 'monitoring_date']);
            $table->index('monitoring_date');
        });
    }

    private function createMortalityAdjustments(): void
    {
        Schema::create('mortality_adjustments', function (Blueprint $table) {
            $table->bigIncrements('adjustment_id');
            $table->foreignId('mortality_id')->constrained('mortality_records', 'mortality_id')->restrictOnDelete();
            $table->foreignId('approved_by_id')->constrained('users', 'id')->restrictOnDelete();
            $table->integer('adjustment_quantity');
            $table->string('reason');
            $table->dateTime('adjusted_at');
            $table->index('adjusted_at');
        });
    }

    private function createStockReversals(): void
    {
        Schema::create('stock_reversals', function (Blueprint $table) {
            $table->bigIncrements('reversal_id');
            $table->foreignId('stock_in_id')->nullable()->constrained('stock_ins', 'stock_in_id')->restrictOnDelete();
            $table->foreignId('stock_out_id')->nullable()->constrained('stock_outs', 'stock_out_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->dateTime('reversed_at');
            $table->index('reversed_at');
        });
    }
};
