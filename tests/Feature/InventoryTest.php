<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScionStockIn;
use Tests\TestCase;

/**
 * Inventory listing (revised ERD §3.12 / PRD FR-08): the owner-directed
 * per-batch template shows the live counts derived from actual Batch,
 * Mortality and Regraft records, plus the derived lifecycle Status column
 * (Growing < 21 days / Ready ≥ 21 days / Completed = no effective surviving
 * quantity (available net of that batch's own mortality) / Cancelled-Void =
 * invalidated record — `Batch::lifecycleStatus()`).
 * The row action is `[View] [More ▾]`: View opens a read-only Batch Details
 * modal (including the recorded mortality causes behind the batch's mortality
 * figure, grouped from the real Mortality_Record rows); More groups the record
 * actions — Regraft and Record Mortality (the same
 * forms as the operational pages, in modals hosted on this page, prefilled with
 * the clicked batch, posting to the shared routes and validating through the
 * shared Form Requests). The unconfirmed Regraft Deficit column/card/review
 * were removed (owner-directed; no client-approved definition).
 * The "Record Monthly Inventory" page action was removed. The backend snapshot
 * writer is kept and still stores one snapshot per batch per month with
 * `status` frozen at creation from the live `available_count`.
 *
 * The summary cards (owner-directed) aggregate the same live per-batch values
 * over the filtered set: Total Mortality, Total Regrafted,
 * Total Active Inventory (active batches only) and Total Batches. The
 * AVAILABLE column is the remaining plant quantity — the live
 * `available_count` net of that batch's own effective mortality through the
 * centralized `Batch::survivingQuantity()`, because mortality records never
 * mutate the stored balance.
 */
class InventoryTest extends TestCase
{
    use CreatesScionStockIn;
    use RefreshDatabase;

    private function createBatch(
        User $user,
        string $code = 'B001',
        int $totalGrafted = 100,
        int $available = 100,
        string $varietyName = 'Criollo',
        string $graftedDate = '2026-07-15',
        bool $receivedInStockIn = true,
    ): Batch {
        $variety = ScionVariety::create(['scion_variety_name' => $varietyName]);

        if ($receivedInStockIn) {
            $this->createScionStockIn($variety, $user);
        }

        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'batch_code' => $code,
            'total_grafted' => $totalGrafted,
            'regraft' => 0,
            'available_count' => $available,
            'grafted_date' => $graftedDate,
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
    }

    private function createSnapshot(Batch $batch, int $available, string $date = '2026-09-01'): Inventory
    {
        return Inventory::create([
            'batch_id' => $batch->batch_id,
            'inventory_date' => $date,
            'total_grafted' => (int) $batch->total_grafted,
            'regraft' => $batch->totalRegrafted(),
            'mortality' => $batch->effectiveMortalityQuantity(),
            'last_update' => now(),
        ]);
    }

    public function test_guests_are_redirected_from_inventory_to_login(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_inventory_page(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 120, 120);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Batch Inventory')
            ->assertSee('BATCH DETAILS')
            ->assertSee('STATUS')
            ->assertSee('Jul 15, 2026')
            ->assertDontSee('Date: Jul 15, 2026')
            ->assertSee('AVAILABLE')
            ->assertDontSee('DEFICIT')
            ->assertDontSee('Record Monthly Inventory')
            ->assertDontSee('Active Snapshots')
            ->assertDontSee('Depleted Snapshots')
            ->assertSee('Total Mortality')
            ->assertDontSee('Regraft Deficit')
            ->assertSee('Total Regrafted')
            ->assertSee('Total Active Inventory')
            ->assertSee('Total Batches')
            ->assertSee('B001');
    }

    public function test_inventory_filters_apply_automatically_without_an_apply_button(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('id="inventoryFilterForm"', false)
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="filterSearch"', false)
            ->assertSee('Clear Filters')
            ->assertDontSee('bi-funnel')
            ->assertDontSee('Apply Filters');
    }

    public function test_inventory_page_shows_real_database_values(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B007', 250, 198);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('B007')
            ->assertSee('Batch Inventory')
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_code === 'B007'
                && (int) $batches->first()->total_grafted === 250);
    }

    public function test_inventory_page_lists_the_newest_batch_first(): void
    {
        $user = User::factory()->create();

        $oldest = $this->createBatch($user, 'B001', 100, 100, 'Criollo', '2026-07-15');
        Batch::create([
            'scion_variety_id' => $oldest->scion_variety_id,
            'parent_batch_id' => $oldest->batch_id,
            'user_id' => $user->id,
            'batch_code' => 'B001-R1',
            'total_grafted' => 20,
            'regraft' => 0,
            'available_count' => 20,
            'grafted_date' => '2026-09-05',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
        $this->createBatch($user, 'B002', 100, 100, 'Trinitario', '2026-08-01');

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('batches', function ($batches) {
                // Newest root family first; a sub-batch always stays nested
                // directly under its parent, whatever the root direction.
                return collect($batches->items())->map(fn (Batch $batch) => $batch->batch_code)->all()
                    === ['B002', 'B001', 'B001-R1'];
            });
    }

    public function test_inventory_page_toggles_nested_regraft_sub_batches_like_the_batches_page(): void
    {
        $user = User::factory()->create();
        $parent = $this->createBatch($user, 'B001', 100, 60, 'Criollo', '2026-07-15');

        $sub = Batch::create([
            'scion_variety_id' => $parent->scion_variety_id,
            'parent_batch_id' => $parent->batch_id,
            'user_id' => $user->id,
            'batch_code' => 'B001-R1',
            'total_grafted' => 20,
            'regraft' => 0,
            'available_count' => 20,
            'grafted_date' => '2026-09-05',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);

        $grand = Batch::create([
            'scion_variety_id' => $parent->scion_variety_id,
            'parent_batch_id' => $sub->batch_id,
            'user_id' => $user->id,
            'batch_code' => 'B001-R1-R1',
            'total_grafted' => 10,
            'regraft' => 0,
            'available_count' => 10,
            'grafted_date' => '2026-09-10',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('title="Toggle sub-batches"', false)
            ->assertSee('data-bs-target=".child-of-'.$parent->batch_id.'"', false)
            ->assertSee('data-bs-target=".child-of-'.$sub->batch_id.'"', false)
            // A parent/child toggle collapses its whole subtree through the
            // ancestor classes on each nested row (same markup as Batches).
            ->assertSee('collapse show child-of-'.$parent->batch_id, false)
            ->assertSee('collapse show child-of-'.$parent->batch_id.' child-of-'.$sub->batch_id, false);

        $response->assertViewHas('nodeMeta', function (array $meta) use ($parent, $sub, $grand) {
            return $meta[$parent->batch_id]['has_children'] === true
                && $meta[$sub->batch_id]['has_children'] === true
                && $meta[$sub->batch_id]['ancestors'] === [$parent->batch_id]
                && $meta[$grand->batch_id]['has_children'] === false
                && $meta[$grand->batch_id]['ancestors'] === [$parent->batch_id, $sub->batch_id];
        });
    }

    public function test_inventory_summary_cards_show_the_filtered_live_totals(): void
    {
        $user = User::factory()->create();

        $batched = $this->createBatch($user, 'B001', 100, 60);
        MortalityRecord::create([
            'batch_id' => $batched->batch_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);
        RegraftRecord::create([
            'batch_id' => $batched->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'regraft_date' => '2026-09-03',
        ]);
        $batched->update(['regraft' => 10]);

        $this->createBatch($user, 'B002', 50, 50, 'Trinitario');

        $archived = $this->createBatch($user, 'B003', 40, 40, 'Forastero');
        $archived->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('totalMortality', 30)
            ->assertViewHas('totalRegrafted', 10)
            // B001: 60 live balance − 30 mortality = 30 remaining; B002: 50.
            // Archived batches leave the "active" inventory card but stay in the
            // listed batch count.
            ->assertViewHas('totalActiveInventory', 80)
            ->assertViewHas('totalBatches', 3)
            ->assertSee('Total plants recorded as dead')
            ->assertSee('Total plants regrafted')
            ->assertSee('Plants currently available')
            ->assertSee('Total number of batches');
    }

    public function test_inventory_summary_cards_follow_the_current_filters(): void
    {
        $user = User::factory()->create();

        $first = $this->createBatch($user, 'B001', 100, 60);
        MortalityRecord::create([
            'batch_id' => $first->batch_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);
        RegraftRecord::create([
            'batch_id' => $first->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'regraft_date' => '2026-09-03',
        ]);
        $first->update(['regraft' => 10]);

        $this->createBatch($user, 'B002', 50, 50, 'Trinitario');

        $this->actingAs($user)
            ->get(route('inventory.index', ['search' => 'B001']))
            ->assertOk()
            ->assertViewHas('totalMortality', 30)
            ->assertViewHas('totalRegrafted', 10)
            // B001: 60 live balance − 30 effective mortality = 30 remaining.
            ->assertViewHas('totalActiveInventory', 30)
            ->assertViewHas('totalBatches', 1);
    }

    public function test_inventory_table_shows_dates_under_batch_details_total_grafted_and_regraft(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100, 'Criollo', '2026-07-15');

        $this->actingAs($user)
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 20,
                'scion_variety_id' => $batch->scion_variety_id,
                'regraft_date' => '2026-09-10',
            ])
            ->assertRedirect(route('operational.regraft'));

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('BATCH DETAILS')
            ->assertSeeInOrder(['B001', 'Jul 15, 2026'])
            ->assertDontSee('Date: Jul 15, 2026')
            ->assertSee('Sep 10, 2026')
            ->assertViewHas('summaries', function ($summaries) use ($batch) {
                $summary = $summaries[$batch->batch_id] ?? null;

                return $summary !== null
                    && $summary['regraft'] === 20
                    && $summary['latest_regraft_date'] === '2026-09-10';
            });
    }

    public function test_inventory_template_shows_the_live_values(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 60);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        RegraftRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'regraft_date' => '2026-09-03',
        ]);

        $batch->update(['regraft' => 10]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', function ($summaries) use ($batch) {
                $summary = $summaries[$batch->batch_id] ?? null;

                return $summary !== null
                    && $summary['total_grafted'] === 100
                    && $summary['regraft'] === 10
                    && $summary['mortality'] === 30
                    // The raw live balance (60) remains unchanged — mortality
                    // is deducted only for the displayed remaining quantity.
                    && (int) $batch->fresh()->available_count === 60
                    && $summary['available'] === 30
                    && ! array_key_exists('deficit', $summary);
            });
    }

    public function test_inventory_available_deducts_recorded_mortality_from_the_live_balance(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B005', 50, 50);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 20,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', function ($summaries) use ($batch) {
                $summary = $summaries[$batch->batch_id] ?? null;

                return $summary !== null
                    && $summary['total_grafted'] === 50
                    && $summary['regraft'] === 0
                    && $summary['mortality'] === 20
                    && $summary['available'] === 30
                    // Mortality never mutates the stored live balance.
                    && (int) $batch->fresh()->available_count === 50;
            })
            ->assertViewHas('totalActiveInventory', 30);
    }

    public function test_inventory_available_equals_the_live_balance_when_mortality_is_zero(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B005', 50, 50);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries[$batch->batch_id]['mortality'] === 0
                && $summaries[$batch->batch_id]['available'] === 50)
            ->assertViewHas('totalActiveInventory', 50);
    }

    public function test_inventory_available_deducts_multiple_mortality_records_once(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B005', 100, 100);

        foreach ([5, 7, 3] as $quantity) {
            MortalityRecord::create([
                'batch_id' => $batch->batch_id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'cause' => 'Disease',
                'recorded_at' => '2026-09-01',
            ]);
        }

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries[$batch->batch_id]['mortality'] === 15
                && $summaries[$batch->batch_id]['available'] === 85)
            ->assertViewHas('totalActiveInventory', 85);
    }

    public function test_inventory_available_uses_each_batch_own_mortality_without_double_counting_the_family(): void
    {
        $user = User::factory()->create();
        $parent = $this->createBatch($user, 'B001', 100, 100);

        $sub = Batch::create([
            'scion_variety_id' => $parent->scion_variety_id,
            'parent_batch_id' => $parent->batch_id,
            'user_id' => $user->id,
            'batch_code' => 'B001-R1',
            'total_grafted' => 50,
            'regraft' => 0,
            'available_count' => 50,
            'grafted_date' => '2026-09-05',
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);

        foreach ([[$parent, 20], [$sub, 5]] as [$batch, $quantity]) {
            MortalityRecord::create([
                'batch_id' => $batch->batch_id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'cause' => 'Disease',
                'recorded_at' => '2026-09-01',
            ]);
        }

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', function ($summaries) use ($parent, $sub) {
                return $summaries[$parent->batch_id]['mortality'] === 20
                    && $summaries[$parent->batch_id]['available'] === 80
                    && $summaries[$sub->batch_id]['mortality'] === 5
                    && $summaries[$sub->batch_id]['available'] === 45;
            })
            // 80 + 45: each batch's own mortality is deducted exactly once.
            ->assertViewHas('totalActiveInventory', 125);
    }

    public function test_inventory_available_reflects_a_corrected_mortality_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = $this->createBatch($admin, 'B005', 50, 50);

        $record = MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $admin->id,
            'quantity' => 20,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries[$batch->batch_id]['available'] === 30);

        $this->actingAs($admin)
            ->post(route('corrections.mortality', $record), [
                'quantity' => 15,
                'cause' => 'Pest',
                'recorded_at' => '2026-09-01',
            ])
            ->assertRedirect(route('operational.mortality'))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries[$batch->batch_id]['mortality'] === 15
                && $summaries[$batch->batch_id]['available'] === 35);
    }

    public function test_inventory_page_no_longer_shows_the_unconfirmed_deficit(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 60);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        RegraftRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'regraft_date' => '2026-09-03',
        ]);

        $batch->update(['regraft' => 10]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            // No DEFICIT column/card, no review action/modal and no deficit
            // value in the row payload (owner-directed removal; the definition
            // was never client-confirmed).
            ->assertDontSee('DEFICIT')
            ->assertDontSee('Regraft Deficit')
            ->assertDontSee('Record Deficit')
            ->assertDontSee('data-bs-target="#deficitModal"', false)
            ->assertDontSee('&quot;deficit&quot;', false);
    }

    public function test_inventory_view_modal_is_available_without_record_operations_permission(): void
    {
        $user = User::factory()->create(['permissions' => ['manage_inventory']]);
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('data-bs-target="#viewBatchModal"', false)
            ->assertDontSee('data-bs-target="#deficitModal"', false)
            ->assertDontSee('Record Deficit')
            ->assertDontSee('data-bs-target="#recordMortalityModal"', false)
            ->assertDontSee('data-bs-target="#recordRegraftModal"', false)
            ->assertDontSee('id="invMortalityForm"', false)
            ->assertDontSee('id="invRegraftForm"', false);
    }

    public function test_inventory_view_modal_is_available_for_an_archived_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);
        $batch->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Archived')
            ->assertSee('data-bs-target="#viewBatchModal"', false)
            ->assertDontSee('data-bs-target="#deficitModal"', false)
            ->assertDontSee('Record Deficit')
            ->assertDontSee('data-bs-target="#recordMortalityModal"', false)
            ->assertDontSee('data-bs-target="#recordRegraftModal"', false);
    }

    public function test_inventory_page_exposes_the_add_batch_modal_and_form(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('data-bs-target="#addBatchModal"', false)
            ->assertSee('Add Batch')
            ->assertSee(route('operational.batches.manage.store'), false)
            ->assertSee('id="addBatchForm"', false)
            ->assertSee('name="scion_variety_id"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="total_grafted"', false)
            // Batch Code and Grafted Date are system-assigned, not typed.
            ->assertDontSee('name="batch_code"', false)
            ->assertDontSee('name="grafted_date"', false)
            ->assertSee('Assigned automatically when the batch is saved.')
            ->assertSee('Set automatically to today when the batch is saved.')
            ->assertSee('value="B002"', false);
    }

    public function test_inventory_add_batch_action_is_hidden_without_record_operations_permission(): void
    {
        $user = User::factory()->create(['permissions' => ['manage_inventory']]);
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertDontSee('data-bs-target="#addBatchModal"', false)
            ->assertDontSee(route('operational.batches.manage.store'), false);
    }

    public function test_add_batch_submitted_from_inventory_validates_through_the_batch_form_request(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->from(route('inventory.index'))
            ->post(route('operational.batches.manage.store'), [
                'total_grafted' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors(['total_grafted'], null, 'batch');

        $this->assertDatabaseCount('batches', 1);
    }

    public function test_add_batch_submitted_from_inventory_auto_assigns_the_code_and_grafted_date(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->from(route('inventory.index'))
            ->post(route('operational.batches.manage.store'), [
                // Client-supplied values are ignored: the system owns both
                // fields (next free code / today's date).
                'batch_code' => 'HACK-999',
                'grafted_date' => now()->addDay()->toDateString(),
                'total_grafted' => 50,
                'status' => Batch::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('operational.batches'))
            ->assertSessionHas('success');

        $batch = Batch::where('batch_code', 'B002')->first();
        $this->assertNotNull($batch);
        $this->assertSame(now()->toDateString(), $batch->grafted_date->format('Y-m-d'));
        $this->assertSame(50, (int) $batch->available_count);
        $this->assertDatabaseMissing('batches', ['batch_code' => 'HACK-999']);
    }

    public function test_inventory_row_action_opens_the_mortality_and_regraft_modals(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('ACTION')
            ->assertSee('Mortality')
            ->assertSee('Regraft')
            ->assertSee('data-bs-target="#recordMortalityModal"', false)
            ->assertSee('data-bs-target="#recordRegraftModal"', false)
            ->assertSee('data-batch-id="'.$batch->batch_id.'"', false)
            ->assertSee(route('operational.mortality.records.store'), false)
            ->assertSee(route('operational.regraft.records.store'), false)
            ->assertDontSee(route('operational.mortality', ['batch_id' => $batch->batch_id]), false)
            ->assertDontSee(route('operational.regraft', ['batch_id' => $batch->batch_id]), false)
            ->assertDontSee('Record Monthly Inventory')
            ->assertDontSee(route('inventory.snapshots.store'), false);
    }

    public function test_inventory_page_exposes_the_mortality_and_regraft_modal_forms(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('id="invMortalityForm"', false)
            ->assertSee('id="invRegraftForm"', false)
            ->assertSee('id="invMortalityBatch"', false)
            ->assertSee('id="invRegraftBatch"', false)
            ->assertSee('name="cause"', false)
            ->assertSee('name="cause_other"', false)
            ->assertSee('name="recorded_at"', false)
            ->assertSee('name="regraft_date"', false)
            ->assertSee('name="scion_variety_id"', false)
            ->assertSee('name="remark"', false)
            ->assertSee('Original: 100 | Stock Out: 0 | Remaining: 100')
            ->assertSee('B001 (Criollo) — 100 regraftable');
    }

    public function test_inventory_regraft_modal_lists_only_scion_varieties_recorded_in_stock_in(): void
    {
        $user = User::factory()->create();

        $received = $this->createBatch($user, 'B001', 100, 100, 'Received Scion');
        $noReceipt = $this->createBatch($user, 'B002', 100, 100, 'No Receipt Scion', receivedInStockIn: false);
        $seedlingOnly = ScionVariety::create(['scion_variety_name' => 'Seedling Only Scion']);
        $archived = $this->createBatch($user, 'B003', 100, 100, 'Archived Scion');

        // A seedling-only Stock In leaves `scion_variety_id` null, so it cannot
        // make a variety available for regrafting.
        StockIn::factory()->create([
            'supplier_id' => null,
            'scion_variety_id' => null,
            'user_id' => $user->id,
        ]);

        $archived->scionVariety->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('regraftScionVarieties', fn ($varieties) => $varieties
                ->pluck('scion_variety_id')->all() === [$received->scion_variety_id])
            // The Add Batch selector is unchanged: it still offers every active
            // variety, not only the ones recorded in Stock In.
            ->assertViewHas('scionVarieties', fn ($varieties) => $varieties
                ->pluck('scion_variety_id')
                ->contains($noReceipt->scion_variety_id)
                && $varieties->pluck('scion_variety_id')->contains($seedlingOnly->scion_variety_id))
            ->assertSee('Replacement Scion Variety')
            ->assertSee('Select scion variety…')
            ->assertSee('Available scion quantity: —')
            ->assertSee('data-available="1000"', false)
            ->assertDontSee('value="'.$received->scion_variety_id.'" selected', false);
    }

    public function test_inventory_regraft_modal_shows_the_empty_state_and_disables_save_when_nothing_qualifies(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100, 'No Receipt Scion', receivedInStockIn: false);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('No scion has been recorded in Stock In yet. Record a Scion Stock In first.')
            ->assertSee('id="invRegraftSubmitBtn" disabled', false)
            ->assertSee(route('inventory.stock-in'), false);
    }

    public function test_inventory_row_action_is_hidden_without_record_operations_permission(): void
    {
        $user = User::factory()->create(['permissions' => ['manage_inventory']]);
        $batch = $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertDontSee('data-bs-target="#recordMortalityModal"', false)
            ->assertDontSee('data-bs-target="#recordRegraftModal"', false)
            ->assertDontSee('id="invMortalityForm"', false)
            ->assertDontSee('id="invRegraftForm"', false)
            ->assertDontSee(route('operational.mortality.records.store'), false)
            ->assertDontSee(route('operational.regraft.records.store'), false);
    }

    public function test_inventory_row_action_is_suppressed_for_an_archived_batch(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);
        $batch->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Archived')
            ->assertDontSee('data-bs-target="#recordMortalityModal"', false)
            ->assertDontSee('data-bs-target="#recordRegraftModal"', false);
    }

    public function test_mortality_submitted_from_inventory_validates_through_the_shared_form_request(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->from(route('inventory.index'))
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 0,
                'cause' => 'Disease',
                'recorded_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors(['quantity'], null, 'mortality');

        $this->assertDatabaseCount('mortality_records', 0);
    }

    public function test_regraft_submitted_from_inventory_validates_through_the_shared_form_request(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->from(route('inventory.index'))
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 0,
                'scion_variety_id' => $batch->scion_variety_id,
                'regraft_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors(['quantity'], null, 'regraft');

        $this->assertDatabaseCount('batches', 1);
    }

    public function test_mortality_can_be_recorded_from_inventory_through_the_shared_flow(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->post(route('operational.mortality.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 5,
                'cause' => 'Disease',
                'recorded_at' => '2026-09-01',
            ])
            ->assertRedirect(route('operational.mortality'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mortality_records', [
            'batch_id' => $batch->batch_id,
            'quantity' => 5,
            'cause' => 'Disease',
        ]);
    }

    public function test_regraft_can_be_recorded_from_inventory_through_the_shared_flow(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->post(route('operational.regraft.records.store'), [
                'batch_id' => $batch->batch_id,
                'quantity' => 20,
                'scion_variety_id' => $batch->scion_variety_id,
                'regraft_date' => '2026-09-10',
            ])
            ->assertRedirect(route('operational.regraft'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('batches', [
            'batch_code' => 'B001-R1',
            'parent_batch_id' => $batch->batch_id,
            'total_grafted' => 20,
            'available_count' => 20,
        ]);
        $this->assertSame(80, (int) $batch->fresh()->available_count);
    }

    public function test_inventory_page_filters_by_batch_code(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100);
        $this->createBatch($user, 'B002', 100, 100, 'Trinitario');

        // Assert the filtered dataset, not raw HTML: the filter dropdown also
        // lists every scion variety.
        $this->actingAs($user)
            ->get(route('inventory.index', ['search' => 'B002']))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_code === 'B002');
    }

    public function test_inventory_page_filters_by_lifecycle_status(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 40);
        $this->createBatch($user, 'B002', 100, 0, 'Trinitario');

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => Batch::LIFECYCLE_COMPLETED]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_code === 'B002'
                && (int) $batches->first()->available_count === 0);
    }

    public function test_inventory_status_column_shows_the_derived_lifecycle_status(): void
    {
        $user = User::factory()->create();
        $growing = $this->createBatch($user, 'B001', 100, 100, 'Criollo', now()->subDays(20)->toDateString());
        $ready = $this->createBatch($user, 'B002', 100, 100, 'Trinitario', now()->subDays(21)->toDateString());
        $completed = $this->createBatch($user, 'B003', 100, 0, 'Forastero', now()->subDays(30)->toDateString());
        $cancelled = $this->createBatch($user, 'B004', 100, 100, 'Batangas', now()->subDays(30)->toDateString());
        $cancelled->update(['status' => Batch::STATUS_INACTIVE]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', function ($summaries) use ($growing, $ready, $completed, $cancelled) {
                return $summaries[$growing->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_GROWING
                    && $summaries[$ready->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_READY
                    && $summaries[$completed->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_COMPLETED
                    && $summaries[$cancelled->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_CANCELLED;
            })
            ->assertSee('&quot;lifecycle_status&quot;:&quot;growing&quot;', false)
            ->assertSee('&quot;lifecycle_status&quot;:&quot;ready&quot;', false)
            ->assertSee('&quot;lifecycle_status&quot;:&quot;completed&quot;', false)
            ->assertSee('&quot;lifecycle_status&quot;:&quot;cancelled&quot;', false)
            ->assertSee('badge-lifecycle-completed', false);
    }

    public function test_inventory_page_filters_by_the_derived_lifecycle_status(): void
    {
        $user = User::factory()->create();
        $growing = $this->createBatch($user, 'B001', 100, 100, 'Criollo', now()->subDays(20)->toDateString());
        $ready = $this->createBatch($user, 'B002', 100, 100, 'Trinitario', now()->subDays(21)->toDateString());
        $completed = $this->createBatch($user, 'B003', 100, 0, 'Forastero', now()->subDays(30)->toDateString());
        $cancelled = $this->createBatch($user, 'B004', 100, 100, 'Batangas', now()->subDays(30)->toDateString());
        $cancelled->update(['archived_at' => now()]);

        foreach ([
            Batch::LIFECYCLE_GROWING => $growing,
            Batch::LIFECYCLE_READY => $ready,
            Batch::LIFECYCLE_COMPLETED => $completed,
            Batch::LIFECYCLE_CANCELLED => $cancelled,
        ] as $status => $expected) {
            $this->actingAs($user)
                ->get(route('inventory.index', ['status' => $status]))
                ->assertOk()
                ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                    && $batches->first()->batch_id === $expected->batch_id);
        }
    }

    public function test_inventory_lifecycle_status_uses_the_effective_surviving_quantity(): void
    {
        $user = User::factory()->create();
        $readyDate = now()->subDays(Batch::CYCLE_DAYS)->toDateString();
        $growingDate = now()->subDays(Batch::CYCLE_DAYS - 1)->toDateString();

        $noMortality = $this->createBatch($user, 'B001', 100, 100, 'Criollo', $readyDate, false);
        $partial = $this->createBatch($user, 'B002', 100, 100, 'Trinitario', $growingDate, false);
        $exhausted = $this->createBatch($user, 'B003', 100, 100, 'Forastero', $readyDate, false);

        MortalityRecord::create([
            'batch_id' => $partial->batch_id,
            'user_id' => $user->id,
            'quantity' => 40,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        MortalityRecord::create([
            'batch_id' => $exhausted->batch_id,
            'user_id' => $user->id,
            'quantity' => 100,
            'cause' => 'Pest',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', function ($summaries) use ($noMortality, $partial, $exhausted) {
                return
                    // No mortality: the stored balance is the surviving quantity.
                    $summaries[$noMortality->batch_id]['available'] === 100
                    && $summaries[$noMortality->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_READY
                    // Partial mortality: still alive, but the displayed and judged
                    // quantity is 100 − 40 = 60.
                    && $summaries[$partial->batch_id]['available'] === 60
                    && $summaries[$partial->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_GROWING
                    // Mortality equals the remaining quantity: zero surviving
                    // plants, so the batch is Completed even though the stored
                    // `available_count` is still 100.
                    && $summaries[$exhausted->batch_id]['available'] === 0
                    && $summaries[$exhausted->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_COMPLETED
                    && (int) $exhausted->fresh()->available_count === 100;
            });
    }

    public function test_inventory_completed_filter_uses_the_effective_surviving_quantity(): void
    {
        $user = User::factory()->create();
        $readyDate = now()->subDays(Batch::CYCLE_DAYS)->toDateString();

        $exhausted = $this->createBatch($user, 'B001', 100, 100, 'Criollo', $readyDate, false);
        $partial = $this->createBatch($user, 'B002', 100, 100, 'Trinitario', $readyDate, false);

        MortalityRecord::create([
            'batch_id' => $exhausted->batch_id,
            'user_id' => $user->id,
            'quantity' => 100,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        MortalityRecord::create([
            'batch_id' => $partial->batch_id,
            'user_id' => $user->id,
            'quantity' => 40,
            'cause' => 'Pest',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => Batch::LIFECYCLE_COMPLETED]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_id === $exhausted->batch_id);

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => Batch::LIFECYCLE_READY]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_id === $partial->batch_id);
    }

    public function test_parent_with_regrafted_sub_batches_is_judged_on_its_own_surviving_quantity(): void
    {
        $user = User::factory()->create();
        $readyDate = now()->subDays(Batch::CYCLE_DAYS)->toDateString();

        // Parent: 100 grafted, 40 regrafted out (available 60), then 60 death.
        // Its own effective surviving quantity is 0 and it does not inherit the
        // sub-batch's population.
        $parent = $this->createBatch($user, 'B001', 100, 60, 'Criollo', $readyDate, false);
        $parent->update(['regraft' => 40]);

        MortalityRecord::create([
            'batch_id' => $parent->batch_id,
            'user_id' => $user->id,
            'quantity' => 60,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $sub = Batch::create([
            'scion_variety_id' => $parent->scion_variety_id,
            'parent_batch_id' => $parent->batch_id,
            'user_id' => $user->id,
            'batch_code' => 'B001-R1',
            'total_grafted' => 40,
            'regraft' => 0,
            'available_count' => 40,
            'grafted_date' => now()->subDays(5)->toDateString(),
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries[$parent->batch_id]['available'] === 0
                && $summaries[$parent->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_COMPLETED
                && $summaries[$sub->batch_id]['available'] === 40
                && $summaries[$sub->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_GROWING);

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => Batch::LIFECYCLE_COMPLETED]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_id === $parent->batch_id);

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => Batch::LIFECYCLE_GROWING]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_id === $sub->batch_id);
    }

    public function test_corrected_mortality_restores_a_completed_batch_and_its_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = $this->createBatch(
            $admin,
            'B001',
            100,
            100,
            'Criollo',
            now()->subDays(Batch::CYCLE_DAYS)->toDateString(),
            false,
        );

        $record = MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $admin->id,
            'quantity' => 100,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries[$batch->batch_id]['available'] === 0
                && $summaries[$batch->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_COMPLETED);

        $this->actingAs($admin)
            ->post(route('corrections.mortality', $record), [
                'quantity' => 40,
                'cause' => 'Pest',
                'recorded_at' => '2026-09-01',
            ])
            ->assertRedirect(route('operational.mortality'))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries[$batch->batch_id]['available'] === 60
                && $summaries[$batch->batch_id]['lifecycle_status'] === Batch::LIFECYCLE_READY);

        $this->actingAs($admin)
            ->get(route('inventory.index', ['status' => Batch::LIFECYCLE_COMPLETED]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 0);

        $this->actingAs($admin)
            ->get(route('inventory.index', ['status' => Batch::LIFECYCLE_READY]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_id === $batch->batch_id);
    }

    public function test_inventory_view_action_exposes_the_read_only_batch_details_modal(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 85, 'Criollo', '2026-07-15');

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('data-bs-target="#viewBatchModal"', false)
            ->assertSee('id="viewBatchModal"', false)
            ->assertSee('Batch Details:')
            ->assertSee('data-details=', false)
            ->assertSee('&quot;lifecycle_label&quot;:&quot;Ready&quot;', false)
            ->assertSee('&quot;lifecycle_class&quot;:&quot;bg-success&quot;', false)
            ->assertSee('&quot;surviving&quot;:85', false)
            // The View modal hosts the mortality-cause breakdown; a batch with
            // no recorded deaths carries an empty cause list and the empty note.
            ->assertSee('Mortality Causes')
            ->assertSee('No mortality recorded for this batch.')
            ->assertSee('id="viewMortalityCausesBody"', false)
            ->assertSee('&quot;mortality_causes&quot;:[]', false);
    }

    public function test_inventory_view_modal_shows_the_recorded_mortality_causes(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 62);

        foreach ([
            ['quantity' => 30, 'cause' => 'Disease', 'recorded_at' => '2026-09-01'],
            ['quantity' => 5, 'cause' => 'Pest', 'recorded_at' => '2026-09-05'],
            [
                'quantity' => 3,
                'cause' => MortalityRecord::composeCause(MortalityRecord::CAUSE_OTHERS, 'Rat damage'),
                'recorded_at' => '2026-09-06',
            ],
        ] as $record) {
            MortalityRecord::create([
                'batch_id' => $batch->batch_id,
                'user_id' => $user->id,
                'quantity' => $record['quantity'],
                'cause' => $record['cause'],
                'recorded_at' => $record['recorded_at'],
            ]);
        }

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            // The breakdown is grouped from the real records, ordered by
            // quantity, and preserves the recorded `Others: <description>`
            // specification (record-level detail, not the analytics roll-up).
            ->assertViewHas('summaries', function ($summaries) use ($batch) {
                return $summaries[$batch->batch_id]['mortality'] === 38
                    && $summaries[$batch->batch_id]['mortality_causes'] === [
                        ['cause' => 'Disease', 'quantity' => 30],
                        ['cause' => 'Pest', 'quantity' => 5],
                        ['cause' => 'Others: Rat damage', 'quantity' => 3],
                    ];
            })
            ->assertSee('Mortality Causes')
            ->assertSee('Grouped from this batch\'s recorded Mortality entries.', false)
            ->assertSee('&quot;cause&quot;:&quot;Disease&quot;,&quot;quantity&quot;:30', false)
            ->assertSee('&quot;cause&quot;:&quot;Pest&quot;,&quot;quantity&quot;:5', false)
            ->assertSee('&quot;cause&quot;:&quot;Others: Rat damage&quot;,&quot;quantity&quot;:3', false);
    }

    public function test_inventory_action_groups_regraft_and_mortality_under_more(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100);

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('dropdown-toggle', false)
            ->assertSee('More')
            ->assertSee('Record Mortality')
            ->assertSee('data-bs-target="#recordRegraftModal"', false)
            ->assertDontSee('Record Deficit');
    }

    public function test_inventory_page_filters_by_scion_variety(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100, 'Criollo');
        $forastero = $this->createBatch($user, 'B002', 100, 100, 'Forastero');

        $this->actingAs($user)
            ->get(route('inventory.index', ['scion_variety_id' => $forastero->scion_variety_id]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_code === 'B002');
    }

    public function test_inventory_page_filters_by_grafted_month_and_year(): void
    {
        $user = User::factory()->create();
        $this->createBatch($user, 'B001', 100, 100, 'Criollo', '2026-07-15');
        $this->createBatch($user, 'B002', 100, 100, 'Trinitario', '2026-09-10');

        $this->actingAs($user)
            ->get(route('inventory.index', ['month' => 9, 'year' => 2026]))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->total() === 1
                && $batches->first()->batch_code === 'B002');
    }

    public function test_inventory_page_rejects_invalid_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.index', ['scion_variety_id' => 999, 'status' => 'bogus']))
            ->assertSessionHasErrors(['scion_variety_id', 'status']);
    }

    public function test_inventory_page_shows_empty_state_when_no_records(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('No batches found for the current filters.');
    }

    public function test_record_monthly_inventory_derives_counts_server_side(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 180, 150);

        MortalityRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 20,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        RegraftRecord::create([
            'batch_id' => $batch->batch_id,
            'user_id' => $user->id,
            'quantity' => 10,
            'regraft_date' => '2026-09-03',
        ]);

        // Phase 7B: the snapshot's `regraft` reads the batch's cached counter.
        $batch->update(['regraft' => 10]);

        $this->actingAs($user)
            ->post(route('inventory.snapshots.store'), [
                'batch_id' => $batch->batch_id,
                'inventory_date' => '2026-09-15',
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batch->batch_id,
            'total_grafted' => 180,
            'regraft' => 10,
            'mortality' => 20,
            'status' => Inventory::STATUS_ACTIVE,
        ]);
    }

    public function test_record_monthly_inventory_rejects_a_second_snapshot_in_the_same_month(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 180, 150);
        $this->createSnapshot($batch, 150, '2026-09-01');

        $this->actingAs($user)
            ->from(route('inventory.index'))
            ->post(route('inventory.snapshots.store'), [
                'batch_id' => $batch->batch_id,
                'inventory_date' => '2026-09-20',
            ])
            ->assertSessionHasErrors(['batch_id'], null, 'inventory');

        $this->assertSame(1, Inventory::count());
    }

    public function test_inventory_status_is_frozen_from_the_batch_available_balance(): void
    {
        $this->assertSame(Inventory::STATUS_ACTIVE, Inventory::statusForAvailable(10));
        $this->assertSame(Inventory::STATUS_DEPLETED, Inventory::statusForAvailable(0));

        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 75, 75);

        $snapshot = $this->createSnapshot($batch, 75);

        $this->assertSame(Inventory::STATUS_ACTIVE, $snapshot->status);

        // Draining the live balance afterwards must NOT rewrite the frozen snapshot.
        $batch->update(['available_count' => 0]);

        $this->assertSame(Inventory::STATUS_ACTIVE, $snapshot->fresh()->status);
        $this->assertSame(Inventory::STATUS_DEPLETED, Inventory::statusForBatch($batch->fresh()));
    }

    public function test_batch_exposes_inventory_snapshots_relationship(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatch($user, 'B001', 100, 100);
        $first = $this->createSnapshot($batch, 100, '2026-08-01');
        $second = $this->createSnapshot($batch, 100, '2026-09-01');

        $this->assertCount(2, $batch->inventories);
        $this->assertTrue($batch->inventories->contains($first));
        $this->assertTrue($batch->inventories->contains($second));
        $this->assertTrue($batch->latestInventory->is($second));
        $this->assertSame($batch->batch_id, $first->batch->batch_id);
    }
}
