<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BatchSourceSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function batch(array $overrides = []): Batch
    {
        $variety = ScionVariety::create(['scion_variety_name' => 'Criollo']);
        $user = User::factory()->create();

        return Batch::create(array_merge([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => 100,
            'regraft' => 0,
            'available_count' => 100,
            'grafted_date' => '2026-07-15',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ], $overrides));
    }

    public function test_batches_table_carries_the_source_and_stock_use_columns(): void
    {
        foreach (['source', 'supplier_id', 'seedlings_used', 'scion_used'] as $column) {
            $this->assertTrue(Schema::hasColumn('batches', $column), "Missing batches.{$column}");
        }

        $this->assertFalse(Schema::hasColumn('batches', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('batches', 'total_amount'));
    }

    public function test_a_batch_defaults_to_own_nursery_with_no_tracked_use(): void
    {
        $batch = $this->batch();

        $this->assertSame(Batch::SOURCE_OWN, $batch->source);
        $this->assertNull($batch->supplier_id);
        $this->assertNull($batch->seedlings_used);
        $this->assertNull($batch->scion_used);
        $this->assertFalse($batch->hasTrackedStockUse());
        $this->assertSame('Own nursery', $batch->sourceLabel());
    }

    public function test_a_supplier_delivery_batch_records_its_supplier(): void
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Supplier A',
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);

        $batch = $this->batch([
            'source' => Batch::SOURCE_SUPPLIER,
            'supplier_id' => $supplier->supplier_id,
        ]);

        $this->assertSame($supplier->supplier_id, $batch->supplier->supplier_id);
        $this->assertSame('Supplier', $batch->sourceLabel());
    }

    public function test_a_tracked_own_nursery_batch_reports_stock_use(): void
    {
        $batch = $this->batch([
            'seedlings_used' => 80,
            'scion_used' => 60,
        ]);

        $this->assertTrue($batch->hasTrackedStockUse());
        $this->assertSame(80, $batch->seedlings_used);
        $this->assertSame(60, $batch->scion_used);
    }

    public function test_a_supplier_reference_is_restricted_on_delete(): void
    {
        $supplier = Supplier::create([
            'supplier_name' => 'Supplier A',
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);

        $this->batch([
            'source' => Batch::SOURCE_SUPPLIER,
            'supplier_id' => $supplier->supplier_id,
        ]);

        $this->expectException(QueryException::class);

        $supplier->delete();
    }
}
