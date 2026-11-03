<?php

namespace Tests\Feature;

use App\Actions\RegraftBatch;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\StockOutDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesScionStockIn;
use Tests\TestCase;

/**
 * Phase 7B: the regraft / sub-batch flow.
 *
 * A regraft moves a quantity out of a source batch into a linked sub-batch
 * (its own 21-day cycle and possibly a different Scion Variety), deducting the
 * parent's live `available_count` and incrementing its `regraft` counter — all
 * atomically through {@see RegraftBatch}. `total_grafted` is a cumulative,
 * non-decreasing log (client-confirmed), so a regraft never reduces it.
 */
class RegraftFlowTest extends TestCase
{
    use CreatesScionStockIn;
    use RefreshDatabase;

    private function variety(string $name = 'Batangas', bool $receivedInStockIn = true): ScionVariety
    {
        $variety = ScionVariety::create(['scion_variety_name' => $name]);

        if ($receivedInStockIn) {
            $this->createScionStockIn($variety);
        }

        return $variety;
    }

    private function batch(User $user, ScionVariety $variety, string $code = 'B001', int $total = 100): Batch
    {
        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => $total,
            'regraft' => 0,
            'available_count' => $total,
            'grafted_date' => now()->subMonths(2)->toDateString(),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function mortality(Batch $batch, User $user, int $quantity): MortalityRecord
    {
        return MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => $quantity,
            'cause' => 'Disease',
            'recorded_at' => now()->subDays(15)->toDateString(),
        ]);
    }

    /**
     * @return TestResponse
     */
    private function postRegraft(User $user, Batch $source, int|string $quantity, ?ScionVariety $variety = null, ?string $date = null)
    {
        $variety ??= $source->scionVariety;

        return $this->actingAs($user)
            ->from(route('operational.regraft'))
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => $quantity,
                'scion_variety_id' => $variety?->scion_variety_id,
                'regraft_date' => $date ?? now()->subDays(5)->toDateString(),
                'remark' => 'Replaced affected stock.',
            ]);
    }

    public function test_happy_path_moves_quantity_into_a_linked_sub_batch(): void
    {
        $user = User::factory()->create();
        $batangas = $this->variety('Batangas');
        $toledo = $this->variety('Toledo');
        $source = $this->batch($user, $batangas, 'B001', 500);
        $this->mortality($source, $user, 25);

        $this->postRegraft($user, $source, 100, $toledo)
            ->assertRedirect(route('operational.regraft'))
            ->assertSessionHas('success');

        $source->refresh();
        // total_grafted is cumulative and unchanged by the regraft; only the
        // live available balance drops and the regraft counter rises.
        $this->assertSame(500, (int) $source->total_grafted);
        $this->assertSame(400, (int) $source->available_count);
        $this->assertSame(100, (int) $source->regraft);

        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();
        $this->assertSame(100, (int) $sub->total_grafted);
        $this->assertSame(100, (int) $sub->available_count);
        $this->assertSame(0, (int) $sub->regraft);
        $this->assertSame($source->batch_id, (int) $sub->parent_batch_id);
        $this->assertSame($toledo->scion_variety_id, (int) $sub->scion_variety_id);
        $this->assertSame($user->id, (int) $sub->user_id);
        $this->assertSame(now()->subDays(5)->toDateString(), $sub->grafted_date->format('Y-m-d'));

        $this->assertDatabaseHas('regraft_records', [
            'batch_id' => $sub->batch_id,
            'parent_batch_id' => $source->batch_id,
            'user_id' => $user->id,
            'quantity' => 100,
            'remark' => 'Replaced affected stock.',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'batch_id' => $sub->batch_id,
            'activity_type' => ActivityLog::TYPE_REGRAFT_RECORDED,
        ]);
    }

    public function test_second_regraft_from_the_same_source_appends_r2(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 200);

        $this->postRegraft($user, $source, 50);
        $this->postRegraft($user, $source->fresh(), 30);

        $this->assertDatabaseHas('batches', ['batch_code' => 'B001-R1']);
        $this->assertDatabaseHas('batches', ['batch_code' => 'B001-R2']);
        $this->assertSame(80, (int) $source->fresh()->regraft);
    }

    public function test_regrafting_a_sub_batch_produces_a_nested_code(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 100);

        $this->postRegraft($user, $source, 40);
        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        $this->postRegraft($user, $sub, 10);

        $this->assertDatabaseHas('batches', [
            'batch_code' => 'B001-R1-R1',
            'parent_batch_id' => $sub->batch_id,
        ]);
    }

    public function test_quantity_must_be_positive_and_numeric(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        foreach ([0, -5, 'abc'] as $quantity) {
            $this->postRegraft($user, $source, $quantity)
                ->assertSessionHasErrors('quantity', null, 'regraft');
        }

        $this->assertDatabaseCount('regraft_records', 0);
        $this->assertSame(100, (int) $source->fresh()->total_grafted);
    }

    public function test_quantity_cannot_exceed_the_regraftable_quantity(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 100);

        // available 100, (total_grafted 100 - regraft 0) - mortality 0 -> 100.
        $this->postRegraft($user, $source, 101)
            ->assertSessionHasErrors('quantity', null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
        $this->assertSame(100, (int) $source->fresh()->available_count);
        $this->assertSame(0, (int) $source->fresh()->regraft);
    }

    public function test_regraft_cannot_exceed_available_minus_recorded_mortality(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 100);
        $this->mortality($source, $user, 90);

        // regraftable = min(100, (100 - 0) - 90) = 10; dead root stock cannot be
        // regrafted.
        $this->assertSame(10, $source->fresh()->survivingQuantity());

        $this->postRegraft($user, $source, 11)
            ->assertSessionHasErrors('quantity', null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_caps_are_computed_against_the_remaining_population_not_the_raw_total(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 500);

        // Regraft 100 out of the 500. Under the confirmed cumulative rule the
        // source's total_grafted stays 500; available drops to 400 and the
        // regraft counter becomes 100.
        $this->postRegraft($user, $source, 100)->assertSessionHas('success');

        $source->refresh();
        $this->assertSame(500, (int) $source->total_grafted);
        $this->assertSame(400, (int) $source->available_count);
        $this->assertSame(100, (int) $source->regraft);

        // Both caps are computed against (total_grafted - regraft) = 400, NOT
        // the raw total_grafted = 500.
        $this->assertSame(400, $source->remainingMortalityCapacity());
        $this->assertSame(400, $source->survivingQuantity());

        // 450 exceeds the 400 that actually remain -> rejected (a raw
        // total_grafted ceiling would have allowed it).
        $this->postRegraft($user, $source, 450)
            ->assertSessionHasErrors('quantity', null, 'regraft');
        $this->assertSame(1, Batch::where('parent_batch_id', $source->batch_id)->count());

        // With mortality recorded, the cap falls from 400 to 350 because the
        // denominator is (500 - 100) - 50, not 500 - 50.
        $this->mortality($source, $user, 50);
        $source->refresh();
        $this->assertSame(350, $source->remainingMortalityCapacity());
        $this->assertSame(350, $source->survivingQuantity());

        // 400 now exceeds the 350 still attributable -> rejected.
        $this->postRegraft($user, $source, 400)
            ->assertSessionHasErrors('quantity', null, 'regraft');
        $this->assertSame(1, Batch::where('parent_batch_id', $source->batch_id)->count());
    }

    public function test_scion_variety_is_required_and_must_be_active(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)->from(route('operational.regraft'))
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => 10,
                'regraft_date' => now()->subDays(5)->toDateString(),
            ])
            ->assertSessionHasErrors('scion_variety_id', null, 'regraft');

        $archived = $this->variety('Archived');
        $archived->update(['archived_at' => now()]);

        $this->postRegraft($user, $source, 10, $archived)
            ->assertSessionHasErrors('scion_variety_id', null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_archived_source_batch_is_rejected(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());
        $source->update(['archived_at' => now()]);

        $this->postRegraft($user, $source, 10)
            ->assertSessionHasErrors('batch_id', null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_future_regraft_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->postRegraft($user, $source, 10, null, now()->addDay()->toDateString())
            ->assertSessionHasErrors('regraft_date', null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_regraft_date_cannot_precede_the_source_grafted_date(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());
        $source->update(['grafted_date' => now()->subDays(5)->toDateString()]);

        $this->postRegraft($user, $source, 10, null, now()->subDays(20)->toDateString())
            ->assertSessionHasErrors('regraft_date', null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_a_failure_after_the_source_update_rolls_everything_back(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 100);

        ActivityLog::creating(function (): void {
            throw new \RuntimeException('forced failure');
        });

        try {
            (new RegraftBatch)->execute(
                $source,
                40,
                (int) $source->scionVariety->scion_variety_id,
                now()->subDays(5)->toDateString(),
                'Should roll back.',
                (int) $user->id,
            );

            $this->fail('The forced activity-log failure should have aborted the transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('forced failure', $exception->getMessage());
        } finally {
            ActivityLog::flushEventListeners();
        }

        $source->refresh();
        $this->assertSame(100, (int) $source->total_grafted);
        $this->assertSame(100, (int) $source->available_count);
        $this->assertSame(0, (int) $source->regraft);
        $this->assertDatabaseCount('regraft_records', 0);
        $this->assertDatabaseMissing('batches', ['batch_code' => 'B001-R1']);
    }

    public function test_duplicate_submission_is_blocked(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 100);

        $this->postRegraft($user, $source, 20)->assertSessionHas('success');

        $this->postRegraft($user, $source->fresh(), 20)
            ->assertSessionHasErrors('submission', null, 'regraft');

        $this->assertSame(1, Batch::where('parent_batch_id', $source->batch_id)->count());
    }

    public function test_a_user_without_record_operations_gets_403(): void
    {
        $user = User::factory()->create(['permissions' => ['view_analytics']]);
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => 10,
                'scion_variety_id' => $source->scion_variety_id,
                'regraft_date' => now()->subDays(5)->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_invariants_hold_after_a_regraft(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 500);
        $this->mortality($source, $user, 25);

        $this->postRegraft($user, $source, 100);

        $source->refresh();
        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        foreach ([$source, $sub] as $batch) {
            $this->assertGreaterThanOrEqual(0, (int) $batch->available_count);
            $this->assertLessThanOrEqual((int) $batch->total_grafted, (int) $batch->available_count);
        }

        // total_grafted is a cumulative, non-decreasing log: the source's
        // total_grafted is unchanged by the regraft and the new sub-batch records
        // the moved quantity as its own total_grafted.
        $this->assertSame(500, (int) $source->total_grafted);
        $this->assertSame(400, (int) $source->available_count);
        $this->assertSame(100, (int) $source->regraft);
        $this->assertSame(100, (int) $sub->total_grafted);

        // What is conserved across the family is the still-attributable
        // population, (total_grafted - regraft): parent 400 + sub-batch 100.
        $this->assertSame(
            500,
            ((int) $source->total_grafted - (int) $source->regraft)
                + ((int) $sub->total_grafted - (int) $sub->regraft),
        );

        // The cached counter reconciles with the regraft records.
        $this->assertSame(
            $source->totalRegrafted(),
            RegraftRecord::totalRegraftedFromBatch($source->batch_id),
        );
        $this->assertSame(100, RegraftRecord::totalRegraftedFromBatch($source->batch_id));
    }

    public function test_mortality_and_regraft_are_independent_across_the_family(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 200);

        $this->postRegraft($user, $source, 50);
        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        $mortalityCountBefore = MortalityRecord::count();

        // Mortality on the parent does not touch the regraft counter or the sub-batch.
        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $source->batch_id,
            'quantity' => 10,
            'cause' => 'Pest',
            'recorded_at' => now()->subDays(2)->toDateString(),
        ])->assertRedirect(route('operational.mortality'));

        $this->assertSame(50, (int) $source->fresh()->regraft);
        $this->assertSame(50, (int) $sub->fresh()->available_count);

        // Mortality on the sub-batch does not touch the parent.
        $this->actingAs($user)->post(route('operational.mortality.records.store'), [
            'batch_id' => $sub->batch_id,
            'quantity' => 5,
            'cause' => 'Pest',
            'recorded_at' => now()->subDays(2)->toDateString(),
        ]);

        // The parent's total_grafted is cumulative and unchanged; the sub-batch
        // mortality is independent of it.
        $this->assertSame(200, (int) $source->fresh()->total_grafted);
        $this->assertSame(50, (int) $source->fresh()->regraft);
        $this->assertSame(5, $sub->fresh()->effectiveMortalityQuantity());

        // Regrafting never creates or edits a Mortality_Record.
        $this->assertSame($mortalityCountBefore + 2, MortalityRecord::count());
    }

    public function test_parent_counts_are_locked_after_a_regraft(): void
    {
        $user = User::factory()->create();
        $variety = $this->variety();
        $source = $this->batch($user, $variety, 'B001', 200);

        $this->postRegraft($user, $source, 50);

        $this->actingAs($user)
            ->put(route('operational.batches.update', $source), [
                'batch_code' => 'B001',
                'scion_variety_id' => $variety->scion_variety_id,
                'total_grafted' => 9999,
                'grafted_date' => now()->subMonths(2)->toDateString(),
                'status' => Batch::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('operational.batches'));

        $source->refresh();
        // The rejected edit must not have changed the cumulative total_grafted,
        // and the regraft's available deduction still stands.
        $this->assertSame(200, (int) $source->total_grafted);
        $this->assertSame(150, (int) $source->available_count);
    }

    public function test_stock_out_from_a_sub_batch_decrements_only_that_sub_batch(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 200);
        $client = Client::create(['client_name' => 'Cacao Farmers Coop', 'contact_number' => '000', 'address' => 'Davao']);

        $this->postRegraft($user, $source, 50);
        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        $this->actingAs($user)->post(route('inventory.stock-out.store'), [
            'client_id' => $client->client_id,
            'release_date' => now()->subDay()->toDateString(),
            'details' => [
                ['batch_id' => $sub->batch_id, 'quantity' => 20, 'unit' => 'pots'],
            ],
        ])->assertRedirect(route('inventory.stock-out'));

        $this->assertSame(30, (int) $sub->fresh()->available_count);
        $this->assertSame(150, (int) $source->fresh()->available_count);
        $this->assertDatabaseHas('stock_out_details', [
            'batch_id' => $sub->batch_id,
            'quantity' => 20,
        ]);
    }

    public function test_inventory_snapshot_works_for_a_sub_batch(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 200);

        $this->postRegraft($user, $source, 50);
        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        $this->actingAs($user)->post(route('inventory.snapshots.store'), [
            'batch_id' => $sub->batch_id,
            'inventory_date' => now()->toDateString(),
        ])->assertRedirect(route('inventory.index'));

        $snapshot = Inventory::where('batch_id', $sub->batch_id)->firstOrFail();
        $this->assertSame(50, (int) $snapshot->total_grafted);
        $this->assertSame(0, (int) $snapshot->regraft);
        $this->assertSame(Inventory::STATUS_ACTIVE, $snapshot->status);
    }

    public function test_the_regraft_page_no_longer_uses_the_remaining_deficit_cap(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertSee('Regrafted plants move to a new sub-batch')
            ->assertDontSee('remaining deficit', false)
            ->assertDontSee('remainingRegraftCapacity');
    }

    public function test_regraft_ledger_page_lists_source_and_sub_batch(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 'B001', 200);

        $this->postRegraft($user, $source, 50);

        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertSee('Regraft History')
            ->assertSee('B001')
            ->assertSee('B001-R1');
    }

    public function test_stock_out_detail_is_derived_for_a_sub_batch_without_special_casing(): void
    {
        $user = User::factory()->create();
        $toledo = $this->variety('Toledo');
        $source = $this->batch($user, $this->variety(), 'B001', 200);
        $client = Client::create(['client_name' => 'Coop', 'contact_number' => '0', 'address' => 'Davao']);

        $this->postRegraft($user, $source, 50, $toledo);
        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        $this->actingAs($user)->post(route('inventory.stock-out.store'), [
            'client_id' => $client->client_id,
            'release_date' => now()->subDay()->toDateString(),
            'details' => [
                ['batch_id' => $sub->batch_id, 'quantity' => 10, 'unit' => 'pots'],
            ],
        ]);

        $detail = StockOutDetail::where('batch_id', $sub->batch_id)->firstOrFail();
        $this->assertSame((int) $toledo->scion_variety_id, (int) $detail->scion_variety_id);
    }

    public function test_a_direct_regraft_batch_call_without_a_stock_in_receipt_fails_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety('Source'), 'B001', 100);
        $notReceived = $this->variety('Not Received Scion', false);

        try {
            (new RegraftBatch)->execute(
                $source,
                10,
                (int) $notReceived->scion_variety_id,
                now()->subDays(5)->toDateString(),
                'Should roll back.',
                (int) $user->id,
            );

            $this->fail('A non-qualifying variety should have aborted the transaction.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Select a scion variety that has been recorded in Stock In.',
                $exception->errors()['scion_variety_id'][0] ?? null,
            );
        }

        $source->refresh();
        $this->assertSame(100, (int) $source->available_count);
        $this->assertSame(0, (int) $source->regraft);
        $this->assertDatabaseCount('regraft_records', 0);
        $this->assertDatabaseMissing('batches', ['batch_code' => 'B001-R1']);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_available_scion_quantity_is_received_minus_regrafted(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Measured Scion']);
        $this->createScionStockIn($variety, $user, ['quantity' => 50]);

        $source = $this->batch($user, $this->variety('Source'), 'B001', 100);

        (new RegraftBatch)->execute(
            $source,
            20,
            (int) $variety->scion_variety_id,
            now()->subDays(5)->toDateString(),
            null,
            (int) $user->id,
        );

        $variety->refresh();
        $this->assertSame(50, $variety->receivedScionQuantity());
        $this->assertSame(20, $variety->usedScionQuantity());
        $this->assertSame(30, $variety->availableScionQuantity());
    }

    public function test_regraft_quantity_cannot_exceed_the_available_scion_quantity(): void
    {
        $user = User::factory()->create();
        $limited = ScionVariety::create(['scion_variety_name' => 'Limited Scion']);
        $this->createScionStockIn($limited, $user, ['quantity' => 10]);

        $source = $this->batch($user, $this->variety('Source'), 'B001', 100);

        $this->postRegraft($user, $source, 11, $limited)
            ->assertSessionHasErrors('quantity', null, 'regraft');

        $this->assertSame(
            'Regraft quantity cannot exceed the 10 scions available for Limited Scion.',
            session('errors')->getBag('regraft')->first('quantity'),
        );

        $source->refresh();
        $this->assertSame(100, (int) $source->available_count);
        $this->assertSame(0, (int) $source->regraft);
        $this->assertDatabaseCount('batches', 1);
        $this->assertDatabaseCount('regraft_records', 0);
        $this->assertSame(0, ActivityLog::count());

        // Exactly the available quantity is allowed.
        $this->postRegraft($user, $source, 10, $limited)->assertSessionHas('success');

        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();
        $this->assertSame($limited->scion_variety_id, (int) $sub->scion_variety_id);
        $this->assertSame(0, $limited->fresh()->availableScionQuantity());

        // A fully used variety stays listed but cannot supply another regraft.
        $secondSource = $this->batch($user, $this->variety('Second Source'), 'B002', 100);

        $this->postRegraft($user, $secondSource, 1, $limited)
            ->assertSessionHasErrors('quantity', null, 'regraft');

        $this->assertSame(
            'No scions are currently available for this variety. Record new scion stock through Stock In before proceeding.',
            session('errors')->getBag('regraft')->first('quantity'),
        );
        $this->assertDatabaseCount('batches', 3);
        $this->assertDatabaseCount('regraft_records', 1);
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_a_direct_regraft_batch_call_cannot_exceed_available_scions_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $limited = ScionVariety::create(['scion_variety_name' => 'Limited Direct Scion']);
        $this->createScionStockIn($limited, $user, ['quantity' => 10]);
        $source = $this->batch($user, $this->variety('Source'), 'B001', 100);

        try {
            (new RegraftBatch)->execute(
                $source,
                11,
                (int) $limited->scion_variety_id,
                now()->subDays(5)->toDateString(),
                'Should roll back.',
                (int) $user->id,
            );

            $this->fail('A quantity above the scion stock should have aborted the transaction.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Regraft quantity cannot exceed the 10 scions available for Limited Direct Scion.',
                $exception->errors()['quantity'][0] ?? null,
            );
        }

        $source->refresh();
        $this->assertSame(100, (int) $source->available_count);
        $this->assertSame(0, (int) $source->regraft);
        $this->assertDatabaseCount('regraft_records', 0);
        $this->assertDatabaseMissing('batches', ['batch_code' => 'B001-R1']);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_pending_regraft_quantity_is_processed_as_new_scions_arrive(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Pending Scion']);
        $this->createScionStockIn($variety, $user, ['quantity' => 250]);

        $source = $this->batch($user, $this->variety('Source'), 'B001', 280);

        // Initial state: 250 scions available, 280 plants pending regraft.
        $this->assertSame(250, $variety->fresh()->availableScionQuantity());
        $this->assertSame(280, $source->fresh()->survivingQuantity());

        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertSee('PENDING REGRAFT')
            ->assertViewHas('summaries', fn ($summaries) => $summaries
                ->first(fn (array $summary) => $summary['batch']->batch_id === $source->batch_id)['regraftable'] === 280);

        // Regraft as many as the scion stock allows: 250, leaving 30 pending.
        $this->postRegraft($user, $source, 250, $variety)
            ->assertSessionHas('success');

        $source->refresh();
        $this->assertSame(30, (int) $source->available_count);
        $this->assertSame(250, (int) $source->regraft);
        $this->assertSame(30, $source->survivingQuantity());
        $this->assertSame(0, $variety->fresh()->availableScionQuantity());

        // The remaining pending quantity cannot be processed without stock.
        $this->postRegraft($user, $source, 1, $variety)
            ->assertSessionHasErrors('quantity', null, 'regraft');

        $this->assertSame(0, $variety->fresh()->availableScionQuantity());
        $this->assertDatabaseCount('regraft_records', 1);

        // The pending column reflects the remaining 30, and the form explains
        // that the quantity will stay pending until scions arrive.
        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries
                ->first(fn (array $summary) => $summary['batch']->batch_id === $source->batch_id)['regraftable'] === 30)
            ->assertSee('will stay pending');

        // Receiving 20 new scions makes the pending work eligible again.
        $this->createScionStockIn($variety, $user, ['quantity' => 20]);
        $this->assertSame(20, $variety->fresh()->availableScionQuantity());
        $this->assertSame(30, $source->fresh()->survivingQuantity());

        $this->postRegraft($user, $source, 20, $variety)->assertSessionHas('success');
        $this->assertSame(10, $source->fresh()->survivingQuantity());
        $this->assertSame(0, $variety->fresh()->availableScionQuantity());

        // Receive the final 10 and clear the pending quantity.
        $this->createScionStockIn($variety, $user, ['quantity' => 10]);
        $this->postRegraft($user, $source, 10, $variety)->assertSessionHas('success');

        $source->refresh();
        $this->assertSame(0, (int) $source->available_count);
        $this->assertSame(280, (int) $source->regraft);
        $this->assertSame(0, $source->survivingQuantity());
        $this->assertSame(0, $variety->fresh()->availableScionQuantity());
        $this->assertSame(280, $variety->fresh()->usedScionQuantity());
        $this->assertSame(280, $variety->fresh()->receivedScionQuantity());

        // Each partial regraft created exactly one sub-batch and one record.
        $this->assertSame(3, Batch::where('parent_batch_id', $source->batch_id)->count());
        $this->assertSame(3, RegraftRecord::where('parent_batch_id', $source->batch_id)->count());

        // The 21-day cycle is still derived from the regraft date.
        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();
        $this->assertSame(
            $sub->grafted_date->copy()->addDays(Batch::CYCLE_DAYS)->toDateString(),
            $sub->cycleReadyDate()->toDateString(),
        );
    }

    public function test_scion_variety_balances_are_independent(): void
    {
        $user = User::factory()->create();
        $criollo = ScionVariety::create(['scion_variety_name' => 'Criollo']);
        $toledo = ScionVariety::create(['scion_variety_name' => 'Toledo']);
        $this->createScionStockIn($criollo, $user, ['quantity' => 100]);
        $this->createScionStockIn($toledo, $user, ['quantity' => 60]);

        $source = $this->batch($user, $this->variety('Source'), 'B001', 300);

        $this->postRegraft($user, $source, 40, $criollo)->assertSessionHas('success');
        $this->assertSame(60, $criollo->fresh()->availableScionQuantity());
        $this->assertSame(60, $toledo->fresh()->availableScionQuantity());

        $this->postRegraft($user, $source, 30, $toledo)->assertSessionHas('success');
        $this->assertSame(60, $criollo->fresh()->availableScionQuantity());
        $this->assertSame(30, $toledo->fresh()->availableScionQuantity());

        $source->refresh();
        $this->assertSame(230, (int) $source->available_count);
        $this->assertSame(70, (int) $source->regraft);
    }

    public function test_seedling_stock_is_never_consumed_as_scion_stock(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Scion Only']);
        $this->createScionStockIn($variety, $user, ['quantity' => 100]);

        $seedlings = StockIn::factory()->create([
            'supplier_id' => null,
            'scion_variety_id' => null,
            'user_id' => $user->id,
            'quantity' => 500,
        ]);

        $source = $this->batch($user, $this->variety('Source'), 'B001', 100);

        $this->postRegraft($user, $source, 30, $variety)->assertSessionHas('success');

        $this->assertSame(100, $variety->fresh()->receivedScionQuantity());
        $this->assertSame(30, $variety->fresh()->usedScionQuantity());
        $this->assertSame(70, $variety->fresh()->availableScionQuantity());

        // The seedling receipt is untouched and never enters scion accounting.
        $this->assertSame(500, (int) $seedlings->fresh()->quantity);
        $this->assertSame(
            500,
            (int) StockIn::query()->whereNull('scion_variety_id')->sum('quantity'),
        );

        $source->refresh();
        $this->assertSame(70, (int) $source->available_count);
        $this->assertSame(30, (int) $source->regraft);
    }

    public function test_failed_validation_does_not_deduct_scion_stock(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Protected Scion']);
        $this->createScionStockIn($variety, $user, ['quantity' => 10]);

        $source = $this->batch($user, $this->variety('Source'), 'B001', 100);

        // A rejected date still fails before any stock is consumed.
        $this->postRegraft($user, $source, 5, $variety, now()->addDay()->toDateString())
            ->assertSessionHasErrors('regraft_date', null, 'regraft');

        $this->assertSame(10, $variety->fresh()->availableScionQuantity());
        $this->assertSame(0, $variety->fresh()->usedScionQuantity());

        // A quantity above the available scions is rejected too.
        $this->postRegraft($user, $source, 11, $variety)
            ->assertSessionHasErrors('quantity', null, 'regraft');

        $this->assertSame(10, $variety->fresh()->availableScionQuantity());
        $this->assertSame(0, $variety->fresh()->usedScionQuantity());

        $source->refresh();
        $this->assertSame(100, (int) $source->available_count);
        $this->assertSame(0, (int) $source->regraft);
        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_repeated_consumption_cannot_make_scion_stock_negative(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Exhausted Scion']);
        $this->createScionStockIn($variety, $user, ['quantity' => 10]);
        $source = $this->batch($user, $this->variety('Source'), 'B001', 100);

        (new RegraftBatch)->execute(
            $source,
            10,
            (int) $variety->scion_variety_id,
            now()->subDays(5)->toDateString(),
            null,
            (int) $user->id,
        );

        $this->assertSame(0, $variety->fresh()->availableScionQuantity());

        // A repeated call cannot consume the same scions twice.
        try {
            (new RegraftBatch)->execute(
                $source->fresh(),
                1,
                (int) $variety->scion_variety_id,
                now()->subDays(5)->toDateString(),
                null,
                (int) $user->id,
            );

            $this->fail('A fully consumed variety must not supply another regraft.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'No scions are currently available for this variety. Record new scion stock through Stock In before proceeding.',
                $exception->errors()['quantity'][0] ?? null,
            );
        }

        $this->assertSame(10, $variety->fresh()->receivedScionQuantity());
        $this->assertSame(10, $variety->fresh()->usedScionQuantity());
        $this->assertSame(0, $variety->fresh()->availableScionQuantity());
        $this->assertDatabaseCount('regraft_records', 1);
        $this->assertSame(1, Batch::where('parent_batch_id', $source->batch_id)->count());
    }
}
