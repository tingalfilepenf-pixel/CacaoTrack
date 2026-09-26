<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Part D: cumulative event records sum correctly (matching the notebook-style
 * incremental entries such as "207+28"), and event dates are cast/handled.
 */
class CumulativeEventRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_incremental_mortality_entries_sum_for_the_same_batch(): void
    {
        $batch = Batch::factory()->create();

        // Real notebook evidence: several dated entries recorded against one batch.
        MortalityRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 207, 'cause' => 'Disease']);
        MortalityRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 28, 'cause' => 'Disease']);

        $this->assertSame(235, $batch->effectiveMortalityQuantity());
        $this->assertSame(2, $batch->mortalityRecords()->count());
    }

    public function test_incremental_regraft_entries_sum_for_the_same_batch(): void
    {
        $batch = Batch::factory()->create();

        ReplantingRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 100]);
        ReplantingRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 60]);
        ReplantingRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 35]);

        $this->assertSame(195, $batch->totalReplanted());
        $this->assertSame(3, $batch->replantingRecords()->count());
    }

    public function test_cumulative_sums_are_scoped_per_batch(): void
    {
        $first = Batch::factory()->create();
        $second = Batch::factory()->create();

        MortalityRecord::factory()->create(['batch_id' => $first->batch_id, 'quantity' => 10]);
        MortalityRecord::factory()->create(['batch_id' => $second->batch_id, 'quantity' => 5]);

        $this->assertSame(10, $first->effectiveMortalityQuantity());
        $this->assertSame(5, $second->effectiveMortalityQuantity());
        $this->assertSame(15, MortalityRecord::effectiveQuantityForBatches([$first->batch_id, $second->batch_id]));
    }

    public function test_event_dates_are_cast_to_carbon_and_date_types(): void
    {
        $batch = Batch::factory()->create();

        $mortality = MortalityRecord::factory()->create([
            'batch_id' => $batch->batch_id,
            'recorded_at' => '2026-09-01 08:30:00',
        ]);

        $replanting = ReplantingRecord::factory()->create([
            'batch_id' => $batch->batch_id,
            'replanting_date' => '2026-09-03',
        ]);

        $this->assertInstanceOf(Carbon::class, $mortality->recorded_at);
        $this->assertSame('2026-09-01 08:30:00', $mortality->recorded_at->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(Carbon::class, $replanting->replanting_date);
        $this->assertSame('2026-09-03', $replanting->replanting_date->format('Y-m-d'));
    }

    public function test_stock_in_dates_are_handled_as_dates_for_seedling_and_scion_rows(): void
    {
        $stockIn = StockIn::factory()->create(['date_received' => '2026-07-01']);

        // A scion-inclusive stock-in (the former Scion_Receiving, merged into
        // Stock_In) is identified by its scion variety and casts the date too.
        $scionReceipt = StockIn::factory()->create([
            'quantity' => 80,
            'viable' => 80,
            'scion_variety_id' => ScionVariety::factory(),
            'unit_price' => 12.50,
            'date_received' => '2026-08-15',
        ]);

        $this->assertInstanceOf(Carbon::class, $stockIn->date_received);
        $this->assertSame('2026-07-01', $stockIn->date_received->format('Y-m-d'));

        $this->assertInstanceOf(Carbon::class, $scionReceipt->date_received);
        $this->assertSame('2026-08-15', $scionReceipt->date_received->format('Y-m-d'));
        $this->assertTrue($scionReceipt->isScionEntry());
    }
}
