<?php

namespace Tests\Feature;

use App\Actions\RegraftBatch;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScionStockIn;
use Tests\TestCase;

/**
 * Regraft page + request contract on the Phase 7B flow.
 *
 * Regraft and Regraft are the same recorded procedure. A regraft now moves a
 * quantity out of a source batch into a linked sub-batch via
 * {@see RegraftBatch} — the removed deficit-cap validation is gone.
 * The deeper transactional/atomicity/independence coverage lives in
 * {@see RegraftFlowTest}.
 */
class RegraftTest extends TestCase
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

    private function batch(User $user, ScionVariety $variety, int $total = 100): Batch
    {
        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => 'B001',
            'total_grafted' => $total,
            'regraft' => 0,
            'available_count' => $total,
            'grafted_date' => now()->subMonths(2)->toDateString(),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    public function test_guests_are_redirected_from_regraft_to_login(): void
    {
        $this->get(route('operational.regraft'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_regraft_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertSee('Regraft Status by Batch')
            ->assertSee('Regraft History');
    }

    public function test_user_can_record_a_regraft_into_a_sub_batch(): void
    {
        $user = User::factory()->create();
        $toledo = $this->variety('Toledo');
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => 20,
                'scion_variety_id' => $toledo->scion_variety_id,
                'regraft_date' => now()->subDays(3)->toDateString(),
                'remark' => 'Replaced affected seedlings.',
            ])
            ->assertRedirect(route('operational.regraft'))
            ->assertSessionHas('success');

        $sub = Batch::where('batch_code', 'B001-R1')->firstOrFail();
        $this->assertSame($source->batch_id, (int) $sub->parent_batch_id);
        $this->assertSame($toledo->scion_variety_id, (int) $sub->scion_variety_id);

        $this->assertDatabaseHas('regraft_records', [
            'batch_id' => $sub->batch_id,
            'parent_batch_id' => $source->batch_id,
            'quantity' => 20,
            'remark' => 'Replaced affected seedlings.',
        ]);
    }

    public function test_regraft_requires_a_positive_quantity(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)
            ->from(route('operational.regraft'))
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => 0,
                'scion_variety_id' => $source->scion_variety_id,
                'regraft_date' => now()->subDays(3)->toDateString(),
            ])
            ->assertSessionHasErrors('quantity', null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_regraft_requires_a_valid_batch_variety_and_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('operational.regraft'))
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => 999,
                'quantity' => 5,
                'scion_variety_id' => '',
                'regraft_date' => '',
            ])
            ->assertSessionHasErrors(['batch_id', 'scion_variety_id', 'regraft_date'], null, 'regraft');

        $this->assertDatabaseCount('regraft_records', 0);
    }

    public function test_regraft_remark_is_optional(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)->post(route('operational.regraft.records.store'), [
            'batch_id' => $source->batch_id,
            'quantity' => 5,
            'scion_variety_id' => $source->scion_variety_id,
            'regraft_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertDatabaseHas('regraft_records', [
            'quantity' => 5,
            'remark' => null,
        ]);
    }

    public function test_regraft_page_filters_by_batch(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety());

        $this->actingAs($user)
            ->get(route('operational.regraft', ['batch_id' => $source->batch_id]))
            ->assertOk()
            ->assertSee('B001');
    }

    public function test_batch_regraft_deficit_is_derived_from_the_counter(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety(), 100);

        MortalityRecord::create([
            'batch_id' => $source->batch_id,
            'user_id' => $user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => now()->subDays(10)->toDateString(),
        ]);

        $this->actingAs($user)->post(route('operational.regraft.records.store'), [
            'batch_id' => $source->batch_id,
            'quantity' => 15,
            'scion_variety_id' => $source->scion_variety_id,
            'regraft_date' => now()->subDays(3)->toDateString(),
        ]);

        $source->refresh();
        $this->assertSame(25, $source->effectiveMortalityQuantity());
        $this->assertSame(15, $source->totalRegrafted());
        $this->assertSame(10, $source->regraftDeficit());
        $this->assertSame(15, RegraftRecord::totalRegraftedFromBatch($source->batch_id));
    }

    public function test_only_active_scion_varieties_recorded_in_stock_in_are_listed(): void
    {
        $user = User::factory()->create();
        $received = $this->variety('Received Scion');
        $noReceipt = $this->variety('No Receipt Scion', false);
        $seedlingOnly = $this->variety('Seedling Only Scion', false);
        $archived = $this->variety('Archived Scion');
        $archived->update(['archived_at' => now()]);

        // A seedling-only Stock In has no scion variety and must add nothing.
        StockIn::factory()->create([
            'supplier_id' => null,
            'scion_variety_id' => null,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertViewHas('regraftScionVarieties', fn ($varieties) => $varieties
                ->pluck('scion_variety_id')->all() === [$received->scion_variety_id])
            ->assertSee('Replacement Scion Variety')
            ->assertSee('Select scion variety…')
            ->assertSee('Available scion quantity: —')
            ->assertSee('data-available="1000"', false)
            ->assertSee($received->scion_variety_name)
            ->assertDontSee($noReceipt->scion_variety_name)
            ->assertDontSee($seedlingOnly->scion_variety_name)
            ->assertDontSee($archived->scion_variety_name)
            // No variety is preselected; only an old() choice may restore one.
            ->assertDontSee('value="'.$received->scion_variety_id.'" selected', false);
    }

    public function test_a_zero_available_scion_variety_stays_listed_with_zero(): void
    {
        $user = User::factory()->create();
        $variety = ScionVariety::create(['scion_variety_name' => 'Used Up Scion']);
        $this->createScionStockIn($variety, $user, ['quantity' => 10]);

        $source = $this->batch($user, $this->variety('Source'), 50);

        (new RegraftBatch)->execute(
            $source,
            10,
            (int) $variety->scion_variety_id,
            now()->subDays(5)->toDateString(),
            null,
            (int) $user->id,
        );

        $this->assertSame(0, $variety->fresh()->availableScionQuantity());

        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertSee('Used Up Scion')
            ->assertSee('data-available="0"', false);
    }

    public function test_regraft_modal_shows_the_empty_state_and_disables_save_when_nothing_qualifies(): void
    {
        $user = User::factory()->create();
        $this->batch($user, $this->variety('Source', false));

        $this->actingAs($user)
            ->get(route('operational.regraft'))
            ->assertOk()
            ->assertSee('No scion has been recorded in Stock In yet. Record a Scion Stock In first.')
            ->assertSee('id="recordRegraftSubmitBtn" disabled', false)
            ->assertSee(route('inventory.stock-in'), false);
    }

    public function test_a_variety_without_a_scion_stock_in_is_rejected_and_changes_nothing(): void
    {
        $user = User::factory()->create();
        $source = $this->batch($user, $this->variety('Source'), 100);
        $notReceived = $this->variety('Not Received Scion', false);

        $this->actingAs($user)
            ->from(route('operational.regraft'))
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $source->batch_id,
                'quantity' => 10,
                'scion_variety_id' => $notReceived->scion_variety_id,
                'regraft_date' => now()->subDays(3)->toDateString(),
            ])
            ->assertSessionHasErrors('scion_variety_id', null, 'regraft');

        $this->assertSame(
            'Select a scion variety that has been recorded in Stock In.',
            session('errors')->getBag('regraft')->first('scion_variety_id'),
        );

        $source->refresh();
        $this->assertSame(100, (int) $source->available_count);
        $this->assertSame(0, (int) $source->regraft);
        $this->assertDatabaseCount('batches', 1);
        $this->assertDatabaseCount('regraft_records', 0);
        $this->assertSame(0, ActivityLog::count());
    }
}
