<?php

namespace Tests\Feature;

use App\Actions\CorrectRecord;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OD-5 record corrections: a mistaken mortality, regraft, stock-out or stock-in
 * entry can be corrected by an administrator; the correction re-validates
 * against the same business rules as a new entry and writes the old/new value
 * of every changed field to the existing Activity_Log.
 */
class RecordCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('email', 'admin@cacaotrack.test')->firstOrFail();
        $this->staff = User::factory()->create();
    }

    public function test_a_non_administrator_cannot_correct_records(): void
    {
        $record = $this->b001Mortality();

        $this->actingAs($this->staff)
            ->post(route('corrections.mortality', $record), [
                'quantity' => 30,
                'cause' => 'Pest',
                'recorded_at' => $record->recorded_at->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(25, (int) $record->fresh()->quantity);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->count());
    }

    public function test_an_administrator_correction_succeeds_and_logs_before_and_after_values(): void
    {
        $record = $this->b001Mortality();

        $this->actingAs($this->admin)
            ->post(route('corrections.mortality', $record), [
                'quantity' => 30,
                'cause' => 'Pest',
                'recorded_at' => $record->recorded_at->toDateString(),
            ])
            ->assertRedirect(route('operational.mortality'))
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertSame(30, (int) $record->quantity);
        $this->assertSame('Pest', $record->cause);

        $log = ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)
            ->latest('activity_id')
            ->firstOrFail();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($record->batch_id, $log->batch_id);
        $this->assertStringContainsString('mortality record', $log->description);
        $this->assertStringContainsString('"quantity":{"from":25,"to":30}', $log->description);
        $this->assertStringContainsString('"cause":{"from":"Disease","to":"Pest"}', $log->description);
    }

    public function test_a_mortality_correction_cannot_exceed_the_batch_capacity(): void
    {
        $record = $this->b001Mortality();

        // B001: total_grafted 500 - regraft 100 = 400 live population, no other
        // mortality, so this record may hold at most 400.
        $this->actingAs($this->admin)
            ->post(route('corrections.mortality', $record), [
                'quantity' => 401,
                'cause' => 'Disease',
                'recorded_at' => $record->recorded_at->toDateString(),
            ])
            ->assertSessionHasErrors('quantity', null, 'mortality_correction');

        $this->assertSame(25, (int) $record->fresh()->quantity);
    }

    public function test_a_regraft_with_a_dependent_sub_batch_is_rejected_with_the_specific_message(): void
    {
        $b001r1 = Batch::where('batch_code', 'B001-R1')->firstOrFail();
        $record = RegraftRecord::where('batch_id', $b001r1->batch_id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 100,
                'scion_variety_id' => $b001r1->scion_variety_id,
                'regraft_date' => $record->regraft_date->toDateString(),
            ])
            ->assertSessionHasErrors(['record'], null, 'regraft_correction');

        $errors = session('errors')->getBag('regraft_correction');
        $this->assertSame(
            CorrectRecord::dependentSubBatchMessage('B001-R1'),
            $errors->first('record'),
        );
        $this->assertSame(100, (int) $record->fresh()->quantity);
    }

    public function test_an_administrator_can_correct_a_regraft_whose_sub_batch_is_untouched(): void
    {
        $b001r1r1 = Batch::where('batch_code', 'B001-R1-R1')->firstOrFail();
        $record = RegraftRecord::where('batch_id', $b001r1r1->batch_id)->firstOrFail();
        $source = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 40,
                'scion_variety_id' => $b001r1r1->scion_variety_id,
                'regraft_date' => $record->regraft_date->toDateString(),
            ])
            ->assertRedirect(route('operational.regraft'))
            ->assertSessionHas('success');

        $this->assertSame(40, (int) $record->fresh()->quantity);
        $this->assertSame(40, (int) $b001r1r1->fresh()->total_grafted);
        $this->assertSame(40, (int) $b001r1r1->fresh()->available_count);

        // Source: 30 returned then 40 moved out → regraft 40, available 40.
        $this->assertSame(40, (int) $source->fresh()->regraft);
        $this->assertSame(40, (int) $source->fresh()->available_count);

        $log = ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->firstOrFail();
        $this->assertStringContainsString('regraft record', $log->description);
        $this->assertStringContainsString('"quantity":{"from":30,"to":40}', $log->description);
    }

    public function test_a_regraft_correction_cannot_exceed_the_source_surviving_capacity(): void
    {
        $b001r1r1 = Batch::where('batch_code', 'B001-R1-R1')->firstOrFail();
        $record = RegraftRecord::where('batch_id', $b001r1r1->batch_id)->firstOrFail();

        // B001-R1 after returning this record's 30: available 80, live
        // (100 - 0) - 5 mortality = 95 → cap 80.
        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 81,
                'scion_variety_id' => $b001r1r1->scion_variety_id,
                'regraft_date' => $record->regraft_date->toDateString(),
            ])
            ->assertSessionHasErrors('quantity', null, 'regraft_correction');

        $this->assertSame(30, (int) $record->fresh()->quantity);
    }

    public function test_an_administrator_can_correct_a_stock_in_record(): void
    {
        $stockIn = StockIn::query()->orderBy('stock_in_id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), [
                'supplier_id' => $stockIn->supplier_id,
                'quantity' => 450,
                'origin' => $stockIn->origin,
                'date_received' => $stockIn->date_received->toDateString(),
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success');

        $this->assertSame(450, (int) $stockIn->fresh()->quantity);

        $log = ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->firstOrFail();
        $this->assertStringContainsString('stock-in record', $log->description);
        $this->assertStringContainsString('"quantity":{"from":500,"to":450}', $log->description);
    }

    public function test_a_stock_in_correction_is_validated_like_a_new_entry(): void
    {
        $stockIn = StockIn::query()->orderBy('stock_in_id')->firstOrFail();
        $variety = Batch::where('batch_code', 'B001')->value('scion_variety_id');

        // Scion variety without a unit price violates the "both or neither" rule.
        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), [
                'supplier_id' => $stockIn->supplier_id,
                'quantity' => 100,
                'scion_variety_id' => $variety,
                'origin' => $stockIn->origin,
                'date_received' => $stockIn->date_received->toDateString(),
            ])
            ->assertSessionHasErrors('unit_price', null, 'stockin_correction');

        $this->assertSame(500, (int) $stockIn->fresh()->quantity);
    }

    public function test_an_administrator_can_correct_a_stock_out_and_balances_are_restored(): void
    {
        $header = StockOut::query()->with('details')->firstOrFail();
        $detail = $header->details->first();
        $batchBefore = (int) Batch::whereKey($detail->batch_id)->value('available_count');

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-out', $header), [
                'client_id' => $header->client_id,
                'release_date' => $header->release_date->toDateString(),
                'details' => [[
                    'id' => $detail->stock_out_detail_id,
                    'batch_id' => $detail->batch_id,
                    'quantity' => 10,
                    'unit' => $detail->unit,
                    'unit_price' => $detail->unit_price,
                ]],
            ])
            ->assertRedirect(route('inventory.stock-out'))
            ->assertSessionHas('success');

        $this->assertSame(10, (int) $detail->fresh()->quantity);
        // The original release was 20; re-releasing 10 returns 10 to the batch.
        $this->assertSame($batchBefore + 10, (int) Batch::whereKey($detail->batch_id)->value('available_count'));

        $log = ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->firstOrFail();
        $this->assertStringContainsString('stock-out record', $log->description);
        $this->assertStringContainsString('"quantity":{"from":20,"to":10}', $log->description);
    }

    public function test_a_stock_out_correction_cannot_exceed_the_surviving_capacity(): void
    {
        $header = StockOut::query()->with('details')->firstOrFail();
        $detail = $header->details->first();
        $batchBefore = (int) Batch::whereKey($detail->batch_id)->value('available_count');

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-out', $header), [
                'client_id' => $header->client_id,
                'release_date' => $header->release_date->toDateString(),
                'details' => [[
                    'id' => $detail->stock_out_detail_id,
                    'batch_id' => $detail->batch_id,
                    'quantity' => 100,
                    'unit' => $detail->unit,
                    'unit_price' => $detail->unit_price,
                ]],
            ])
            ->assertSessionHasErrors('details', null, 'stockout_correction');

        $this->assertSame(20, (int) $detail->fresh()->quantity);
        $this->assertSame($batchBefore, (int) Batch::whereKey($detail->batch_id)->value('available_count'));
    }

    private function b001Mortality(): MortalityRecord
    {
        return MortalityRecord::query()
            ->whereHas('batch', fn ($query) => $query->where('batch_code', 'B001'))
            ->firstOrFail();
    }
}
