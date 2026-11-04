<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Role;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Stock In on the client-approved model (ERD §3.7): it records the received
 * quantity immediately (no five-day viability count), has NO `batch_id` and no
 * inventory side effect. Since the client-confirmed merge of Scion_Receiving
 * into Stock_In, a single form/list handles both seedling-only rows and
 * scion-inclusive rows (identified by a non-null `scion_variety_id`).
 */
class StockInTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplier(string $name = 'Supplier A'): Supplier
    {
        return Supplier::create([
            'supplier_name' => $name,
            'contact_number' => '0917-000-0000',
            'address' => 'Test Address',
        ]);
    }

    private function createScionVariety(string $name = 'Criollo'): ScionVariety
    {
        return ScionVariety::create(['scion_variety_name' => $name]);
    }

    public function test_guests_are_redirected_from_stock_in_to_login(): void
    {
        $this->get(route('inventory.stock-in'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_stock_in_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Stock In')
            ->assertSee('Receiving History')
            ->assertSee('Total Quantity Received');
    }

    public function test_stock_in_filters_apply_automatically_without_an_apply_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('id="stockInFilterForm"', false)
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="filterSearch"', false)
            ->assertSee('id="filterSupplier"', false)
            ->assertSee('Clear Filters')
            ->assertDontSee('bi-funnel')
            ->assertDontSee('Apply Filters');
    }

    public function test_stock_in_creates_a_record_without_touching_inventory(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 30,
                    'origin' => 'Davao del Sur',
                    'date_received' => '2026-09-05',
                    'notes' => 'Seedling bundle.',
                ],
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success', 'Saved: Seedlings 30.');

        $this->assertDatabaseHas('stock_ins', [
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 30,
            'origin' => 'Davao del Sur',
            'notes' => 'Seedling bundle.',
            'date_received' => '2026-09-05',
        ]);

        // Stock In is a standalone receiving record; it never mutates a batch.
        $this->assertDatabaseCount('inventories', 0);

        // A pure-seedling submission never sets the optional scion fields.
        $this->assertNull(StockIn::first()->scion_variety_id);
        $this->assertNull(StockIn::first()->unit_price);
    }

    public function test_stock_in_saves_without_a_viable_column(): void
    {
        // The approved Stock In has no separate seedling viability count.
        $this->assertFalse(Schema::hasColumn('stock_ins', 'viable'));

        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 45,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(45, (int) StockIn::first()->quantity);
    }

    public function test_scion_stock_in_persists_scion_fields(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety('Criollo');

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'scion_variety_id' => $variety->scion_variety_id,
                    'quantity' => 120,
                    'unit_price' => 12.50,
                    'origin' => 'Davao del Sur',
                    'notes' => 'Premium bundle.',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('success', 'Saved: Scion 120.');

        $stockIn = StockIn::first();

        $this->assertNotNull($stockIn);
        $this->assertSame($variety->scion_variety_id, (int) $stockIn->scion_variety_id);
        $this->assertSame('12.50', $stockIn->unit_price);
        $this->assertSame(120, (int) $stockIn->quantity);
        $this->assertTrue($stockIn->isScionEntry());
        $this->assertTrue($stockIn->scionVariety->is($variety));
    }

    public function test_scion_variety_and_unit_price_must_be_provided_together(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        // Scion variety without a unit price.
        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'scion_variety_id' => $variety->scion_variety_id,
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['scion.unit_price'], null, 'stockin');

        // Unit price without a scion variety.
        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 10,
                    'unit_price' => 5.00,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['scion.scion_variety_id'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_modal_is_responsive_and_shows_the_scion_amount_preview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down', false)
            ->assertSee('id="stockInCalculatedAmount"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('Calculated Amount');
    }

    public function test_the_modal_has_exactly_one_save_button_inside_the_footer(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->getContent();

        preg_match('/<form id="stockInForm".*?<\/form>/s', $html, $matches);
        $form = $matches[0] ?? '';

        $this->assertNotSame('', $form, 'The New Stock In form was not found.');
        $this->assertSame(1, substr_count($form, 'type="submit"'));
        $this->assertSame(1, substr_count($form, 'id="stockInSubmitBtn"'));
        $this->assertStringNotContainsString('id="stockInScionSubmitBtn"', $form);
        $this->assertStringContainsString('class="modal-footer', $form);
        $this->assertGreaterThan(
            strpos($form, 'id="stockInSaveSummaryBar"'),
            strpos($form, 'class="modal-footer'),
        );
        $this->assertGreaterThan(
            strpos($form, 'class="modal-footer'),
            strpos($form, 'id="stockInSubmitBtn"'),
        );
    }

    public function test_a_failed_scion_field_reopens_the_scion_tab_without_hiding_the_error(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        // Unit price without a scion variety: the scion tab must reopen so the
        // specific error is never hidden behind the Seedlings tab.
        $this->actingAs($user)
            ->from(route('inventory.stock-in'))
            ->followingRedirects()
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 10,
                    'unit_price' => 5.00,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertOk()
            ->assertSee('show active" id="scionPane"', false)
            ->assertSee('Scion tab: select a scion variety.', false);
    }

    public function test_stock_in_rejects_a_negative_unit_price(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'scion_variety_id' => $variety->scion_variety_id,
                    'quantity' => 10,
                    'unit_price' => -1,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['scion.unit_price'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_list_shows_scion_variety_for_scion_rows_and_dash_for_seedlings(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety('Trinitario');

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 50,
            'origin' => 'Seedling Origin',
            'date_received' => '2026-09-01',
        ]);

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'quantity' => 80,
            'unit_price' => 12.50,
            'origin' => 'Scion Origin',
            'date_received' => '2026-09-02',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Trinitario')
            ->assertSee('Scion');
    }

    public function test_stock_in_seedling_total_excludes_scion_rows(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 50,
            'origin' => 'Seedling Origin',
            'date_received' => '2026-09-01',
        ]);

        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'quantity' => 80,
            'unit_price' => 12.50,
            'origin' => 'Scion Origin',
            'date_received' => '2026-09-02',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertViewHas('totalReceived', 130)
            ->assertViewHas('totalReceivedSeedlings', 50)
            ->assertViewHas('totalReceivedScion', 80)
            ->assertSee('Total Received (Scion)');
    }

    public function test_entry_type_is_derived_and_labelled(): void
    {
        // There is no discriminator column: the type is derived from whether
        // the row carries a scion variety (ERD §3.7).
        $seedling = new StockIn;
        $seedling->scion_variety_id = null;
        $this->assertSame(StockIn::TYPE_SEEDLING, $seedling->entryType());
        $this->assertSame('Seedlings', $seedling->entryTypeLabel());

        $scion = new StockIn;
        $scion->scion_variety_id = 1;
        $this->assertSame(StockIn::TYPE_SCION, $scion->entryType());
        $this->assertSame('Scion', $scion->entryTypeLabel());
    }

    public function test_stock_in_page_filters_by_entry_type(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        $seedlingRow = StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 50,
            'origin' => 'Seedling Origin',
            'date_received' => '2026-09-01',
        ]);

        $scionRow = StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $user->id,
            'quantity' => 80,
            'unit_price' => 12.50,
            'origin' => 'Scion Origin',
            'date_received' => '2026-09-02',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['type' => StockIn::TYPE_SCION]))
            ->assertOk()
            ->assertSee($scionRow->reference())
            ->assertDontSee($seedlingRow->reference());

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['type' => StockIn::TYPE_SEEDLING]))
            ->assertOk()
            ->assertSee($seedlingRow->reference())
            ->assertSee('Seedlings')
            ->assertDontSee($scionRow->reference());
    }

    public function test_stock_in_schema_carries_optional_scion_fields(): void
    {
        // The merged scion columns are nullable so a pure-seedling row is
        // unchanged; presence of scion_variety_id is the only "type" signal.
        $this->assertTrue(Schema::hasColumn('stock_ins', 'scion_variety_id'));
        $this->assertTrue(Schema::hasColumn('stock_ins', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('stock_ins', 'entry_type'));
    }

    public function test_stock_in_requires_valid_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [])
            ->assertSessionHasErrors(
                ['entries'],
                'Fill in the Seedlings tab, the Scion tab, or both.',
                'stockin',
            );
    }

    public function test_stock_in_rejects_unknown_or_archived_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => 9999,
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['seedling.supplier_id'], null, 'stockin');

        $supplier->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['seedling.supplier_id'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_rejects_zero_and_negative_quantities(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        foreach ([0, -5] as $quantity) {
            $this->actingAs($user)
                ->post(route('inventory.stock-in.store'), [
                    'seedling' => [
                        'supplier_id' => $supplier->supplier_id,
                        'quantity' => $quantity,
                        'origin' => 'Davao',
                        'date_received' => '2026-09-05',
                    ],
                ])
                ->assertSessionHasErrors(['seedling.quantity'], null, 'stockin');
        }

        $this->assertSame(0, StockIn::count());
    }

    public function test_stock_in_rejects_a_future_received_date(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'date_received' => now()->addDay()->toDateString(),
                ],
            ])
            ->assertSessionHasErrors(['seedling.date_received'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_both_tabs_are_saved_together_in_one_transaction(): void
    {
        $user = User::factory()->create();
        $seedlingSupplier = $this->createSupplier('Seedling Supplier');
        $scionSupplier = $this->createSupplier('Scion Supplier');
        $variety = $this->createScionVariety('Criollo');

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $seedlingSupplier->supplier_id,
                    'quantity' => 500,
                    'origin' => 'Seedling Origin',
                    'notes' => 'Seedling note.',
                    'date_received' => '2026-09-01',
                ],
                'scion' => [
                    'supplier_id' => $scionSupplier->supplier_id,
                    'quantity' => 300,
                    'origin' => 'Scion Origin',
                    'scion_variety_id' => $variety->scion_variety_id,
                    'unit_price' => 12.50,
                    'notes' => 'Scion note.',
                    'date_received' => '2026-09-02',
                ],
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Saved: Seedlings 500 and Scion 300.');

        $this->assertSame(2, StockIn::count());

        $seedling = StockIn::query()->whereNull('scion_variety_id')->firstOrFail();

        $this->assertSame($seedlingSupplier->supplier_id, (int) $seedling->supplier_id);
        $this->assertSame(500, (int) $seedling->quantity);
        $this->assertSame('Seedling Origin', $seedling->origin);
        $this->assertSame('Seedling note.', $seedling->notes);
        $this->assertSame('2026-09-01', $seedling->date_received->toDateString());
        $this->assertNull($seedling->scion_variety_id);
        $this->assertNull($seedling->unit_price);

        $scion = StockIn::query()->whereNotNull('scion_variety_id')->firstOrFail();

        $this->assertSame($scionSupplier->supplier_id, (int) $scion->supplier_id);
        $this->assertSame($variety->scion_variety_id, (int) $scion->scion_variety_id);
        $this->assertSame(300, (int) $scion->quantity);
        $this->assertSame('12.50', $scion->unit_price);
        $this->assertSame('Scion Origin', $scion->origin);
        $this->assertSame('Scion note.', $scion->notes);
        $this->assertSame('2026-09-02', $scion->date_received->toDateString());

        $logs = ActivityLog::query()
            ->where('activity_type', ActivityLog::TYPE_STOCK_IN_RECORDED)
            ->orderBy('activity_id')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertSame('Received 500 seedlings from Seedling Supplier (origin: Seedling Origin).', $logs[0]->description);
        $this->assertSame('Received 300 scion of Criollo from Scion Supplier (origin: Scion Origin).', $logs[1]->description);
        $this->assertSame('2026-09-01', $logs[0]->activity_date->toDateString());
        $this->assertSame('2026-09-02', $logs[1]->activity_date->toDateString());
    }

    public function test_a_scion_receipt_does_not_require_a_supplier(): void
    {
        $user = User::factory()->create();
        $variety = $this->createScionVariety();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'quantity' => 40,
                    'origin' => 'Davao',
                    'scion_variety_id' => $variety->scion_variety_id,
                    'unit_price' => 12.5,
                    'notes' => '',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Saved: Scion 40.');

        $record = StockIn::firstOrFail();
        $this->assertNull($record->supplier_id);
        $this->assertTrue($record->isScionEntry());

        $log = ActivityLog::where('activity_type', ActivityLog::TYPE_STOCK_IN_RECORDED)->firstOrFail();
        $this->assertStringContainsString('from supplier', $log->description);
    }

    public function test_a_scion_receipt_still_rejects_an_unknown_or_archived_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'supplier_id' => 9999,
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'scion_variety_id' => $variety->scion_variety_id,
                    'unit_price' => 10,
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['scion.supplier_id'], null, 'stockin');

        $supplier->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'scion_variety_id' => $variety->scion_variety_id,
                    'unit_price' => 10,
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['scion.supplier_id'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_a_seedling_receipt_still_requires_a_supplier(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => '',
                    'quantity' => 10,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['seedling.supplier_id'], null, 'stockin');

        $this->assertSame(0, StockIn::count());
    }

    public function test_a_scion_only_save_ignores_the_untouched_seedling_tab(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => '',
                    'quantity' => '',
                    'origin' => '',
                    'notes' => '',
                    'date_received' => '2026-09-05',
                ],
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 120,
                    'origin' => 'Davao',
                    'scion_variety_id' => $variety->scion_variety_id,
                    'unit_price' => 0,
                    'notes' => '',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Saved: Scion 120.');

        $this->assertSame(1, StockIn::count());
        $this->assertSame(1, StockIn::query()->whereNotNull('scion_variety_id')->count());
        $this->assertSame(0, StockIn::query()->whereNull('scion_variety_id')->count());
    }

    public function test_a_seedlings_only_save_ignores_the_untouched_scion_tab(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 500,
                    'origin' => 'Davao',
                    'notes' => '',
                    'date_received' => '2026-09-05',
                ],
                'scion' => [
                    'supplier_id' => '',
                    'quantity' => '',
                    'origin' => '',
                    'scion_variety_id' => '',
                    'unit_price' => '',
                    'notes' => '',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Saved: Seedlings 500.');

        $this->assertSame(1, StockIn::count());
        $this->assertSame(1, StockIn::query()->whereNull('scion_variety_id')->count());
        $this->assertNull(StockIn::first()->scion_variety_id);
        $this->assertNull(StockIn::first()->unit_price);
    }

    public function test_a_note_only_tab_is_validated_and_nothing_is_saved(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 500,
                    'origin' => 'Seedling Origin',
                    'date_received' => '2026-09-05',
                ],
                'scion' => [
                    'supplier_id' => '',
                    'quantity' => '',
                    'origin' => '',
                    'scion_variety_id' => '',
                    'unit_price' => '',
                    'notes' => 'Typed a note only.',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors([
                'scion.quantity',
                'scion.origin',
                'scion.scion_variety_id',
                'scion.unit_price',
            ], null, 'stockin');

        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, ActivityLog::query()->where('activity_type', ActivityLog::TYPE_STOCK_IN_RECORDED)->count());
    }

    public function test_the_save_is_atomic_when_activity_logging_fails(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        ActivityLog::creating(static function (): void {
            throw new RuntimeException('forced activity log failure');
        });

        try {
            $this->withoutExceptionHandling();

            try {
                $this->actingAs($user)->post(route('inventory.stock-in.store'), [
                    'seedling' => [
                        'supplier_id' => $supplier->supplier_id,
                        'quantity' => 500,
                        'origin' => 'Seedling Origin',
                        'date_received' => '2026-09-05',
                    ],
                    'scion' => [
                        'supplier_id' => $supplier->supplier_id,
                        'quantity' => 300,
                        'origin' => 'Scion Origin',
                        'scion_variety_id' => $variety->scion_variety_id,
                        'unit_price' => 12.50,
                        'date_received' => '2026-09-05',
                    ],
                ]);

                $this->fail('The forced activity log failure did not abort the save.');
            } catch (RuntimeException $exception) {
                $this->assertSame('forced activity log failure', $exception->getMessage());
            }
        } finally {
            ActivityLog::flushEventListeners();
        }

        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_duplicate_submission_is_blocked_even_when_the_other_tab_is_empty(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        $payload = [
            'scion' => [
                'supplier_id' => $supplier->supplier_id,
                'quantity' => 120,
                'origin' => 'Davao',
                'scion_variety_id' => $variety->scion_variety_id,
                'unit_price' => 12.50,
                'date_received' => '2026-09-05',
            ],
        ];

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), $payload + [
                'seedling' => [
                    'supplier_id' => '',
                    'quantity' => '',
                    'origin' => '',
                    'notes' => '',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasErrors(['submission'], null, 'stockin');

        $this->assertSame(1, StockIn::count());
    }

    public function test_a_user_without_manage_stock_cannot_record_stock_in(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $supplier = $this->createSupplier();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 500,
                    'origin' => 'Davao',
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, StockIn::count());
    }

    public function test_seedling_totals_ignore_the_scion_entry(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety();

        $this->actingAs($user)
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 500,
                    'origin' => 'Seedling Origin',
                    'date_received' => '2026-09-05',
                ],
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 300,
                    'origin' => 'Scion Origin',
                    'scion_variety_id' => $variety->scion_variety_id,
                    'unit_price' => 12.50,
                    'date_received' => '2026-09-05',
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertViewHas('totalReceivedSeedlings', 500)
            ->assertViewHas('totalReceivedScion', 300)
            ->assertViewHas('totalReceived', 800);

        $this->assertSame(500, AnalyticsMetrics::stockLedgerGrid()['summary']['seedlings']);
    }

    public function test_the_modal_renders_both_panes_helpers_and_a_single_footer_action(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('id="seedlingsPane"', false)
            ->assertSee('id="scionPane"', false)
            ->assertSee('Seedlings are recorded without a variety or price.')
            ->assertSee('Required when you fill in this tab.')
            ->assertSee('The supplier is optional; the other fields are required when you fill in this tab.')
            ->assertSee('Select Supplier (optional)')
            ->assertSee('Clear this entry')
            ->assertSee('Copy supplier and date from the other tab')
            ->assertSee('Fill in at least one tab.')
            ->assertSee('Discard what you entered?')
            ->assertSee('Keep editing')
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/name="seedling\[[^"]+\]"[^>]*\brequired\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="scion\[[^"]+\]"[^>]*\brequired\b/', $html);
    }

    public function test_old_values_restore_into_both_panes_after_a_failure(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier();
        $variety = $this->createScionVariety('Criollo');

        $this->actingAs($user)
            ->from(route('inventory.stock-in'))
            ->followingRedirects()
            ->post(route('inventory.stock-in.store'), [
                'seedling' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 500,
                    'origin' => 'Seedling Origin',
                    'notes' => 'Seedling note.',
                    'date_received' => '2026-09-01',
                ],
                'scion' => [
                    'supplier_id' => $supplier->supplier_id,
                    'quantity' => 300,
                    'origin' => 'Scion Origin',
                    'scion_variety_id' => $variety->scion_variety_id,
                    'unit_price' => -1,
                    'notes' => 'Scion note.',
                    'date_received' => '2026-09-02',
                ],
            ])
            ->assertOk()
            ->assertSee('show active" id="scionPane"', false)
            ->assertSee('value="Seedling Origin"', false)
            ->assertSee('value="Scion Origin"', false)
            ->assertSee('Seedling note.')
            ->assertSee('Scion note.')
            ->assertSee('Scion tab: unit price cannot be negative.');
    }

    public function test_stock_in_page_lists_real_records(): void
    {
        $user = User::factory()->create();
        $supplier = $this->createSupplier('Real Supplier Co');
        StockIn::create([
            'supplier_id' => $supplier->supplier_id,
            'user_id' => $user->id,
            'quantity' => 77,
            'origin' => 'Test Origin',
            'date_received' => '2026-09-04',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Real Supplier Co')
            ->assertSee('77');
    }

    public function test_stock_in_page_filters_by_supplier_and_search(): void
    {
        $user = User::factory()->create();
        $supplierA = $this->createSupplier('Alpha Supply');
        $supplierB = $this->createSupplier('Beta Supply');

        $stockInA = StockIn::create(['supplier_id' => $supplierA->supplier_id, 'user_id' => $user->id, 'quantity' => 10, 'origin' => 'Alpha Origin', 'date_received' => '2026-09-01']);
        $stockInB = StockIn::create(['supplier_id' => $supplierB->supplier_id, 'user_id' => $user->id, 'quantity' => 20, 'origin' => 'Beta Origin', 'date_received' => '2026-09-02']);

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['supplier_id' => $supplierB->supplier_id]))
            ->assertOk()
            ->assertSee($stockInB->reference())
            ->assertDontSee($stockInA->reference());

        $this->actingAs($user)
            ->get(route('inventory.stock-in', ['search' => 'Alpha Supply']))
            ->assertOk()
            ->assertSee($stockInA->reference())
            ->assertDontSee($stockInB->reference());
    }

    public function test_stock_in_page_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('No stock-in transactions found for the current filters.');
    }

    public function test_supplier_can_be_created_inline(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($user)
            ->post(route('inventory.suppliers.store'), [
                'supplier_name' => 'New Supplier',
                'contact_number' => '0999-123-4567',
                'address' => 'Somewhere, Philippines',
            ])
            ->assertRedirect(route('inventory.stock-in'))
            ->assertSessionHas('new_supplier_id');

        $this->assertDatabaseHas('suppliers', ['supplier_name' => 'New Supplier']);
    }

    public function test_archived_supplier_is_not_offered_on_the_stock_in_form(): void
    {
        $user = User::factory()->create();
        $this->createSupplier('Hidden Supplier')->update(['archived_at' => now()]);
        $this->createSupplier('Visible Supplier');

        $this->actingAs($user)
            ->get(route('inventory.stock-in'))
            ->assertOk()
            ->assertSee('Visible Supplier')
            ->assertDontSee('Hidden Supplier');
    }

    public function test_stock_in_does_not_reference_a_batch(): void
    {
        // Regression guard: the removed `stock_ins.batch_id` must not reappear.
        $this->assertFalse(
            Schema::hasColumn('stock_ins', 'batch_id'),
        );
    }

    public function test_stock_in_reference_is_formatted(): void
    {
        $first = new StockIn;
        $first->stock_in_id = 1;

        $padded = new StockIn;
        $padded->stock_in_id = 42;

        $large = new StockIn;
        $large->stock_in_id = 1234;

        $this->assertSame('SI-001', $first->reference());
        $this->assertSame('SI-042', $padded->reference());
        $this->assertSame('SI-1234', $large->reference());
    }
}
