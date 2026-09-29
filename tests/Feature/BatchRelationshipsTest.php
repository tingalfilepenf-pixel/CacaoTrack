<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
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

    public function test_batch_regraft_records_and_regraft_counter_are_centralized(): void
    {
        $batch = Batch::factory()->create(['regraft' => 0]);

        RegraftRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 15]);
        RegraftRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 5]);

        $this->assertCount(2, $batch->regraftRecords);
        $this->assertSame(20, RegraftRecord::totalForBatch($batch->batch_id));

        // Phase 7B: totalRegrafted() reads the cached `batches.regraft` counter.
        $batch->update(['regraft' => 20]);
        $this->assertSame(20, $batch->fresh()->totalRegrafted());
    }

    public function test_batch_regraft_records_as_parent_links_a_source_to_its_sub_batches(): void
    {
        $source = Batch::factory()->create(['batch_code' => 'B001']);
        $sub = Batch::factory()->create([
            'batch_code' => 'B001-R1',
            'parent_batch_id' => $source->batch_id,
        ]);

        $record = RegraftRecord::factory()->create([
            'batch_id' => $sub->batch_id,
            'parent_batch_id' => $source->batch_id,
            'quantity' => 30,
        ]);

        $this->assertCount(1, $source->regraftRecordsAsParent);
        $this->assertTrue($source->regraftRecordsAsParent->first()->is($record));
        $this->assertTrue($record->parent->is($source));
        $this->assertTrue($record->subBatch->is($sub));
        $this->assertSame(30, RegraftRecord::totalRegraftedFromBatch($source->batch_id));
    }

    public function test_batch_has_many_inventory_snapshots_and_a_latest_snapshot(): void
    {
        $batch = Batch::factory()->create(['available_count' => 100]);

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

    public function test_batch_parent_and_sub_batch_relations(): void
    {
        $parent = Batch::factory()->create(['batch_code' => 'B001']);
        $childA = Batch::factory()->create([
            'batch_code' => 'B001-R1',
            'parent_batch_id' => $parent->batch_id,
        ]);
        $childB = Batch::factory()->create([
            'batch_code' => 'B001-R2',
            'parent_batch_id' => $parent->batch_id,
        ]);

        // Parent is a plain batch; children are sub-batches.
        $this->assertFalse($parent->isSubBatch());
        $this->assertNull($parent->parent);

        $this->assertTrue($childA->isSubBatch());
        $this->assertTrue($childA->parent->is($parent));

        // The parent sees both linked sub-batches.
        $this->assertCount(2, $parent->subBatches);
        $this->assertTrue($parent->subBatches->contains($childA));
        $this->assertTrue($parent->subBatches->contains($childB));
    }
}
