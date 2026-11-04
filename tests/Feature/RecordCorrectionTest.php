<?php

namespace Tests\Feature;

use App\Actions\CorrectRecord;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_an_unchanged_regraft_variety_without_received_stock_is_rejected(): void
    {
        $sourceVariety = ScionVariety::create(['scion_variety_name' => 'Source Variety']);
        $currentVariety = ScionVariety::create(['scion_variety_name' => 'Current Without Receipt']);

        $source = Batch::create([
            'scion_variety_id' => $sourceVariety->scion_variety_id,
            'user_id' => $this->admin->id,
            'batch_code' => 'B900',
            'total_grafted' => 100,
            'regraft' => 20,
            'available_count' => 80,
            'grafted_date' => '2026-07-01',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);

        $sub = Batch::create([
            'scion_variety_id' => $currentVariety->scion_variety_id,
            'parent_batch_id' => $source->batch_id,
            'user_id' => $this->admin->id,
            'batch_code' => 'B900-R1',
            'total_grafted' => 20,
            'regraft' => 0,
            'available_count' => 20,
            'grafted_date' => '2026-09-10',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);

        $record = RegraftRecord::create([
            'batch_id' => $sub->batch_id,
            'parent_batch_id' => $source->batch_id,
            'user_id' => $this->admin->id,
            'quantity' => 20,
            'regraft_date' => '2026-09-10',
            'remark' => 'Original remark.',
        ]);

        $this->actingAs($this->userWithPermissions(['record_operations']))
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertViewHas('regraftCorrectionVarieties', fn ($varieties) => $varieties
                ->pluck('scion_variety_id')->contains($currentVariety->scion_variety_id));

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 25,
                'scion_variety_id' => $currentVariety->scion_variety_id,
                'regraft_date' => '2026-09-10',
                'remark' => 'Adjusted after recount.',
            ])
            ->assertSessionHasErrors('quantity', null, 'regraft_correction');

        $this->assertSame(
            'No scions are currently available for this variety. Record new scion stock through Stock In before proceeding.',
            session('errors')->getBag('regraft_correction')->first('quantity'),
        );
        $this->assertSame($currentVariety->scion_variety_id, (int) $sub->fresh()->scion_variety_id);
        $this->assertSame(20, (int) $record->fresh()->quantity);
    }

    public function test_a_regraft_correction_cannot_change_to_a_variety_without_a_scion_stock_in(): void
    {
        $b001r1r1 = Batch::where('batch_code', 'B001-R1-R1')->firstOrFail();
        $record = RegraftRecord::where('batch_id', $b001r1r1->batch_id)->firstOrFail();
        $batangas = ScionVariety::where('scion_variety_name', 'Batangas')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 30,
                'scion_variety_id' => $batangas->scion_variety_id,
                'regraft_date' => $record->regraft_date->toDateString(),
            ])
            ->assertSessionHasErrors('scion_variety_id', null, 'regraft_correction');

        $this->assertSame(
            'No scions are currently available for this variety. Record new scion stock through Stock In before proceeding.',
            session('errors')->getBag('regraft_correction')->first('scion_variety_id'),
        );
        $this->assertSame(30, (int) $record->fresh()->quantity);
        $this->assertSame('Criollo', $b001r1r1->fresh()->scionVariety->scion_variety_name);
    }

    public function test_a_regraft_correction_can_change_to_a_variety_with_a_scion_stock_in(): void
    {
        $b001r1r1 = Batch::where('batch_code', 'B001-R1-R1')->firstOrFail();
        $record = RegraftRecord::where('batch_id', $b001r1r1->batch_id)->firstOrFail();
        $trinitario = ScionVariety::where('scion_variety_name', 'Trinitario')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 40,
                'scion_variety_id' => $trinitario->scion_variety_id,
                'regraft_date' => $record->regraft_date->toDateString(),
            ])
            ->assertRedirect(route('operational.regraft'))
            ->assertSessionHas('success');

        $this->assertSame($trinitario->scion_variety_id, (int) $b001r1r1->fresh()->scion_variety_id);
        $this->assertSame(40, (int) $record->fresh()->quantity);
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

    public function test_a_scion_stock_in_correction_does_not_require_a_supplier(): void
    {
        $variety = $this->scionVariety('No Supplier Correction Scion');
        $stockIn = $this->scionReceipt($variety, 100, ['supplier_id' => null]);

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), [
                'supplier_id' => '',
                'quantity' => 120,
                'scion_variety_id' => $variety->scion_variety_id,
                'unit_price' => 12,
                'origin' => 'Test nursery',
                'date_received' => '2026-08-15',
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success');

        $stockIn->refresh();
        $this->assertNull($stockIn->supplier_id);
        $this->assertSame(120, (int) $stockIn->quantity);
    }

    public function test_a_seedling_stock_in_correction_still_requires_a_supplier(): void
    {
        $stockIn = StockIn::create([
            'supplier_id' => Supplier::query()->value('supplier_id'),
            'scion_variety_id' => null,
            'user_id' => $this->admin->id,
            'quantity' => 100,
            'unit_price' => null,
            'origin' => 'Seedling nursery',
            'notes' => null,
            'date_received' => '2026-08-15',
        ]);

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), [
                'supplier_id' => '',
                'quantity' => 120,
                'origin' => 'Seedling nursery',
                'date_received' => '2026-08-15',
            ])
            ->assertSessionHasErrors('supplier_id', null, 'stockin_correction');

        $this->assertSame(100, (int) $stockIn->fresh()->quantity);
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

    public function test_increasing_a_corrected_regraft_beyond_available_scion_stock_is_rejected(): void
    {
        $scion = $this->scionVariety('Tight Correction Scion');
        $source = $this->customBatch('B910', $this->scionVariety('Source 910'), 500, null, 90);
        $first = $this->customBatch('B910-R1', $scion, 30, $source, 0, '2026-09-10');
        $second = $this->customBatch('B910-R2', $scion, 60, $source, 0, '2026-09-10');

        $this->scionReceipt($scion, 100);
        $record = $this->regraftRecord($source, $first, 30);
        $this->regraftRecord($source, $second, 60);

        // 100 received - 90 consumed = 10 left; returning this record's own 30
        // makes 40 available, so 50 would overdraw the variety.
        $this->assertSame(10, $scion->fresh()->scionBalance());

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 50,
                'scion_variety_id' => $scion->scion_variety_id,
                'regraft_date' => '2026-09-10',
            ])
            ->assertSessionHasErrors('quantity', null, 'regraft_correction');

        $this->assertSame(
            'This correction would exceed the 40 scions available for Tight Correction Scion.',
            session('errors')->getBag('regraft_correction')->first('quantity'),
        );
        $this->assertSame(30, (int) $record->fresh()->quantity);
        $this->assertSame(30, (int) $first->fresh()->available_count);
        $this->assertSame(410, (int) $source->fresh()->available_count);
        $this->assertSame(90, (int) $source->fresh()->regraft);
        $this->assertSame(10, $scion->fresh()->scionBalance());
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->count());
    }

    public function test_changing_a_corrected_regraft_to_a_variety_with_insufficient_stock_is_rejected(): void
    {
        $current = $this->scionVariety('Current Correction Scion');
        $target = $this->scionVariety('Insufficient Correction Scion');
        $source = $this->customBatch('B911', $this->scionVariety('Source 911'), 500, null, 30);
        $sub = $this->customBatch('B911-R1', $current, 30, $source, 0, '2026-09-10');

        $this->scionReceipt($current, 100);
        $this->scionReceipt($target, 10);
        $record = $this->regraftRecord($source, $sub, 30);

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 20,
                'scion_variety_id' => $target->scion_variety_id,
                'regraft_date' => '2026-09-10',
            ])
            ->assertSessionHasErrors('scion_variety_id', null, 'regraft_correction');

        $this->assertSame(
            'This correction would exceed the 10 scions available for Insufficient Correction Scion.',
            session('errors')->getBag('regraft_correction')->first('scion_variety_id'),
        );
        $this->assertSame(30, (int) $record->fresh()->quantity);
        $this->assertSame($current->scion_variety_id, (int) $sub->fresh()->scion_variety_id);
        $this->assertSame(70, $current->fresh()->scionBalance());
        $this->assertSame(10, $target->fresh()->scionBalance());
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->count());
    }

    public function test_a_regraft_correction_can_increase_within_available_scion_stock(): void
    {
        $scion = $this->scionVariety('Valid Correction Scion');
        $source = $this->customBatch('B912', $this->scionVariety('Source 912'), 500, null, 30);
        $sub = $this->customBatch('B912-R1', $scion, 30, $source, 0, '2026-09-10');

        $this->scionReceipt($scion, 100);
        $record = $this->regraftRecord($source, $sub, 30);

        $this->actingAs($this->admin)
            ->post(route('corrections.regraft', $record), [
                'quantity' => 50,
                'scion_variety_id' => $scion->scion_variety_id,
                'regraft_date' => '2026-09-10',
            ])
            ->assertRedirect(route('operational.regraft'))
            ->assertSessionHas('success');

        $this->assertSame(50, (int) $record->fresh()->quantity);
        $this->assertSame(50, (int) $sub->fresh()->total_grafted);
        $this->assertSame(50, (int) $sub->fresh()->available_count);
        $this->assertSame(450, (int) $source->fresh()->available_count);
        $this->assertSame(50, (int) $source->fresh()->regraft);
        $this->assertSame(100, $scion->fresh()->receivedScionQuantity());
        $this->assertSame(50, $scion->fresh()->usedScionQuantity());
        $this->assertSame(50, $scion->fresh()->scionBalance());

        $log = ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->firstOrFail();
        $this->assertStringContainsString('"quantity":{"from":30,"to":50}', $log->description);
    }

    public function test_reducing_a_scion_receipt_below_existing_consumption_is_rejected(): void
    {
        $scion = $this->scionVariety('Stock In Correction Scion');
        $source = $this->customBatch('B913', $this->scionVariety('Source 913'), 200, null, 90);
        $sub = $this->customBatch('B913-R1', $scion, 90, $source, 0, '2026-09-10');
        $stockIn = $this->scionReceipt($scion, 100);
        $this->regraftRecord($source, $sub, 90);

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), $this->stockInPayload($stockIn, ['quantity' => 80]))
            ->assertSessionHasErrors('quantity', null, 'stockin_correction');

        $this->assertSame(
            'This correction would leave Stock In Correction Scion with 80 received scions, below the 90 already consumed.',
            session('errors')->getBag('stockin_correction')->first('quantity'),
        );
        $this->assertSame(100, (int) $stockIn->fresh()->quantity);
        $this->assertSame(10, $scion->fresh()->scionBalance());
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->count());
    }

    public function test_converting_a_consumed_scion_receipt_to_a_seedling_is_rejected(): void
    {
        $scion = $this->scionVariety('Consumed Conversion Scion');
        $source = $this->customBatch('B914', $this->scionVariety('Source 914'), 100, null, 30);
        $sub = $this->customBatch('B914-R1', $scion, 30, $source, 0, '2026-09-10');
        $stockIn = $this->scionReceipt($scion, 100);
        $this->regraftRecord($source, $sub, 30);

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), $this->stockInPayload($stockIn, [
                'scion_variety_id' => null,
                'unit_price' => null,
            ]))
            ->assertSessionHasErrors('scion_variety_id', null, 'stockin_correction');

        $this->assertSame(
            'This correction would leave Consumed Conversion Scion with 0 received scions, below the 30 already consumed.',
            session('errors')->getBag('stockin_correction')->first('scion_variety_id'),
        );
        $this->assertSame($scion->scion_variety_id, (int) $stockIn->fresh()->scion_variety_id);
        $this->assertSame(100, (int) $stockIn->fresh()->quantity);
        $this->assertSame(70, $scion->fresh()->scionBalance());
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->count());
    }

    public function test_changing_the_variety_of_a_consumed_scion_receipt_is_rejected(): void
    {
        $current = $this->scionVariety('Consumed Variety Change');
        $target = $this->scionVariety('Unused Variety Change');
        $source = $this->customBatch('B915', $this->scionVariety('Source 915'), 100, null, 30);
        $sub = $this->customBatch('B915-R1', $current, 30, $source, 0, '2026-09-10');
        $stockIn = $this->scionReceipt($current, 100);
        $this->scionReceipt($target, 50);
        $this->regraftRecord($source, $sub, 30);

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), $this->stockInPayload($stockIn, [
                'scion_variety_id' => $target->scion_variety_id,
                'unit_price' => 10.00,
            ]))
            ->assertSessionHasErrors('scion_variety_id', null, 'stockin_correction');

        $this->assertSame(
            'This correction would leave Consumed Variety Change with 0 received scions, below the 30 already consumed.',
            session('errors')->getBag('stockin_correction')->first('scion_variety_id'),
        );
        $this->assertSame($current->scion_variety_id, (int) $stockIn->fresh()->scion_variety_id);
        $this->assertSame(70, $current->fresh()->scionBalance());
        $this->assertSame(50, $target->fresh()->scionBalance());
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->count());
    }

    public function test_a_stock_in_correction_can_reduce_a_receipt_within_consumption(): void
    {
        $scion = $this->scionVariety('Valid Stock In Correction');
        $source = $this->customBatch('B916', $this->scionVariety('Source 916'), 100, null, 30);
        $sub = $this->customBatch('B916-R1', $scion, 30, $source, 0, '2026-09-10');
        $stockIn = $this->scionReceipt($scion, 100);
        $this->regraftRecord($source, $sub, 30);

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), $this->stockInPayload($stockIn, ['quantity' => 50]))
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success');

        $this->assertSame(50, (int) $stockIn->fresh()->quantity);
        $this->assertSame(50, $scion->fresh()->receivedScionQuantity());
        $this->assertSame(30, $scion->fresh()->usedScionQuantity());
        $this->assertSame(20, $scion->fresh()->scionBalance());

        $log = ActivityLog::where('activity_type', ActivityLog::TYPE_RECORD_CORRECTED)->firstOrFail();
        $this->assertStringContainsString('"quantity":{"from":100,"to":50}', $log->description);
    }

    public function test_an_unconsumed_scion_receipt_can_be_converted_to_a_seedling(): void
    {
        $scion = $this->scionVariety('Unconsumed Conversion Scion');
        $stockIn = $this->scionReceipt($scion, 40);

        $this->actingAs($this->admin)
            ->post(route('corrections.stock-in', $stockIn), $this->stockInPayload($stockIn, [
                'scion_variety_id' => null,
                'unit_price' => null,
            ]))
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success');

        $stockIn->refresh();
        $this->assertNull($stockIn->scion_variety_id);
        $this->assertNull($stockIn->unit_price);
        $this->assertSame(40, (int) $stockIn->quantity);
        $this->assertSame(0, $scion->fresh()->receivedScionQuantity());
        $this->assertSame(0, $scion->fresh()->scionBalance());
    }

    public function test_scion_stock_locks_are_acquired_in_ascending_identifier_order(): void
    {
        $first = $this->scionVariety('Lock Order First');
        $second = $this->scionVariety('Lock Order Second');

        DB::connection()->enableQueryLog();

        ScionVariety::lockForScionStock([
            $second->scion_variety_id,
            $first->scion_variety_id,
        ]);

        $lockedIds = collect(DB::connection()->getQueryLog())
            ->filter(fn (array $entry) => str_contains(strtolower($entry['query']), 'for update')
                && str_contains($entry['query'], 'scion_varieties'))
            ->map(fn (array $entry) => (int) ($entry['bindings'][0] ?? 0))
            ->values()
            ->all();

        DB::connection()->disableQueryLog();

        $this->assertSame(
            [(int) $first->scion_variety_id, (int) $second->scion_variety_id],
            $lockedIds,
        );
    }

    private function scionVariety(string $name): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => $name]);
    }

    private function customBatch(
        string $code,
        ?ScionVariety $variety,
        int $total,
        ?Batch $parent = null,
        int $regraft = 0,
        string $graftedDate = '2026-07-01',
    ): Batch {
        return Batch::create([
            'scion_variety_id' => $variety?->scion_variety_id,
            'parent_batch_id' => $parent?->batch_id,
            'user_id' => $this->admin->id,
            'batch_code' => $code,
            'total_grafted' => $total,
            'regraft' => $regraft,
            'available_count' => $total - $regraft,
            'grafted_date' => $graftedDate,
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function regraftRecord(Batch $source, Batch $sub, int $quantity, string $date = '2026-09-10'): RegraftRecord
    {
        return RegraftRecord::create([
            'batch_id' => $sub->batch_id,
            'parent_batch_id' => $source->batch_id,
            'user_id' => $this->admin->id,
            'quantity' => $quantity,
            'regraft_date' => $date,
            'remark' => null,
        ]);
    }

    private function scionReceipt(ScionVariety $variety, int $quantity, array $overrides = []): StockIn
    {
        return StockIn::create(array_merge([
            'supplier_id' => Supplier::query()->value('supplier_id'),
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $this->admin->id,
            'quantity' => $quantity,
            'unit_price' => 10.00,
            'origin' => 'Test nursery',
            'notes' => null,
            'date_received' => '2026-08-15',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stockInPayload(StockIn $stockIn, array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $stockIn->supplier_id ?? Supplier::query()->value('supplier_id'),
            'quantity' => (int) $stockIn->quantity,
            'scion_variety_id' => $stockIn->scion_variety_id,
            'unit_price' => $stockIn->unit_price,
            'origin' => $stockIn->origin,
            'notes' => $stockIn->notes,
            'date_received' => $stockIn->date_received->toDateString(),
        ], $overrides);
    }

    private function b001Mortality(): MortalityRecord
    {
        return MortalityRecord::query()
            ->whereHas('batch', fn ($query) => $query->where('batch_code', 'B001'))
            ->firstOrFail();
    }
}
