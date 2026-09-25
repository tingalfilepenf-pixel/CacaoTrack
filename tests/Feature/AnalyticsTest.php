<?php

namespace Tests\Feature;

use App\Models\Analytic;
use App\Models\Batch;
use App\Models\Inventory;
use App\Models\MortalityAdjustment;
use App\Models\MortalityRecord;
use App\Models\PotType;
use App\Models\ReplantingRecord;
use App\Models\Scion;
use App\Models\User;
use App\Models\Variety;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Variety $criollo;

    private Variety $trinitario;

    private Batch $b001;

    private Batch $b002;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->criollo = Variety::create(['variety_name' => 'Criollo']);
        $this->trinitario = Variety::create(['variety_name' => 'Trinitario']);
        $scion = Scion::create(['scion_name' => 'ICS-1']);
        $pot = PotType::create(['pot_name' => 'Standard Polybag']);

        $this->b001 = $this->createBatch($this->criollo, $scion, $pot, 'B001', 200, 180, '2026-07-15', 170);
        $this->b002 = $this->createBatch($this->trinitario, $scion, $pot, 'B002', 100, 100, '2026-08-20', 100);

        $disease = MortalityRecord::create([
            'batch_id' => $this->b001->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        MortalityAdjustment::create([
            'mortality_id' => $disease->mortality_id,
            'approved_by_id' => $this->user->id,
            'adjustment_quantity' => -5,
            'reason' => 'Recount correction.',
            'adjusted_at' => now(),
        ]);

        MortalityRecord::create([
            'batch_id' => $this->b002->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 10,
            'cause' => 'Pest',
            'recorded_at' => '2026-09-01',
        ]);

        ReplantingRecord::create([
            'batch_id' => $this->b001->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 15,
            'replanting_date' => '2026-09-03',
            'remark' => 'Replaced disease losses.',
        ]);
    }

    private function createBatch(
        Variety $variety,
        Scion $scion,
        PotType $pot,
        string $code,
        int $totalGrafted,
        int $viable,
        string $graftedDate,
        int $inventoryQuantity,
    ): Batch {
        $batch = Batch::create([
            'variety_id' => $variety->variety_id,
            'scion_id' => $scion->scion_id,
            'pot_id' => $pot->pot_id,
            'user_id' => $this->user->id,
            'batch_code' => $code,
            'total_grafted' => $totalGrafted,
            'viable_count' => $viable,
            'available_count' => $viable,
            'grafted_date' => $graftedDate,
            'created_at' => now(),
            'status' => 'active',
        ]);

        Inventory::create([
            'batch_id' => $batch->batch_id,
            'current_quantity' => $inventoryQuantity,
            'status' => Inventory::statusForQuantity($inventoryQuantity),
            'last_update' => now(),
        ]);

        return $batch;
    }

    public function test_guests_are_redirected_from_all_analytics_pages(): void
    {
        $this->get(route('analytics.index'))->assertRedirect(route('login'));
        $this->get(route('analytics.export'))->assertRedirect(route('login'));
        $this->get(route('analytics.export.csv'))->assertRedirect(route('login'));
        $this->get(route('analytics.stock-ledger'))->assertRedirect(route('login'));
        $this->get(route('analytics.stock-ledger.export'))->assertRedirect(route('login'));
        $this->get(route('analytics.mortality-matrix'))->assertRedirect(route('login'));
        $this->get(route('analytics.mortality-matrix.export'))->assertRedirect(route('login'));
        $this->get(route('analytics.mortality-matrix.export.csv'))->assertRedirect(route('login'));
        $this->get(route('analytics.historical.seasonality'))->assertRedirect(route('login'));
        $this->get(route('analytics.historical.age-profile'))->assertRedirect(route('login'));
        $this->get(route('analytics.historical.gap-report'))->assertRedirect(route('login'));
        $this->get(route('analytics.historical.gap-report.export'))->assertRedirect(route('login'));
        $this->get(route('analytics.historical.gap-report.export.csv'))->assertRedirect(route('login'));
        $this->post(route('analytics.reports.store'))->assertRedirect(route('login'));
    }

    public function test_operational_analytics_shows_real_kpis(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSee('Operational Analytics')
            ->assertSee('270')   // total active inventory
            ->assertSee('10.00') // crude mortality rate
            ->assertSee('15');   // total replanting deficit
    }

    public function test_operational_analytics_page_exposes_print_and_csv_actions(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSee('Print Analytics')
            ->assertSee('Export CSV')
            ->assertSee('printAnalyticsModal');
    }

    public function test_operational_analytics_pdf_export_renders_real_data(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('Operational Analytics', $content);
        $this->assertStringContainsString('Total Active Inventory', $content);
        $this->assertStringContainsString('270', $content);
        $this->assertStringContainsString('Crude Mortality Rate', $content);
        $this->assertStringContainsString('10.00%', $content);
        $this->assertStringContainsString('Replanting Deficit', $content);
        $this->assertStringContainsString('Mortality Trend', $content);
        $this->assertStringContainsString('Inventory by Variety', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringContainsString('Trinitario', $content);
        $this->assertStringContainsString('Survival vs Mortality', $content);
        $this->assertStringContainsString('Surviving', $content);
        $this->assertStringContainsString('280', $content); // surviving = stored viable (alive) count
    }

    public function test_operational_analytics_csv_export_renders_real_data(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertStringContainsString('Operational Analytics', $csv);
        $this->assertStringContainsString('Key Performance Indicators', $csv);
        $this->assertStringContainsString('Crude Mortality Rate', $csv);
        $this->assertStringContainsString('10.00%', $csv);
        $this->assertStringContainsString('Mortality Trend (Last 6 Months)', $csv);
        $this->assertStringContainsString('Inventory by Variety', $csv);
        $this->assertStringContainsString('Replanting Deficit by Batch', $csv);
        $this->assertStringContainsString('Survival vs Mortality', $csv);
        $this->assertStringContainsString('Criollo', $csv);
        $this->assertStringContainsString('B001', $csv);
        $this->assertStringContainsString('270', $csv);
    }

    public function test_centralized_metrics_return_real_values(): void
    {
        $this->assertSame(270, AnalyticsMetrics::totalActiveInventory());
        $this->assertSame(15, AnalyticsMetrics::totalReplantingDeficit());
        $this->assertSame(10.0, AnalyticsMetrics::crudeMortalityRate());

        $components = AnalyticsMetrics::mortalityRateComponents();
        $this->assertSame(30, $components['numerator']);
        $this->assertSame(300, $components['denominator']);

        $survival = AnalyticsMetrics::survivalVsMortality();
        $this->assertSame(310, $survival['totalViable']);
        $this->assertSame(30, $survival['mortality']);
        $this->assertSame(280, $survival['surviving']);
    }

    public function test_stock_ledger_shows_real_batch_data(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger'))
            ->assertOk()
            ->assertSee('Cacao Tree Stock Ledger')
            ->assertSee('B001')
            ->assertSee('B002')
            ->assertSee('Criollo')
            ->assertSee('Trinitario');
    }

    public function test_stock_ledger_filters_by_batch(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger', ['batch_id' => $this->b001->batch_id]))
            ->assertOk()
            ->assertSee('class="fw-bold">B001', false)
            ->assertDontSee('class="fw-bold">B002', false);
    }

    public function test_stock_ledger_rejects_inverted_date_range(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger', ['date_from' => '2026-09-10', 'date_to' => '2026-09-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_stock_ledger_pdf_export_returns_a_pdf_with_real_data(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics.stock-ledger.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        // Real batches, varieties and the totals strip (no fabricated rows).
        $this->assertStringContainsString('Cacao Tree Stock Ledger', $content);
        $this->assertStringContainsString('B001', $content);
        $this->assertStringContainsString('B002', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringContainsString('Batches: 2', $content);
        $this->assertStringContainsString('Grafted: 300', $content);
        $this->assertStringContainsString('Available: 270', $content);
        $this->assertStringContainsString('Mortality: 30', $content);
        $this->assertStringContainsString('Replanted: 15', $content);
    }

    public function test_stock_ledger_pdf_export_applies_filters(): void
    {
        $content = $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger.export', ['batch_id' => $this->b001->batch_id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertStringContainsString('B001', $content);
        $this->assertStringContainsString('Batch: B001', $content);
        $this->assertStringNotContainsString('B002', $content);
    }

    public function test_stock_ledger_pdf_export_rejects_inverted_date_range(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger.export', ['date_from' => '2026-09-10', 'date_to' => '2026-09-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_stock_ledger_pdf_export_rejects_unknown_batch_filter(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger.export', ['batch_id' => 99999]))
            ->assertSessionHasErrors('batch_id');
    }

    public function test_mortality_matrix_groups_variety_by_cause(): void
    {
        $matrix = AnalyticsMetrics::mortalityMatrix();

        $this->assertSame(['Disease', 'Pest'], $matrix['causes']);
        $this->assertSame(30, $matrix['grandTotal']);
        $this->assertSame(20, $matrix['totalsByCause']['Disease']);
        $this->assertSame(10, $matrix['totalsByCause']['Pest']);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('Disease')
            ->assertSee('Pest')
            ->assertSee('Criollo');
    }

    public function test_mortality_matrix_exposes_raw_adjustment_effective_chain(): void
    {
        $matrix = AnalyticsMetrics::mortalityMatrix();

        // Raw mortality (before adjustments) vs effective (after adjustments).
        $this->assertSame(35, $matrix['rawTotal']);
        $this->assertSame(-5, $matrix['adjustmentTotal']);
        $this->assertSame(30, $matrix['grandTotal']);

        $this->assertSame(25, $matrix['rawTotalsByCause']['Disease']);
        $this->assertSame(20, $matrix['totalsByCause']['Disease']);
        $this->assertSame(-5, $matrix['adjustmentTotalsByCause']['Disease']);

        $this->assertSame(10, $matrix['rawTotalsByCause']['Pest']);
        $this->assertSame(10, $matrix['totalsByCause']['Pest']);
        $this->assertSame(0, $matrix['adjustmentTotalsByCause']['Pest']);

        // Effective equals raw + adjustments for every row (single definition).
        foreach ($matrix['rows'] as $row) {
            $this->assertSame($row['total'], $row['rawTotal'] + $row['adjustmentTotal']);
        }

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('Raw Mortality')
            ->assertSee('Adjustments')
            ->assertSee('Effective Mortality')
            ->assertSee('base 25')
            ->assertSee('(-5)');
    }

    public function test_mortality_matrix_rejects_inverted_date_range(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix', ['date_from' => '2026-09-10', 'date_to' => '2026-09-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_mortality_matrix_rejects_unknown_batch_filter(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix', ['batch_id' => 99999]))
            ->assertSessionHasErrors('batch_id');
    }

    public function test_mortality_matrix_page_exposes_print_and_csv_actions(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('Print Matrix')
            ->assertSee('Export CSV')
            ->assertSee('printMatrixModal');
    }

    public function test_mortality_matrix_pdf_export_renders_real_data(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics.mortality-matrix.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('Mortality Root-Cause Matrix', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringContainsString('Trinitario', $content);
        $this->assertStringContainsString('Disease', $content);
        $this->assertStringContainsString('Pest', $content);
        $this->assertStringContainsString('Raw mortality: 35', $content);
        $this->assertStringContainsString('Adjustments: -5', $content);
        $this->assertStringContainsString('Effective mortality: 30', $content);
        $this->assertStringContainsString('Grand Total', $content);
    }

    public function test_mortality_matrix_pdf_export_applies_filters(): void
    {
        $content = $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix.export', ['batch_id' => $this->b001->batch_id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertStringContainsString('Batch: B001', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringNotContainsString('Trinitario', $content);
    }

    public function test_mortality_matrix_csv_export_renders_real_matrix(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics.mortality-matrix.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertStringContainsString('Variety,Disease,Pest,Total', $csv);
        $this->assertStringContainsString('Criollo,20,0,20', $csv);
        $this->assertStringContainsString('Trinitario,0,10,10', $csv);
        $this->assertStringContainsString('Grand Total', $csv);
        $this->assertStringContainsString(',20,10,30', $csv);
    }

    public function test_mortality_matrix_exports_reject_inverted_date_range(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix.export', ['date_from' => '2026-09-10', 'date_to' => '2026-09-01']))
            ->assertSessionHasErrors('date_to');

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix.export.csv', ['date_from' => '2026-09-10', 'date_to' => '2026-09-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_mortality_matrix_csv_export_rejects_unknown_batch_filter(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix.export.csv', ['batch_id' => 99999]))
            ->assertSessionHasErrors('batch_id');
    }

    public function test_seasonality_heatmap_uses_real_monthly_data(): void
    {
        $heatmap = AnalyticsMetrics::seasonality(2026);

        $this->assertSame(2026, $heatmap['selectedYear']);
        $this->assertSame(30, $heatmap['total']);
        $this->assertSame(20, $heatmap['max']);

        $this->actingAs($this->user)
            ->get(route('analytics.historical.seasonality', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Seasonality Heatmap');
    }

    public function test_seasonality_shows_empty_state_for_year_without_data(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.historical.seasonality', ['year' => 2020]))
            ->assertOk()
            ->assertSee('No data available for the selected period.');
    }

    public function test_age_profile_buckets_inventory_by_batch_age(): void
    {
        $profile = AnalyticsMetrics::ageProfile(null, null, '2026-09-14');

        $this->assertSame(['0-30 Days', '31-60 Days', '61-90 Days', '91+ Days'], $profile['labels']);
        $this->assertSame(270, $profile['total']);
        $this->assertSame(100, $profile['values'][0]); // B002 is 25 days old
        $this->assertSame(170, $profile['values'][2]); // B001 is 61 days old

        $this->actingAs($this->user)
            ->get(route('analytics.historical.age-profile', ['as_of' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Inventory by Age Group');
    }

    public function test_gap_report_computes_expected_actual_and_gap(): void
    {
        $gap = AnalyticsMetrics::gapReport();

        $this->assertSame(280, $gap['totalExpected']);
        $this->assertSame(270, $gap['totalActual']);
        $this->assertSame(10, $gap['totalGap']);

        $b001 = collect($gap['rows'])->firstWhere('batch', 'B001');
        $this->assertSame(10, $b001['gap']);

        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report'))
            ->assertOk()
            ->assertSee('Total Gap');
    }

    public function test_gap_report_rejects_unknown_batch_filter(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report', ['batch_id' => 99999]))
            ->assertSessionHasErrors('batch_id');
    }

    public function test_gap_report_page_exposes_print_and_csv_actions(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report'))
            ->assertOk()
            ->assertSee('Print Report')
            ->assertSee('Export CSV')
            ->assertSee('printGapModal')
            ->assertSee('Generated Gap Reports');
    }

    public function test_gap_report_pdf_export_renders_real_data(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics.historical.gap-report.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('Gap Report', $content);
        $this->assertStringContainsString('Batch', $content);
        $this->assertStringContainsString('Expected', $content);
        $this->assertStringContainsString('Actual', $content);
        $this->assertStringContainsString('B001', $content);
        $this->assertStringContainsString('B002', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringContainsString('Expected inventory: 280', $content);
        $this->assertStringContainsString('Actual inventory: 270', $content);
        $this->assertStringContainsString('Total gap: 10', $content);
        $this->assertStringContainsString('180', $content); // B001 expected
        $this->assertStringContainsString('170', $content); // B001 actual
    }

    public function test_gap_report_pdf_export_applies_filters(): void
    {
        $content = $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report.export', ['batch_id' => $this->b001->batch_id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertStringContainsString('Batch: B001', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringNotContainsString('Trinitario', $content);
    }

    public function test_gap_report_csv_export_renders_real_data(): void
    {
        $response = $this->actingAs($this->user)->get(route('analytics.historical.gap-report.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertStringContainsString('Batch,Variety,Expected,Actual,Gap', $csv);
        $this->assertStringContainsString('B001,Criollo,180,170,10', $csv);
        $this->assertStringContainsString('B002,Trinitario,100,100,0', $csv);
        $this->assertStringContainsString('Total,,280,270,10', $csv);
    }

    public function test_gap_report_exports_reject_unknown_batch_filter(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report.export', ['batch_id' => 99999]))
            ->assertSessionHasErrors('batch_id');

        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report.export.csv', ['batch_id' => 99999]))
            ->assertSessionHasErrors('batch_id');
    }

    public function test_user_can_generate_a_report_snapshot(): void
    {
        $this->actingAs($this->user)
            ->post(route('analytics.reports.store'), [
                'analysis_type' => Analytic::TYPE_OPERATIONAL,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $report = Analytic::first();

        $this->assertNotNull($report);
        $this->assertSame($this->user->id, $report->user_id);
        $this->assertSame('operational', $report->analysis_type);
        $this->assertSame('2026-09-01 to 2026-09-30', $report->analysis_period);
        $this->assertSame(270, $report->result_summary['total_active_inventory']);
    }

    public function test_generate_report_rejects_unknown_analysis_type(): void
    {
        $this->actingAs($this->user)
            ->post(route('analytics.reports.store'), ['analysis_type' => 'bogus'])
            ->assertSessionHasErrors('analysis_type');

        $this->assertSame(0, Analytic::count());
    }

    public function test_generate_report_rejects_inverted_date_range(): void
    {
        $this->actingAs($this->user)
            ->post(route('analytics.reports.store'), [
                'analysis_type' => Analytic::TYPE_OPERATIONAL,
                'date_from' => '2026-09-10',
                'date_to' => '2026-09-01',
            ])
            ->assertSessionHasErrors('date_to');

        $this->assertSame(0, Analytic::count());
    }

    public function test_operational_page_shows_generated_report_history(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_GAP_REPORT,
            'analysis_period' => 'All time',
            'result_summary' => ['totalGap' => 10],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSee('Gap Report')
            ->assertSee($this->user->name);
    }

    public function test_stock_ledger_report_summary_contains_ledger_totals(): void
    {
        $summary = AnalyticsMetrics::summaryFor(Analytic::TYPE_STOCK_LEDGER);

        $this->assertSame(2, $summary['batches']);
        $this->assertSame(300, $summary['grafted']);
        $this->assertSame(280, $summary['viable']);
        $this->assertSame(270, $summary['available']);
        $this->assertSame(30, $summary['mortality']);
        $this->assertSame(15, $summary['replanting']);
    }

    public function test_stock_ledger_page_lists_its_own_generated_reports_with_snapshot(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_STOCK_LEDGER,
            'analysis_period' => 'Ledger period XYZ',
            'result_summary' => AnalyticsMetrics::summaryFor(Analytic::TYPE_STOCK_LEDGER),
            'generated_at' => now(),
        ]);

        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'Matrix period XYZ',
            'result_summary' => ['grandTotal' => 30],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger'))
            ->assertOk()
            ->assertSee('Generated Stock Ledger Reports')
            ->assertSee('Ledger period XYZ')
            ->assertSee('Result Summary')
            ->assertSee('300')
            ->assertDontSee('Matrix period XYZ');
    }

    public function test_mortality_matrix_page_lists_its_own_generated_reports(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_STOCK_LEDGER,
            'analysis_period' => 'Ledger period XYZ',
            'result_summary' => ['batches' => 2],
            'generated_at' => now(),
        ]);

        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'Matrix period XYZ',
            'result_summary' => ['grandTotal' => 30, 'totalsByCause' => ['Disease' => 20]],
            'generated_at' => now()->addMinute(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('Generated Root-Cause Reports')
            ->assertSee('Matrix period XYZ')
            ->assertSee('Result Summary')
            ->assertSee('Disease')
            ->assertDontSee('Ledger period XYZ');
    }

    public function test_generating_a_stock_ledger_report_persists_and_shows_on_its_page(): void
    {
        $this->actingAs($this->user)
            ->post(route('analytics.reports.store'), [
                'analysis_type' => Analytic::TYPE_STOCK_LEDGER,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('analytics', [
            'analysis_type' => 'stock_ledger',
            'analysis_period' => '2026-09-01 to 2026-09-30',
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.stock-ledger'))
            ->assertOk()
            ->assertSee('2026-09-01 to 2026-09-30');
    }

    public function test_mortality_matrix_report_summary_includes_cells_and_filters(): void
    {
        $summary = AnalyticsMetrics::summaryFor(Analytic::TYPE_MORTALITY_MATRIX);

        $this->assertSame(['Disease', 'Pest'], $summary['causes']);
        $this->assertSame(['Disease' => 20, 'Pest' => 10], $summary['totalsByCause']);
        $this->assertSame(30, $summary['grandTotal']);
        $this->assertSame(['Disease' => 20], $summary['cellsByVariety']['Criollo']);
        $this->assertSame(['Pest' => 10], $summary['cellsByVariety']['Trinitario']);
        $this->assertSame([
            'batch_id' => null,
            'variety_id' => null,
            'date_from' => null,
            'date_to' => null,
        ], $summary['filters']);
    }

    public function test_mortality_matrix_regressions_flag_increases_even_by_one(): void
    {
        $current = AnalyticsMetrics::mortalityMatrix();

        $baseline = [
            'totalsByCause' => ['Disease' => 19, 'Pest' => 10],
            'cellsByVariety' => ['Criollo' => ['Disease' => 19], 'Trinitario' => ['Pest' => 10]],
        ];

        $regressions = AnalyticsMetrics::mortalityMatrixRegressions($current, $baseline);

        $this->assertTrue($regressions['hasBaseline']);
        $this->assertCount(1, $regressions['causes']);
        $this->assertSame('Disease', $regressions['causes'][0]['cause']);
        $this->assertSame(19, $regressions['causes'][0]['previous']);
        $this->assertSame(20, $regressions['causes'][0]['current']);
        $this->assertSame(1, $regressions['causes'][0]['increase']);

        $this->assertCount(1, $regressions['cells']);
        $this->assertSame('Criollo', $regressions['cells'][0]['variety']);
        $this->assertSame('Disease', $regressions['cells'][0]['cause']);
        $this->assertSame(1, $regressions['cells'][0]['increase']);
    }

    public function test_mortality_matrix_regressions_without_baseline_returns_none(): void
    {
        $regressions = AnalyticsMetrics::mortalityMatrixRegressions(AnalyticsMetrics::mortalityMatrix(), null);

        $this->assertFalse($regressions['hasBaseline']);
        $this->assertSame([], $regressions['causes']);
        $this->assertSame([], $regressions['cells']);
    }

    public function test_mortality_matrix_page_alerts_when_counts_increase_vs_saved_report(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'All time',
            'result_summary' => [
                'causes' => ['Disease', 'Pest'],
                'totalsByCause' => ['Disease' => 19, 'Pest' => 10],
                'grandTotal' => 29,
                'cellsByVariety' => ['Criollo' => ['Disease' => 19], 'Trinitario' => ['Pest' => 10]],
                'filters' => ['batch_id' => null, 'variety_id' => null, 'date_from' => null, 'date_to' => null],
            ],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('Mortality increase detected')
            ->assertSee('table-danger', false);
    }

    public function test_mortality_matrix_page_has_no_alert_without_an_increase(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'All time',
            'result_summary' => [
                'causes' => ['Disease', 'Pest'],
                'totalsByCause' => ['Disease' => 25, 'Pest' => 10],
                'grandTotal' => 35,
                'cellsByVariety' => ['Criollo' => ['Disease' => 25], 'Trinitario' => ['Pest' => 10]],
                'filters' => ['batch_id' => null, 'variety_id' => null, 'date_from' => null, 'date_to' => null],
            ],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertDontSee('Mortality increase detected');
    }

    public function test_mortality_matrix_page_ignores_saved_report_with_different_filters(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'All time',
            'result_summary' => [
                'causes' => ['Disease'],
                'totalsByCause' => ['Disease' => 1],
                'grandTotal' => 1,
                'cellsByVariety' => ['Criollo' => ['Disease' => 1]],
                'filters' => ['batch_id' => $this->b001->batch_id, 'variety_id' => null, 'date_from' => null, 'date_to' => null],
            ],
            'generated_at' => now(),
        ]);

        // Current view has no batch filter, so the batch-scoped snapshot must not match.
        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertDontSee('Mortality increase detected');
    }

    public function test_mortality_matrix_page_alerts_using_matching_batch_snapshot(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'All time',
            'result_summary' => [
                'causes' => ['Disease'],
                'totalsByCause' => ['Disease' => 1],
                'grandTotal' => 1,
                'cellsByVariety' => ['Criollo' => ['Disease' => 1]],
                'filters' => ['batch_id' => $this->b001->batch_id, 'variety_id' => null, 'date_from' => null, 'date_to' => null],
            ],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix', ['batch_id' => $this->b001->batch_id]))
            ->assertOk()
            ->assertSee('Mortality increase detected');
    }

    public function test_mortality_matrix_page_shows_a_warning_toast_when_counts_increase(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'All time',
            'result_summary' => [
                'causes' => ['Disease', 'Pest'],
                'totalsByCause' => ['Disease' => 19, 'Pest' => 10],
                'grandTotal' => 29,
                'cellsByVariety' => ['Criollo' => ['Disease' => 19], 'Trinitario' => ['Pest' => 10]],
                'filters' => ['batch_id' => null, 'variety_id' => null, 'date_from' => null, 'date_to' => null],
            ],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('text-bg-warning', false)
            ->assertSee('Mortality increased since the last saved Root-Cause Matrix report')
            ->assertSee('Disease 19 → 20 (+1)');
    }

    public function test_mortality_matrix_page_has_no_warning_toast_without_an_increase(): void
    {
        Analytic::create([
            'user_id' => $this->user->id,
            'analysis_type' => Analytic::TYPE_MORTALITY_MATRIX,
            'analysis_period' => 'All time',
            'result_summary' => [
                'causes' => ['Disease', 'Pest'],
                'totalsByCause' => ['Disease' => 25, 'Pest' => 10],
                'grandTotal' => 35,
                'cellsByVariety' => ['Criollo' => ['Disease' => 25], 'Trinitario' => ['Pest' => 10]],
                'filters' => ['batch_id' => null, 'variety_id' => null, 'date_from' => null, 'date_to' => null],
            ],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertDontSee('text-bg-warning', false)
            ->assertDontSee('Mortality increased since the last saved Root-Cause Matrix report');
    }

    public function test_mortality_matrix_page_has_no_warning_toast_without_a_baseline(): void
    {
        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertDontSee('text-bg-warning', false)
            ->assertDontSee('Mortality increased since the last saved Root-Cause Matrix report');
    }
}
