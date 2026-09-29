<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ScionVariety;
use App\Models\StockIn;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7C: the Stock Ledger reflects the Phase 7B model on the seeded family.
 *
 * Seed (DatabaseSeeder): B001 Batangas 500 → regraft 100 into B001-R1 (Toledo)
 * → regraft 30 into B001-R1-R1 (Criollo); B002 Trinitario 150; B003 Forastero
 * 100. Seedling Stock In 500 + 300; scion Stock In Criollo 120 + Trinitario 80.
 */
class StockLedgerNestingTest extends TestCase
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
     * @return array<int,array<string,mixed>>
     */
    private function events(): array
    {
        $grid = AnalyticsMetrics::stockLedgerGrid();

        return collect($grid['months'])->flatMap(fn (array $month) => $month['rows'])->values()->all();
    }

    public function test_grafted_section_excludes_regraft_sub_batches(): void
    {
        $grid = AnalyticsMetrics::stockLedgerGrid();

        // Root batches only (cumulative totals): B001 500 + B002 150 + B003 100.
        $this->assertSame(750, $grid['summary']['grafted']);

        $graftedCodes = collect($this->events())
            ->filter(fn (array $event) => str_starts_with((string) $event['description'], 'Batch '))
            ->map(fn (array $event) => $event['description'])
            ->all();

        $this->assertContains('Batch B001 — established / grafted', $graftedCodes);
        $this->assertNotContains('Batch B001-R1 — established / grafted', $graftedCodes);
        $this->assertNotContains('Batch B001-R1-R1 — established / grafted', $graftedCodes);
    }

    public function test_regraft_rows_name_the_move_and_use_the_sub_batch_variety(): void
    {
        $toledo = ScionVariety::where('scion_variety_name', 'Toledo')->firstOrFail();
        $criollo = ScionVariety::where('scion_variety_name', 'Criollo')->firstOrFail();

        $events = collect($this->events());

        $first = $events->first(fn (array $event) => str_starts_with((string) $event['description'], 'Regraft B001 → B001-R1'));
        $this->assertNotNull($first);
        $this->assertSame([(int) $toledo->scion_variety_id => 100], $first['regraft']);

        $second = $events->first(fn (array $event) => str_starts_with((string) $event['description'], 'Regraft B001-R1 → B001-R1-R1'));
        $this->assertNotNull($second);
        $this->assertSame([(int) $criollo->scion_variety_id => 30], $second['regraft']);
    }

    public function test_seedlings_counts_only_seedling_stock_in_rows(): void
    {
        $grid = AnalyticsMetrics::stockLedgerGrid();

        // Only the two seedling-only receipts (500 + 300).
        $this->assertSame(800, $grid['summary']['seedlings']);

        // Before the Phase 7C fix every Stock In row was counted; that total
        // (980) must NOT be used because it double-counts scion receipts.
        $this->assertSame(1000, (int) StockIn::query()->sum('quantity'));
        $this->assertSame(
            800,
            (int) StockIn::query()->whereNull('scion_variety_id')->sum('quantity'),
        );
        $this->assertNotSame(
            (int) StockIn::query()->sum('quantity'),
            $grid['summary']['seedlings'],
        );
    }

    public function test_nested_batch_summary_has_every_column_for_the_seeded_family(): void
    {
        $rows = AnalyticsMetrics::stockLedgerGrid()['batchSummary']['rows'];

        $this->assertSame(
            ['B001', 'B001-R1', 'B001-R1-R1', 'B002', 'B003'],
            array_column($rows, 'batch'),
        );

        $byCode = collect($rows)->keyBy('batch');

        $this->assertSame([
            'batch' => 'B001',
            'parent' => null,
            'parent_missing' => false,
            'depth' => 0,
            'variety' => 'Batangas',
            'total_grafted' => 500,
            'available' => 400,
            'regraft' => 100,
            'mortality' => 25,
            'stock_out' => 0,
            'grafted_date' => '2026-07-15',
        ], $byCode['B001']);

        $this->assertSame([
            'batch' => 'B001-R1',
            'parent' => 'B001',
            'parent_missing' => false,
            'depth' => 1,
            'variety' => 'Toledo',
            'total_grafted' => 100,
            'available' => 50,
            'regraft' => 30,
            'mortality' => 5,
            'stock_out' => 20,
            'grafted_date' => '2026-09-05',
        ], $byCode['B001-R1']);

        $this->assertSame([
            'batch' => 'B001-R1-R1',
            'parent' => 'B001-R1',
            'parent_missing' => false,
            'depth' => 2,
            'variety' => 'Criollo',
            'total_grafted' => 30,
            'available' => 30,
            'regraft' => 0,
            'mortality' => 0,
            'stock_out' => 0,
            'grafted_date' => '2026-09-08',
        ], $byCode['B001-R1-R1']);

        // Cumulative raw sum: 500 + 100 + 30 + 150 + 100 = 880.
        $this->assertSame(880, AnalyticsMetrics::stockLedgerGrid()['batchSummary']['totals']['total_grafted']);
        $this->assertSame(730, AnalyticsMetrics::stockLedgerGrid()['batchSummary']['totals']['available']);
    }

    public function test_ledger_page_shows_the_footnote_and_nested_summary(): void
    {
        $this->actingAs($this->user())
            ->get(route('analytics.stock-ledger'))
            ->assertOk()
            ->assertSee('Batch summary (nested)')
            ->assertSee('Sub-batch quantities appear under Regraft, not Grafted')
            ->assertSee('B001-R1-R1')
            ->assertSee('↳');
    }

    public function test_ledger_csv_contains_the_nested_summary(): void
    {
        $response = $this->actingAs($this->user())->get(route('analytics.stock-ledger.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertStringContainsString('Batch summary (nested)', $csv);
        $this->assertStringContainsString('Batch or sub-batch', $csv);
        $this->assertStringContainsString('Total Grafted', $csv);
        $this->assertStringContainsString('B001-R1-R1', $csv);
        $this->assertStringContainsString('Sub-batch quantities appear under Regraft, not Grafted', $csv);
    }

    public function test_ledger_pdf_contains_the_nested_summary(): void
    {
        $response = $this->actingAs($this->user())->get(route('analytics.stock-ledger.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        // FPDF escapes parentheses in the content stream.
        $this->assertStringContainsString('Batch summary \(nested\)', $content);
        $this->assertStringContainsString('B001-R1-R1', $content);
    }

    public function test_filtering_by_a_sub_batch_returns_only_that_batch(): void
    {
        $sub = Batch::where('batch_code', 'B001-R1-R1')->firstOrFail();

        $summary = AnalyticsMetrics::stockLedgerSummary($sub->batch_id);

        // A sub-batch is not "grafted" (that belongs to its root)...
        $this->assertSame(0, $summary['grafted']);
        // ...but its own regraft record is visible under Regraft.
        $this->assertSame(30, $summary['regraft']);
        $this->assertSame(1, $summary['batches']);
    }

    public function test_filtering_by_a_parent_does_not_include_its_sub_batches(): void
    {
        $parent = Batch::where('batch_code', 'B001')->firstOrFail();

        $summary = AnalyticsMetrics::stockLedgerSummary($parent->batch_id);

        // Only B001 itself; the sub-batches are separate (exact-match) filters.
        $this->assertSame(1, $summary['batches']);
        $this->assertSame(500, $summary['grafted']);
        $this->assertSame(400, $summary['ready_to_plant']);
        // The sub-batch's regraft record is not pulled in by the parent filter.
        $this->assertSame(0, $summary['regraft']);
    }
}
