<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data-migrate `scion_receivings` into `stock_ins`, then drop the table.
     *
     * The client confirmed Scion_Receiving is the same real-world receiving
     * event as Stock In, so the two are merged (see 2026_09_26_000003 for the
     * full reasoning). This step preserves the real seeded receiving rows
     * instead of silently discarding them: each `scion_receivings` row becomes
     * a `stock_ins` row with `scion_variety_id` set, `quantity`/`unit_price`/
     * `date_received` mapped directly, `origin`/`notes` preserved, and `viable`
     * set equal to `quantity` (scion has no five-day recount — it is perishable
     * and used immediately).
     *
     * The standalone log had no "recorded by" user, so migrated rows are
     * attributed to the earliest user account; `stock_ins.user_id` is a
     * required FK and every other receiving/user attribute is preserved.
     *
     * The down() recreates `scion_receivings` and moves the scion rows back out
     * of `stock_ins`, so the merge stays reversible (up -> down -> up).
     */
    public function up(): void
    {
        if (! Schema::hasTable('scion_receivings')) {
            return;
        }

        $receivings = DB::table('scion_receivings')->orderBy('scion_receiving_id')->get();

        if ($receivings->isNotEmpty()) {
            // The Users table uses Laravel's default `id` primary key in this
            // project (it is not `user_id`), so resolve it defensively.
            $userKey = Schema::hasColumn('users', 'user_id') ? 'user_id' : 'id';
            $recordedBy = DB::table('users')->orderBy($userKey)->value($userKey);

            if ($recordedBy === null) {
                throw new RuntimeException(
                    'Cannot merge scion_receivings into stock_ins: no users exist to attribute the migrated receiving rows to.',
                );
            }

            foreach ($receivings as $receiving) {
                DB::table('stock_ins')->insert([
                    'supplier_id' => $receiving->supplier_id,
                    'scion_variety_id' => $receiving->scion_variety_id,
                    'user_id' => $recordedBy,
                    'quantity' => $receiving->quantity,
                    'viable' => $receiving->quantity,
                    'unit_price' => $receiving->unit_price,
                    'origin' => $receiving->origin ?? '',
                    'notes' => $receiving->notes,
                    'date_received' => $receiving->date_received,
                ]);
            }
        }

        Schema::dropIfExists('scion_receivings');
    }

    /**
     * Restore the standalone `scion_receivings` table and move every merged
     * scion row back out of `stock_ins` (mirrors the original 2026_09_26_000001
     * schema). Runs before 2026_09_26_000003's down(), which then restores the
     * NOT NULL `supplier_id` constraint on the remaining seedling-only rows.
     */
    public function down(): void
    {
        Schema::create('scion_receivings', function (Blueprint $table) {
            $table->bigIncrements('scion_receiving_id');
            $table->foreignId('scion_variety_id')
                ->constrained('scion_varieties', 'scion_variety_id')
                ->restrictOnDelete();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers', 'supplier_id')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->date('date_received');
            $table->string('origin')->nullable();
            $table->text('notes')->nullable();

            $table->index('date_received');
        });

        $scionRows = DB::table('stock_ins')
            ->whereNotNull('scion_variety_id')
            ->orderBy('stock_in_id')
            ->get();

        foreach ($scionRows as $row) {
            DB::table('scion_receivings')->insert([
                'scion_variety_id' => $row->scion_variety_id,
                'supplier_id' => $row->supplier_id,
                'quantity' => $row->quantity,
                'unit_price' => $row->unit_price,
                'date_received' => $row->date_received,
                'origin' => $row->origin ?: null,
                'notes' => $row->notes,
            ]);
        }

        DB::table('stock_ins')->whereNotNull('scion_variety_id')->delete();
    }
};
