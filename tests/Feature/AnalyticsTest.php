<?php

namespace Tests\Feature;

use App\Models\Analytic;
use App\Models\Batch;
use App\Models\MortalityRecord;
use App\Models\RegraftRecord;
use App\Models\ScionVariety;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Analytics/report coverage on the revised 15-entity model.
 *
 * Fixture (real, non-zero mortality and regraft so the Gap Report has genuine
 * data to verify against):
 *   B001 / Criollo   grafted 200, available 170, grafted 2026-07-15
 *   B002 / Trinitario grafted 100, available 100, grafted 2026-08-20
 *   Mortality:    B001 Disease 25, B002 Pest 10  (total 35)
 *   Regraft:      B001 15
 *
 * Corrected Gap formula (Session BS): gap = Batch::effectiveMortalityQuantity()
 * (recorded mortality), NOT total_grafted − available − stock_out (an identity
 * that always resolves to 0 because Stock Out is already netted out of
 * `available_count`). B001 gap = 25, B002 gap = 10, total = 35.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ScionVariety $criollo;

    private ScionVariety $trinitario;

    private Batch $b001;

    private Batch $b002;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->criollo = ScionVariety::create(['scion_variety_name' => 'Criollo']);
        $this->trinitario = ScionVariety::create(['scion_variety_name' => 'Trinitario']);

        $this->b001 = $this->createBatch($this->criollo, 'B001', 200, 170, '2026-07-15');
        $this->b002 = $this->createBatch($this->trinitario, 'B002', 100, 100, '2026-08-20');

        MortalityRecord::create([
            'batch_id' => $this->b001->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 25,
            'cause' => 'Disease',
            'recorded_at' => '2026-09-01',
        ]);

        MortalityRecord::create([
            'batch_id' => $this->b002->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 10,
            'cause' => 'Pest',
            'recorded_at' => '2026-09-01',
        ]);

        RegraftRecord::create([
            'batch_id' => $this->b001->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 15,
            'regraft_date' => '2026-09-03',
            'remark' => 'Replaced disease losses.',
        ]);

        // Phase 7B: the batch's cached `regraft` counter is the authoritative
        // per-batch regrafted total used by the Regraft Deficit formula.
        $this->b001->update(['regraft' => 15]);
    }

    private function createBatch(
        ScionVariety $variety,
        string $code,
        int $totalGrafted,
        int $available,
        string $graftedDate,
    ): Batch {
        return Batch::create([
            'scion_variety_id' => $variety->scion_variety_id,
            'user_id' => $this->user->id,
            'batch_code' => $code,
            'total_grafted' => $totalGrafted,
            'regraft' => 0,
            'available_count' => $available,
            'grafted_date' => $graftedDate,
            'created_at' => now(),
            'status' => Batch::STATUS_ACTIVE,
        ]);
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
            ->assertSee('270')    // total active inventory (170 + 100)
            ->assertSee('12.28')  // crude mortality rate = 35 / (200-15 + 100) = 35/285
            ->assertSee('20');    // total regraft deficit (B001 10 + B002 10)
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
        $this->assertStringContainsString('12.28%', $content);
        $this->assertStringContainsString('Regraft Deficit', $content);
        $this->assertStringContainsString('Mortality Trend', $content);
        $this->assertStringContainsString('Inventory by Scion Variety', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringContainsString('Trinitario', $content);
        $this->assertStringContainsString('Survival vs Mortality', $content);
        $this->assertStringContainsString('Surviving', $content);
        $this->assertStringContainsString('250', $content); // surviving = live population 285 - mortality 35
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
        $this->assertStringContainsString('12.28%', $csv);
        $this->assertStringContainsString('Mortality Trend (Last 6 Months)', $csv);
        $this->assertStringContainsString('Inventory by Scion Variety', $csv);
        $this->assertStringContainsString('Regraft Deficit by Batch', $csv);
        $this->assertStringContainsString('Survival vs Mortality', $csv);
        $this->assertStringContainsString('Criollo', $csv);
        $this->assertStringContainsString('B001', $csv);
        $this->assertStringContainsString('270', $csv);
    }

    public function test_centralized_metrics_return_real_values(): void
    {
        $this->assertSame(270, AnalyticsMetrics::totalActiveInventory());
        $this->assertSame(20, AnalyticsMetrics::totalRegraftDeficit());
        // Denominator = SUM(total_grafted - regraft) = (200-15) + 100 = 285;
        // 35 / 285 = 12.28%.
        $this->assertSame(12.28, AnalyticsMetrics::crudeMortalityRate());

        $components = AnalyticsMetrics::mortalityRateComponents();
        $this->assertSame(35, $components['numerator']);
        $this->assertSame(285, $components['denominator']);

        $survival = AnalyticsMetrics::survivalVsMortality();
        $this->assertSame(285, $survival['totalGrafted']);
        $this->assertSame(35, $survival['mortality']);
        $this->assertSame(250, $survival['surviving']);
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
            ->assertSee('Batch B001')
            ->assertDontSee('Batch B002');
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
        // Real batches, scion varieties and the totals strip (no fabricated rows).
        $this->assertStringContainsString('Cacao Tree Stock Ledger', $content);
        $this->assertStringContainsString('B001', $content);
        $this->assertStringContainsString('B002', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringContainsString('Grafted: 300', $content);
        $this->assertStringContainsString('Mortality: 35', $content);
        $this->assertStringContainsString('Regraft: 15', $content);
        $this->assertStringContainsString('Ready to Plant: 250', $content);
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

    public function test_mortality_matrix_groups_scion_variety_by_cause(): void
    {
        $matrix = AnalyticsMetrics::mortalityMatrix();

        $this->assertSame(['Disease', 'Pest'], $matrix['causes']);
        $this->assertSame(35, $matrix['grandTotal']);
        $this->assertSame(25, $matrix['totalsByCause']['Disease']);
        $this->assertSame(10, $matrix['totalsByCause']['Pest']);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('Disease')
            ->assertSee('Pest')
            ->assertSee('Criollo');
    }

    public function test_mortality_matrix_effective_mortality_equals_recorded_quantity(): void
    {
        // Adjustments were removed with the revised model, so the matrix total
        // must equal the raw sum of the Mortality_Record rows (RULE 6).
        $matrix = AnalyticsMetrics::mortalityMatrix();

        $recorded = (int) MortalityRecord::query()->sum('quantity');

        $this->assertSame($recorded, $matrix['grandTotal']);
        $this->assertSame(
            $recorded,
            (int) collect($matrix['rows'])->sum('total'),
        );
    }

    public function test_mortality_matrix_collapses_others_specifications_into_one_cause(): void
    {
        // Two distinct `Others: <description>` rows must roll up into a single
        // `Others` cause column — the description is only a specification.
        MortalityRecord::create([
            'batch_id' => $this->b001->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 7,
            'cause' => 'Others: Anthracnose infection',
            'recorded_at' => '2026-09-05',
        ]);

        MortalityRecord::create([
            'batch_id' => $this->b002->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 4,
            'cause' => 'Others: Rat damage',
            'recorded_at' => '2026-09-06',
        ]);

        $matrix = AnalyticsMetrics::mortalityMatrix();

        $this->assertContains('Others', $matrix['causes']);
        $this->assertNotContains('Others: Anthracnose infection', $matrix['causes']);
        $this->assertNotContains('Others: Rat damage', $matrix['causes']);
        $this->assertSame(11, $matrix['totalsByCause']['Others']);
        $this->assertSame(46, $matrix['grandTotal']);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('Others')
            ->assertDontSee('Anthracnose')
            ->assertDontSee('Rat damage');
    }

    public function test_mortality_matrix_collapses_others_in_pdf_and_csv_exports(): void
    {
        MortalityRecord::create([
            'batch_id' => $this->b001->batch_id,
            'user_id' => $this->user->id,
            'quantity' => 7,
            'cause' => 'Others: Anthracnose infection',
            'recorded_at' => '2026-09-05',
        ]);

        $response = $this->actingAs($this->user)->get(route('analytics.mortality-matrix.export.csv'));
        $response->assertOk();
        $csv = $response->getContent();

        $this->assertStringContainsString('Others', $csv);
        $this->assertStringNotContainsString('Anthracnose', $csv);
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
        $this->assertStringContainsString('Total mortality: 35', $content);
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
        $this->assertStringContainsString('Criollo,25,0,25', $csv);
        $this->assertStringContainsString('Trinitario,0,10,10', $csv);
        $this->assertStringContainsString('Grand Total', $csv);
        $this->assertStringContainsString(',25,10,35', $csv);
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
        $this->assertSame(35, $heatmap['total']);
        $this->assertSame(25, $heatmap['max']);

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

    public function test_gap_report_computes_expected_available_and_effective_mortality_gap(): void
    {
        // Part B: the Gap formula was corrected to Batch::effectiveMortalityQuantity()
        // (recorded mortality). It is NOT total_grafted - available - stock_out, which
        // is an identity that always equals 0 because Stock Out is already netted out
        // of `available_count` on every validated release.
        $gap = AnalyticsMetrics::gapReport();

        // Cross-batch total counts each plant once: SUM(total_grafted - regraft)
        // = (200 - 15) + (100 - 0) = 285, NOT the raw total_grafted 200 + 100.
        $this->assertSame(285, $gap['totalExpected']);
        $this->assertSame(270, $gap['totalActual']);     // available 170 + 100
        $this->assertSame(35, $gap['totalGap']);         // mortality 25 + 10

        $b001 = collect($gap['rows'])->firstWhere('batch', 'B001');
        $this->assertSame(25, $b001['gap']);
        $this->assertSame(25, $this->b001->effectiveMortalityQuantity());

        $b002 = collect($gap['rows'])->firstWhere('batch', 'B002');
        $this->assertSame(10, $b002['gap']);

        // The gap total must equal the recorded mortality — never 0 here.
        $this->assertSame((int) MortalityRecord::query()->sum('quantity'), $gap['totalGap']);
        $this->assertNotSame(0, $gap['totalGap']);

        $this->actingAs($this->user)
            ->get(route('analytics.historical.gap-report'))
            ->assertOk()
            ->assertSee('Total Gap')
            ->assertSee("Expected counts each plant once; a regrafted plant's quantity is not added again under its new sub-batch.", false);
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
        $this->assertStringContainsString('Available', $content);
        $this->assertStringContainsString('B001', $content);
        $this->assertStringContainsString('B002', $content);
        $this->assertStringContainsString('Criollo', $content);
        $this->assertStringContainsString('Expected: 285', $content);
        $this->assertStringContainsString('Available: 270', $content);
        // FPDF escapes the parentheses in the label.
        $this->assertStringContainsString('Gap \(effective mortality\): 35', $content);
        $this->assertStringContainsString('200', $content); // B001 expected (total grafted)
        $this->assertStringContainsString('170', $content); // B001 available
        $this->assertStringContainsString("Expected counts each plant once; a regrafted plant's quantity is not added again under its new sub-batch.", $content);
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

        // fputcsv quotes headers containing spaces/parentheses.
        $this->assertStringContainsString('Batch,"Parent Batch",Variety,Expected,Available,"Pulled Out","Gap (Effective Mortality)"', $csv);
        $this->assertStringContainsString('B001,,Criollo,200,170,0,25', $csv);
        $this->assertStringContainsString('B002,,Trinitario,100,100,0,10', $csv);
        $this->assertStringContainsString('Total,,,285,270,0,35', $csv);
        $this->assertStringContainsString("Expected counts each plant once; a regrafted plant's quantity is not added again under its new sub-batch.", $csv);
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
            'result_summary' => ['totalGap' => 35],
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
        $this->assertSame(35, $summary['mortality']);
        $this->assertSame(15, $summary['regraft']);
        $this->assertSame(250, $summary['ready_to_plant']);
        $this->assertSame(0, $summary['pullout']);
        $this->assertSame(0, $summary['seedlings']);
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
            'result_summary' => ['grandTotal' => 35],
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
            'result_summary' => ['grandTotal' => 35, 'totalsByCause' => ['Disease' => 25]],
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
        $this->assertSame(['Disease' => 25, 'Pest' => 10], $summary['totalsByCause']);
        $this->assertSame(35, $summary['grandTotal']);
        $this->assertSame(['Disease' => 25], $summary['cellsByVariety']['Criollo']);
        $this->assertSame(['Pest' => 10], $summary['cellsByVariety']['Trinitario']);
        $this->assertSame([
            'batch_id' => null,
            'scion_variety_id' => null,
            'date_from' => null,
            'date_to' => null,
        ], $summary['filters']);
    }

    public function test_mortality_matrix_regressions_flag_increases_even_by_one(): void
    {
        $current = AnalyticsMetrics::mortalityMatrix();

        $baseline = [
            'totalsByCause' => ['Disease' => 24, 'Pest' => 10],
            'cellsByVariety' => ['Criollo' => ['Disease' => 24], 'Trinitario' => ['Pest' => 10]],
        ];

        $regressions = AnalyticsMetrics::mortalityMatrixRegressions($current, $baseline);

        $this->assertTrue($regressions['hasBaseline']);
        $this->assertCount(1, $regressions['causes']);
        $this->assertSame('Disease', $regressions['causes'][0]['cause']);
        $this->assertSame(24, $regressions['causes'][0]['previous']);
        $this->assertSame(25, $regressions['causes'][0]['current']);
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
                'totalsByCause' => ['Disease' => 24, 'Pest' => 10],
                'grandTotal' => 34,
                'cellsByVariety' => ['Criollo' => ['Disease' => 24], 'Trinitario' => ['Pest' => 10]],
                'filters' => ['batch_id' => null, 'scion_variety_id' => null, 'date_from' => null, 'date_to' => null],
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
                'filters' => ['batch_id' => null, 'scion_variety_id' => null, 'date_from' => null, 'date_to' => null],
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
                'filters' => ['batch_id' => $this->b001->batch_id, 'scion_variety_id' => null, 'date_from' => null, 'date_to' => null],
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
                'filters' => ['batch_id' => $this->b001->batch_id, 'scion_variety_id' => null, 'date_from' => null, 'date_to' => null],
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
                'totalsByCause' => ['Disease' => 24, 'Pest' => 10],
                'grandTotal' => 34,
                'cellsByVariety' => ['Criollo' => ['Disease' => 24], 'Trinitario' => ['Pest' => 10]],
                'filters' => ['batch_id' => null, 'scion_variety_id' => null, 'date_from' => null, 'date_to' => null],
            ],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('analytics.mortality-matrix'))
            ->assertOk()
            ->assertSee('text-bg-warning', false)
            ->assertSee('Mortality increased since the last saved Root-Cause Matrix report')
            ->assertSee('Disease 24 → 25 (+1)');
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
                'filters' => ['batch_id' => null, 'scion_variety_id' => null, 'date_from' => null, 'date_to' => null],
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
