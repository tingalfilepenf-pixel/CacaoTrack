<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\ReplantingRecord;
use App\Models\ScionVariety;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part D (ties back to the corrected Gap formula): with real mortality rows,
 * Gap = Batch::effectiveMortalityQuantity() and is NOT the always-zero
 * viable − available − stock_out identity.
 */
class GapReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_gap_equals_recorded_mortality_for_a_batch_with_real_deaths(): void
    {
        $variety = ScionVariety::factory()->create(['scion_variety_name' => 'Criollo']);

        $batch = Batch::factory()->create([
            'scion_variety_id' => $variety->scion_variety_id,
            'total_grafted' => 200,
            'viable_count' => 180,
            // Available is not yet reconciled against mortality (the real gap).
            'available_count' => 180,
        ]);

        MortalityRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 207]);
        MortalityRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 28]);
        ReplantingRecord::factory()->create(['batch_id' => $batch->batch_id, 'quantity' => 100]);

        $gap = AnalyticsMetrics::gapReport();

        $this->assertSame(235, $gap['totalGap']);
        $this->assertNotSame(0, $gap['totalGap']);

        $row = collect($gap['rows'])->firstWhere('batch', $batch->batch_code);
        $this->assertSame(235, $row['gap']);
        $this->assertSame(235, $batch->effectiveMortalityQuantity());

        // The old (wrong) identity always resolves to 0 — prove the gap is not that.
        $oldIdentity = max(0, 180 - 180 - 0);
        $this->assertSame(0, $oldIdentity);
        $this->assertNotSame($oldIdentity, $row['gap']);
    }

    public function test_gap_report_filters_by_scion_variety(): void
    {
        $criollo = ScionVariety::factory()->create(['scion_variety_name' => 'Criollo']);
        $trinitario = ScionVariety::factory()->create(['scion_variety_name' => 'Trinitario']);

        $first = Batch::factory()->create(['scion_variety_id' => $criollo->scion_variety_id]);
        $second = Batch::factory()->create(['scion_variety_id' => $trinitario->scion_variety_id]);

        MortalityRecord::factory()->create(['batch_id' => $first->batch_id, 'quantity' => 40]);
        MortalityRecord::factory()->create(['batch_id' => $second->batch_id, 'quantity' => 15]);

        $filtered = AnalyticsMetrics::gapReport(null, $trinitario->scion_variety_id);

        $this->assertSame(15, $filtered['totalGap']);
        $this->assertSame($second->batch_code, $filtered['rows'][0]['batch']);
    }
}
