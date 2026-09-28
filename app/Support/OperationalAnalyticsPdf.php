<?php

namespace App\Support;

use FPDF;

/**
 * Printable "Operational Analytics" report (FPDF, portrait).
 *
 * Covers the panelist-required operational dashboard (PRD §12): the three KPI
 * cards (Total Active Inventory, Crude Mortality Rate, Replanting Deficit) and
 * the tabular values behind each on-screen chart (mortality trend, inventory by
 * variety, replanting deficit by batch, survival vs mortality). Every value is
 * passed in by the controller from the centralized AnalyticsMetrics methods
 * (RULE 5/6/12) — nothing is fabricated and no report table is involved
 * (PRD §9, ERD §9.4).
 *
 * Non Latin-1 characters are transliterated so the core FPDF fonts render.
 */
class OperationalAnalyticsPdf extends FPDF
{
    private const CONTENT_WIDTH = 186.0;

    private const LABEL_WIDTH = 126.0;

    private const VALUE_WIDTH = 60.0;

    private const ROW_HEIGHT = 6.5;

    /** Brand palette (shared with the other report PDFs). */
    private const BRAND = [30, 61, 43];

    private const BRAND_LIGHT = [233, 240, 234];

    private const ZEBRA = [247, 249, 247];

    private const BORDER = [206, 212, 206];

    private const TEXT = [33, 37, 41];

    private const MUTED = [108, 117, 125];

    /**
     * @param  array<int,array{label:string,value:string}>  $kpis
     * @param  array{labels:array<int,string>,values:array<int,int>,total:int}  $mortalityTrend
     * @param  array{labels:array<int,string>,values:array<int,int>}  $inventoryByVariety
     * @param  array{labels:array<int,string>,values:array<int,int>}  $replantingDeficitByBatch
     * @param  array{surviving:int,mortality:int,totalGrafted:int}  $survivalVsMortality
     */
    public function __construct(
        private readonly string $generatedBy,
        private readonly string $generatedAt,
        private readonly array $kpis,
        private readonly array $mortalityTrend,
        private readonly array $inventoryByVariety,
        private readonly array $replantingDeficitByBatch,
        private readonly array $survivalVsMortality,
    ) {
        parent::__construct('P', 'mm', 'A4');

        $this->SetTitle('Operational Analytics');
        $this->SetAuthor('CacaoTrack');
        $this->SetCreator('CacaoTrack');
        $this->SetMargins(12, 14, 12);
        $this->SetAutoPageBreak(true, 18);
        $this->SetCompression(false);
        $this->AliasNbPages();
    }

    public function render(): string
    {
        return $this->Output('S');
    }

    public function header(): void
    {
        if ($this->PageNo() > 1) {
            $this->drawContinuationHeader();

            return;
        }

        $bandY = 10.0;
        $this->SetY($bandY);
        $this->SetFillColor(...self::BRAND);
        $this->Rect(12, $bandY, self::CONTENT_WIDTH, 13, 'F');

        $this->SetXY(15, $bandY + 2.4);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(100, 8, $this->txt('CacaoTrack'), 0, 0, 'L');

        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(235, 240, 235);
        $this->SetXY(105, $bandY + 2.4);
        $this->Cell(90, 4, $this->txt('Generated '.$this->generatedAt), 0, 0, 'R');
        $this->SetXY(105, $bandY + 6.8);
        $this->Cell(90, 4, $this->txt('By '.$this->generatedBy), 0, 0, 'R');

        $this->SetY($bandY + 15.5);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...self::TEXT);
        $this->Cell(110, 6, $this->txt('Operational Analytics'), 0, 0, 'L');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(76, 6, $this->txt('All time · live data'), 0, 1, 'R');

        $this->SetY($this->GetY() + 2.0);
    }

    public function footer(): void
    {
        $this->SetY(-13);
        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() - 1, 198, $this->GetY() - 1);

        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(...self::MUTED);
        $this->SetX(12);
        $this->Cell(130, 5, $this->txt('CacaoTrack - Operational Analytics'), 0, 0, 'L');
        $this->Cell(56, 5, $this->txt('Page '.$this->PageNo().' of {nb}'), 0, 0, 'R');
    }

    /**
     * Draw the KPI strip and the tabular values behind each chart.
     */
    public function renderBody(): void
    {
        // KPI cards (PRD §12).
        $this->drawKpis();
        $this->Ln(3);

        $this->sectionTable(
            'Mortality Trend (Last 6 Months)',
            'Month',
            $this->zip($this->mortalityTrend['labels'], $this->mortalityTrend['values']),
            'Mortality',
        );

        $this->sectionTable(
            'Inventory by Scion Variety',
            'Scion Variety',
            $this->zip($this->inventoryByVariety['labels'], $this->inventoryByVariety['values']),
            'Inventory',
        );

        $this->sectionTable(
            'Regraft Deficit by Batch',
            'Batch',
            $this->zip($this->replantingDeficitByBatch['labels'], $this->replantingDeficitByBatch['values']),
            'Deficit',
        );

        $this->sectionTable('Survival vs Mortality', 'Metric', [
            ['Surviving', $this->survivalVsMortality['surviving']],
            ['Mortality', $this->survivalVsMortality['mortality']],
            ['Total Grafted', $this->survivalVsMortality['totalGrafted']],
        ], 'Trees');
    }

    private function drawKpis(): void
    {
        $this->ensureSpace(24);

        $x = 12.0;
        $y = $this->GetY();
        $boxWidth = 60.0;
        $gap = 3.0;
        $boxHeight = 20.0;

        foreach ($this->kpis as $index => $kpi) {
            $boxX = $x + ($index * ($boxWidth + $gap));

            $this->SetFillColor(...self::BRAND_LIGHT);
            $this->SetDrawColor(...self::BORDER);
            $this->SetLineWidth(0.3);
            $this->Rect($boxX, $y, $boxWidth, $boxHeight, 'DF');

            $this->SetXY($boxX + 3, $y + 2.5);
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(...self::MUTED);
            $this->Cell($boxWidth - 6, 4, $this->txt($kpi['label']), 0, 0, 'L');

            $this->SetXY($boxX + 3, $y + 8.0);
            $this->SetFont('Helvetica', 'B', 16);
            $this->SetTextColor(...self::BRAND);
            $this->Cell($boxWidth - 6, 8, $this->txt($kpi['value']), 0, 0, 'L');
        }

        $this->SetXY($x, $y + $boxHeight);
    }

    /**
     * @param  list<array{0:string,1:int|string}>  $rows
     */
    private function sectionTable(string $title, string $labelHeader, array $rows, string $valueHeader): void
    {
        $this->ensureSpace(8 + self::ROW_HEIGHT + 12);
        $this->Ln(2);

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(self::CONTENT_WIDTH, 6, $this->txt($title), 0, 1, 'L');

        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(...self::BRAND);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...self::BRAND);
        $this->SetLineWidth(0.2);
        $this->Cell(self::LABEL_WIDTH, self::ROW_HEIGHT, $this->txt($labelHeader), 1, 0, 'L', true);
        $this->Cell(self::VALUE_WIDTH, self::ROW_HEIGHT, $this->txt($valueHeader), 1, 1, 'R', true);

        if ($rows === []) {
            $this->SetFont('Helvetica', 'I', 8.5);
            $this->SetTextColor(...self::MUTED);
            $this->SetFillColor(...self::BRAND_LIGHT);
            $this->Cell(self::CONTENT_WIDTH, 9, $this->txt('No data available for the selected period.'), 1, 1, 'C', true);

            return;
        }

        foreach ($rows as $index => $row) {
            if ($this->GetY() + self::ROW_HEIGHT > $this->PageBreakTrigger) {
                $this->AddPage($this->CurOrientation);
            }

            $fill = $index % 2 === 1;
            if ($fill) {
                $this->SetFillColor(...self::ZEBRA);
            }

            $this->SetDrawColor(...self::BORDER);
            $this->SetTextColor(...self::TEXT);
            $this->SetFont('Helvetica', '', 8.5);
            $this->Cell(self::LABEL_WIDTH, self::ROW_HEIGHT, $this->txt((string) $row[0]), 1, 0, 'L', $fill);
            $this->Cell(self::VALUE_WIDTH, self::ROW_HEIGHT, $this->txt(number_format((int) $row[1])), 1, 1, 'R', $fill);
        }
    }

    /**
     * @param  array<int,string>  $labels
     * @param  array<int,int>  $values
     * @return list<array{0:string,1:int}>
     */
    private function zip(array $labels, array $values): array
    {
        $rows = [];

        foreach ($labels as $index => $label) {
            $rows[] = [$label, (int) ($values[$index] ?? 0)];
        }

        return $rows;
    }

    private function drawContinuationHeader(): void
    {
        $this->SetY(12);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6, $this->txt('Operational Analytics (continued)'), 0, 1, 'L');

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() + 0.5, 198, $this->GetY() + 0.5);
        $this->SetY($this->GetY() + 3.0);
    }

    private function ensureSpace(float $height): void
    {
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    /**
     * Transliterate UTF-8 text to ISO-8859-1 for the core FPDF fonts.
     */
    private function txt(string $text): string
    {
        return (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    }
}
