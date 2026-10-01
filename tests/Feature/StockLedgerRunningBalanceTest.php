<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * The Stock Ledger is a true chronological transaction ledger: every row is a
 * real dated event, Pullout posts one row per Stock Out release (a deliberate
 * reversal of the earlier cumulative-stamp design), and a running
 * "Remaining Available" is tracked per batch and reconciled against the
 * authoritative {@see Batch::survivingQuantity()}.
 *
 * Seed (DatabaseSeeder): B001 Batangas 500 → regraft 100 into B001-R1 (Toledo)
 * → regraft 30 into B001-R1-R1 (Criollo); B002 Trinitario 150; B003 Forastero
 * 100. B001 mortality 25; B001-R1 mortality 5 + Stock Out 20.
 */
class StockLedgerRunningBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function user(): User
    {
        return User::where('email', 'admin@cacaotrack.test')->firstOrFail();
    }

    /**
     * The per-row running balance sequence for one batch, in date order.
     *
     * @return list<array{date:string,description:string,remaining:int}>
     */
    private function runningBalanceFor(string $batchCode): array
    {
        $batch = Batch::where('batch_code', $batchCode)->firstOrFail();

        return collect(AnalyticsMetrics::stockLedgerGrid()['months'])
            ->flatMap(fn (array $month) => $month['rows'])
            ->filter(fn (array $row) => ($row['balance_batch_id'] ?? null) === (int) $batch->batch_id)
            ->sortBy('date')
            ->values()
            ->map(fn (array $row) => [
                'date' => $row['date'],
                'description' => $row['description'],
                'remaining' => $row['remaining'],
            ])
            ->all();
    }

    /**
     * Worked example (the screenshot batch): established 100 → regraft-out 30 →
     * mortality 5 → stock-out 20, showing the Remaining Available after EVERY
     * row, not just the final number.
     */
    public function test_b001_r1_running_balance_sequence_from_graft_to_today(): void
    {
        $this->assertSame(
            [
                [
                    'date' => '2026-09-05',
                    'description' => 'Sub-batch B001-R1 — ready to plant',
                    'remaining' => 100,
                ],
                [
                    'date' => '2026-09-08',
                    'description' => 'Regraft B001-R1 → B001-R1-R1 — Regrafted a further 30 plants from B001-R1 into a linked sub-batch.',
                    'remaining' => 70,
                ],
                [
                    'date' => '2026-09-10',
                    'description' => 'Mortality — Disease (B001-R1)',
                    'remaining' => 65,
                ],
                [
                    'date' => '2026-09-12',
                    'description' => 'Pullout B001-R1 → Cacao Farmers Coop (20 pots)',
                    'remaining' => 45,
                ],
            ],
            $this->runningBalanceFor('B001-R1'),
        );

        // The last row equals the live authoritative method exactly.
        $this->assertSame(
            45,
            Batch::where('batch_code', 'B001-R1')->firstOrFail()->survivingQuantity(),
        );
    }

    /**
     * A Stock Out posts its own dated Pullout row (batch + client + date), not a
     * cumulative figure merged onto a batch row.
     */
    public function test_pullout_posts_one_dated_row_per_stock_out_release(): void
    {
        $b001 = Batch::where('batch_code', 'B001')->firstOrFail();
        $client = Client::where('client_name', 'Mountain View Estate')->firstOrFail();

        $this->actingAs($this->user())
            ->post(route('inventory.stock-out.store'), [
                'client_id' => $client->client_id,
                'release_date' => '2026-10-01',
                'details' => [
                    ['batch_id' => $b001->batch_id, 'quantity' => 12, 'unit' => 'pots', 'unit_price' => 25],
                ],
            ])
            ->assertSessionHasNoErrors();

        $pulloutRows = collect(AnalyticsMetrics::stockLedgerGrid()['months'])
            ->flatMap(fn (array $month) => $month['rows'])
            ->filter(fn (array $row) => str_starts_with((string) $row['description'], 'Pullout B001 '))
            ->values();

        $this->assertCount(1, $pulloutRows);
        $this->assertSame('2026-10-01', $pulloutRows[0]['date']);
        $this->assertSame('Pullout B001 → Mountain View Estate (12 pots)', $pulloutRows[0]['description']);
        $this->assertSame(12, array_sum($pulloutRows[0]['pullout']));
        $this->assertSame((int) $b001->batch_id, (int) $pulloutRows[0]['balance_batch_id']);

        // It is its own row in October, not folded onto B001's July row.
        $october = collect(AnalyticsMetrics::stockLedgerGrid()['months'])->firstWhere('key', '2026-10');
        $this->assertNotNull($october);
        $this->assertCount(1, $october['rows']);

        // The running balance still reconciles after the new release:
        // 500 − 100 regraft − 25 mortality − 12 stock-out = 363.
        $this->assertSame(363, $b001->fresh()->survivingQuantity());
    }

    /**
     * The three cross-batch totals and the headline KPIs from today's work are
     * unchanged: 750 / 750 / 750, rate 7.33%, inventory 730.
     */
    public function test_cross_batch_totals_and_crude_mortality_rate_are_unchanged(): void
    {
        $this->assertSame(750, AnalyticsMetrics::batchSummary()['totals']['total_grafted']);
        $this->assertSame(750, AnalyticsMetrics::gapReport()['totalExpected']);
        $this->assertSame(
            750,
            (int) Inventory::query()->sum(DB::raw('total_grafted - regraft')),
        );

        $components = AnalyticsMetrics::mortalityRateComponents();
        $this->assertSame(55, $components['numerator']);
        $this->assertSame(750, $components['denominator']);
        $this->assertSame(7.33, AnalyticsMetrics::crudeMortalityRate());

        $this->assertSame(730, AnalyticsMetrics::totalActiveInventory());
    }

    /**
     * The reconciliation is a hard, loud check — not just a test. If the ledger's
     * independent running balance and the live balance ever diverge, the grid
     * throws instead of rendering.
     */
    public function test_ledger_fails_loudly_when_the_running_balance_and_the_live_balance_diverge(): void
    {
        $b001 = Batch::where('batch_code', 'B001')->firstOrFail();

        // Drift the live balance only (the event history is untouched), so the
        // running balance and survivingQuantity() no longer agree.
        $b001->forceFill(['available_count' => (int) $b001->available_count - 7])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not reconcile for batch B001');

        AnalyticsMetrics::stockLedgerGrid();
    }

    /**
     * The gate now subtracts stock-out as well as mortality, so a batch with BOTH
     * (B001-R1) reports 45, not the old 50.
     */
    public function test_surviving_quantity_subtracts_stock_out_as_well_as_mortality(): void
    {
        $b001r1 = Batch::where('batch_code', 'B001-R1')->firstOrFail();

        $this->assertSame(100, (int) $b001r1->total_grafted);
        $this->assertSame(30, (int) $b001r1->regraft);
        $this->assertSame(5, $b001r1->effectiveMortalityQuantity());
        $this->assertSame(20, (int) $b001r1->stockOutDetails()->sum('quantity'));

        // 100 − 30 − 5 − 20 = 45 (the old cap stopped at 50 by only subtracting
        // the larger of mortality / stock-out).
        $this->assertSame(45, $b001r1->survivingQuantity());
    }
}
