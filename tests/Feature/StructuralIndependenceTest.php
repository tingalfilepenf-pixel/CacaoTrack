<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Part D: structural regression guards for the revised 15-entity model.
 *
 * These tests fail if anyone reintroduces: a link between Mortality_Record and
 * Replanting_Record, a scion stock pool/balance, a separate regraft table, or
 * any of the entities/columns the ERD explicitly removed.
 */
class StructuralIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mortality_and_replanting_records_remain_structurally_independent(): void
    {
        $mortalityColumns = Schema::getColumnListing('mortality_records');
        $replantingColumns = Schema::getColumnListing('replanting_records');

        // No cross-referencing columns in either direction.
        foreach ($mortalityColumns as $column) {
            $this->assertStringNotContainsString('replanting', $column);
            $this->assertStringNotContainsString('regraft', $column);
        }

        foreach ($replantingColumns as $column) {
            $this->assertStringNotContainsString('mortality', $column);
        }

        // No foreign key between the two tables in either direction.
        $mortalityFks = collect(Schema::getForeignKeys('mortality_records'))->pluck('foreign_table');
        $replantingFks = collect(Schema::getForeignKeys('replanting_records'))->pluck('foreign_table');

        $this->assertFalse($mortalityFks->contains('replanting_records'));
        $this->assertFalse($replantingFks->contains('mortality_records'));
    }

    public function test_a_separate_regraft_table_does_not_exist(): void
    {
        $this->assertFalse(Schema::hasTable('regraft_records'));
    }

    public function test_the_removed_entities_do_not_exist(): void
    {
        foreach (['batch_monitorings', 'varieties', 'scions', 'pot_types', 'mortality_adjustments', 'stock_reversals'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Removed table [{$table}] must not exist.");
        }
    }

    public function test_the_removed_columns_do_not_exist(): void
    {
        $this->assertFalse(Schema::hasColumn('batches', 'variety_id'));
        $this->assertFalse(Schema::hasColumn('batches', 'pot_id'));
        $this->assertFalse(Schema::hasColumn('batches', 'monitoring_start_date'));
        $this->assertFalse(Schema::hasColumn('stock_ins', 'batch_id'));
        $this->assertFalse(Schema::hasColumn('stock_ins', 'scion_quantity'));
        $this->assertFalse(Schema::hasColumn('inventories', 'current_quantity'));
    }

    public function test_the_revised_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('scion_varieties'));
        $this->assertTrue(Schema::hasTable('stock_out_details'));

        $this->assertTrue(Schema::hasColumn('batches', 'scion_variety_id'));
        $this->assertTrue(Schema::hasColumn('batches', 'available_count'));
        $this->assertTrue(Schema::hasColumn('stock_ins', 'viable'));
        $this->assertTrue(Schema::hasColumn('stock_ins', 'origin'));
        $this->assertTrue(Schema::hasColumn('inventories', 'inventory_date'));
    }

    public function test_scion_receiving_was_merged_into_stock_ins(): void
    {
        // Client-confirmed: Scion_Receiving is the same real-world receiving
        // event as Stock In, so its columns live on stock_ins and the separate
        // table is retired (see migration 2026_09_26_000003/000004).
        $this->assertFalse(Schema::hasTable('scion_receivings'));

        $this->assertTrue(Schema::hasColumn('stock_ins', 'scion_variety_id'));
        $this->assertTrue(Schema::hasColumn('stock_ins', 'unit_price'));
    }
}
