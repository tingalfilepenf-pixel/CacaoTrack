<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merge Scion_Receiving into Stock_In (client-confirmed schema decision).
     *
     * Client confirmed seedling Stock In and Scion Receiving are the same
     * real-world receiving event, so Scion_Receiving (added 2026_09_26) is
     * merged into stock_ins rather than kept as a separate table — avoiding the
     * same duplication problem this project already resolved once for
     * Regraft/Replanting. scion_variety_id and unit_price are nullable because
     * they only apply when a stock-in includes scion; a pure-seedling delivery
     * leaves them null, unchanged from prior behavior. For scion-inclusive rows,
     * viable is set equal to quantity at entry (no 5-day recount), since scion is
     * confirmed perishable and used immediately, unlike seedlings.
     *
     * Two supporting changes are unavoidable to migrate the existing
     * `scion_receivings` rows without discarding data (the user-facing rule for
     * this task):
     *  - `supplier_id` becomes nullable. `scion_receivings.supplier_id` was
     *    nullable (scion is not always tied to a formal Supplier record) and
     *    real rows exist with a NULL supplier; a merged row cannot satisfy the
     *    previous NOT NULL constraint. Seedling rows are unaffected — the form
     *    still requires a supplier for them.
     *  - `notes` is added (nullable). `scion_receivings` carried a `notes`
     *    column and the merge must preserve it; stock_ins had no equivalent.
     *
     * There is deliberately NO discriminator/"type" column: the presence of
     * `scion_variety_id` is itself the signal that a row includes scion.
     */
    public function up(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->foreignId('scion_variety_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('scion_varieties', 'scion_variety_id')
                ->restrictOnDelete();
            $table->decimal('unit_price', 12, 2)->nullable()->after('viable');
            $table->text('notes')->nullable()->after('origin');
        });

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
        });
    }

    /**
     * Reverse the merge. The scion-only columns are dropped and `supplier_id`
     * returns to NOT NULL (any scion rows that still reference a NULL supplier
     * are moved back to `scion_receivings` by the companion migration
     * 2026_09_26_000004 before this runs).
     */
    public function down(): void
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scion_variety_id');
            $table->dropColumn(['unit_price', 'notes']);
        });

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
        });
    }
};
