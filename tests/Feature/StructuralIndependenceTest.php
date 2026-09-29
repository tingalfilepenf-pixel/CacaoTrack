<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Part D: structural regression guards for the revised 15-entity model.
 *
 * These tests fail if anyone reintroduces: a link between Mortality_Record and
 * Regraft_Record, a scion stock pool/balance, a duplicate/retired replanting
 * table, or any of the entities/columns the ERD explicitly removed.
 */
class StructuralIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mortality_and_regraft_records_remain_structurally_independent(): void
    {
        $mortalityColumns = Schema::getColumnListing('mortality_records');
        $regraftColumns = Schema::getColumnListing('regraft_records');

        // No cross-referencing columns in either direction.
        foreach ($mortalityColumns as $column) {
            $this->assertStringNotContainsString('regraft', $column);
        }

        foreach ($regraftColumns as $column) {
            $this->assertStringNotContainsString('mortality', $column);
        }

        // No foreign key between the two tables in either direction.
        $mortalityFks = collect(Schema::getForeignKeys('mortality_records'))->pluck('foreign_table');
        $regraftFks = collect(Schema::getForeignKeys('regraft_records'))->pluck('foreign_table');

        $this->assertFalse($mortalityFks->contains('regraft_records'));
        $this->assertFalse($regraftFks->contains('mortality_records'));
    }

    public function test_the_retired_replanting_records_table_no_longer_exists(): void
    {
        // Regraft and Replanting are the same recorded procedure, so there is a
        // single table. It was renamed from `replanting_records` to
        // `regraft_records`; the old name must be gone and no duplicate may exist.
        $this->assertFalse(Schema::hasTable('replanting_records'));
        $this->assertTrue(Schema::hasTable('regraft_records'));
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

    public function test_the_viable_columns_do_not_exist(): void
    {
        // Phase 7A: the client-approved ERD has no viability count on Stock In,
        // Batch or Inventory. Guard against reintroducing them.
        $this->assertFalse(Schema::hasColumn('stock_ins', 'viable'));
        $this->assertFalse(Schema::hasColumn('inventories', 'viable'));
        $this->assertFalse(Schema::hasColumn('batches', 'viable_count'));
    }

    public function test_the_revised_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('scion_varieties'));
        $this->assertTrue(Schema::hasTable('stock_out_details'));

        $this->assertTrue(Schema::hasColumn('batches', 'scion_variety_id'));
        $this->assertTrue(Schema::hasColumn('batches', 'available_count'));
        $this->assertTrue(Schema::hasColumn('batches', 'regraft'));
        $this->assertTrue(Schema::hasColumn('batches', 'parent_batch_id'));
        $this->assertTrue(Schema::hasColumn('regraft_records', 'parent_batch_id'));
        $this->assertTrue(Schema::hasColumn('regraft_records', 'regraft_id'));
        $this->assertTrue(Schema::hasColumn('regraft_records', 'regraft_date'));
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
