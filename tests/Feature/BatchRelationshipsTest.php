<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\ScionVariety;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part D: model relationships on the revised 15-entity schema.
 */
class BatchRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_belongs_to_scion_variety_and_user(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::factory()->create();

        $batch = Batch::factory()->create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($batch->scionVariety->is($variety));
        $this->assertTrue($batch->user->is($user));
        $this->assertTrue($variety->batches->contains($batch));
    }

    public function test_batch_has_many_mortality_records_and_sums_them(): void
    {
        $batch = Batch::factory()->create();

        MortalityRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 207, 'cause' => 'Disease']);
        MortalityRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 28, 'cause' => 'Pest']);

        $this->assertCount(2, $batch->mortalityRecords);
        $this->assertSame(235, $batch->effectiveMortalityQuantity());
        $this->assertSame(235, MortalityRecord::effectiveQuantityForBatch($batch->batch_id));
    }

    public function test_batch_has_many_replanting_records_and_sums_them(): void
    {
        $batch = Batch::factory()->create();

        ReplantingRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 15]);
        ReplantingRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 5]);

        $this->assertCount(2, $batch->replantingRecords);
        $this->assertSame(20, $batch->totalReplanted());
        $this->assertSame(20, ReplantingRecord::totalForBatch($batch->batch_id));
    }

    public function test_batch_has_many_inventory_snapshots_and_a_latest_snapshot(): void
    {
        $batch = Batch::factory()->create(['viable_count' => 100, 'available_count' => 100]);

        $older = Inventory::factory()->forBatch($batch)->create(['inventory_date' => '2026-08-01']);
        $newer = Inventory::factory()->forBatch($batch)->create(['inventory_date' => '2026-09-01']);

        $this->assertCount(2, $batch->inventories);
        $this->assertTrue($batch->inventories->contains($older));
        $this->assertTrue($batch->latestInventory->is($newer));
        $this->assertTrue($older->batch->is($batch));
    }

    public function test_batch_has_many_stock_out_details_and_derives_scion_variety(): void
    {
        $variety = ScionVariety::factory()->create();
        $batch = Batch::factory()->create(['scion_variety_id' => $variety->scion_variety_id]);
        $header = StockOut::factory()->create();

        $detail = StockOutDetail::factory()->create([
            'stock_out_id' => $header->stock_out_id,
            'batch_id' => $batch->batch_id,
            'quantity' => 10,
        ]);

        $this->assertCount(1, $batch->stockOutDetails);
        $this->assertTrue($detail->batch->is($batch));
        $this->assertTrue($detail->stockOut->is($header));
        // The saving hook derives scion_variety_id from the batch (locked rule 4).
        $this->assertSame((int) $variety->scion_variety_id, (int) $detail->scion_variety_id);
        $this->assertTrue($detail->scionVariety->is($variety));
        $this->assertTrue($variety->stockOutDetails->contains($detail));
        $this->assertTrue($header->details->contains($detail));
        $this->assertSame(10, $header->totalQuantity());
    }
}
