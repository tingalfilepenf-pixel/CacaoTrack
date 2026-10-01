<?php

namespace App\Support;

use FPDF;

/**
 * Printable "Cacao Tree Stock Ledger" PDF (FPDF, landscape).
 *
 * Part C: the ledger is a MONTHLY GRID grouped by month with dynamic
 * per-Scion-Variety columns, matching the client's real manual ledger:
 *
 *   | Date | Description | Seedlings | Grafted (per variety) | Mortality
 *   | (per variety) | Re Graft (per variety) | Ready to Plant (per variety)
 *   | Pullout (per variety) |
 *
 * Every value is passed in by the controller from the centralized
 * AnalyticsMetrics::stockLedgerGrid() (RULE 5/6/12). It never invents a row or
 * a total and needs no report table.
 *
 * NOTE: "Ready to Plant" is mapped to the batch's live `available_count`
 * (flagged as an unconfirmed interpretation — see AnalyticsMetrics).
 *
 * Non Latin-1 characters are transliterated so the core FPDF fonts render.
 */
class StockLedgerPdf extends FPDF
{
    private const TABLE_WIDTH = 273.0;

    private const DATE_W = 17.0;

    private const DESC_W = 40.0;

    private const SEEDLINGS_W = 20.0;

    private const SECTION_W = 39.2;

    private const HEADER_H = 10.6;

    private const ROW_H = 5.4;

    /** @var array<string,float> Nested batch-summary column widths (sum = TABLE_WIDTH). */
    private const SUMMARY_COLUMNS = [
        'batch' => 46.0,
        'parent' => 26.0,
        'variety' => 40.0,
        'total_grafted' => 26.0,
        'available' => 26.0,
        'regraft' => 24.0,
        'mortality' => 26.0,
        'stock_out' => 30.0,
        'grafted_date' => 29.0,
    ];

    /** Brand palette (restrained agricultural green + neutral greys). */
    private const BRAND = [30, 61, 43];

    private const BRAND_LIGHT = [233, 240, 234];

    private const ZEBRA = [247, 249, 247];

    private const BORDER = [206, 212, 206];

    private const TEXT = [33, 37, 41];

    private const MUTED = [108, 117, 125];

    private const DANGER = [176, 42, 55];

    private const WARNING = [176, 84, 10];

    private string $currentMonthLabel = '';

    private int $varietyCount = 1;

    private float $colW = self::SECTION_W;

    private float $fontSize = 6.5;

    private bool $summaryMode = false;

    /**
     * @param  array<int,array{id:int,name:string}>  $varieties
     * @param  array<int,array{key:string,label:string,rows:array<int,array<string,mixed>>,totals:array<string,mixed>}>  $months
     * @param  array<string,mixed>  $grandTotals
     * @param  array<string,int>  $summary
     * @param  array{rows:list<array<string,mixed>>,totals:array<string,int>}  $batchSummary
     */
    public function __construct(
        private readonly string $filterLabel,
        private readonly string $generatedBy,
        private readonly string $generatedAt,
        private readonly array $varieties,
        private readonly array $months,
        private readonly array $grandTotals,
        private readonly array $summary,
        private readonly array $batchSummary = ['rows' => [], 'totals' => []],
    ) {
        parent::__construct('L', 'mm', 'A4');

        $this->varietyCount = max(1, count($this->varieties));
        $this->colW = self::SECTION_W / $this->varietyCount;
        $this->fontSize = $this->colW < 7.0 ? 5.5 : 6.5;

        $this->SetTitle('Cacao Tree Stock Ledger - '.$filterLabel);
        $this->SetAuthor('CacaoTrack');
        $this->SetCreator('CacaoTrack');
        $this->SetMargins(12, 14, 12);
        $this->SetAutoPageBreak(false);
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
            if ($this->summaryMode) {
                $this->drawSummaryContinuationHeader();

                return;
            }

            $this->drawContinuationHeader();

            return;
        }

        $bandY = 10.0;
        $this->SetY($bandY);
        $this->SetFillColor(...self::BRAND);
        $this->Rect(12, $bandY, self::TABLE_WIDTH, 13, 'F');

        $this->SetXY(15, $bandY + 2.4);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(120, 8, $this->txt('CacaoTrack'), 0, 0, 'L');

        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(235, 240, 235);
        $this->SetXY(165, $bandY + 2.4);
        $this->Cell(117, 4, $this->txt('Generated '.$this->generatedAt), 0, 0, 'R');
        $this->SetXY(165, $bandY + 6.8);
        $this->Cell(117, 4, $this->txt('By '.$this->generatedBy), 0, 0, 'R');

        $this->SetY($bandY + 15.5);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...self::TEXT);
        $this->Cell(150, 6, $this->txt('Cacao Tree Stock Ledger'), 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(123, 6, $this->txt($this->filterLabel), 0, 1, 'R');

        $this->drawSummaryStrip();
        $this->SetY($this->GetY() + 2.0);
        $this->SetX(12);
    }

    public function footer(): void
    {
        $this->SetY(-13);
        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() - 1, 285, $this->GetY() - 1);

        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(...self::MUTED);
        $this->SetX(12);
        $this->Cell(200, 5, $this->txt('CacaoTrack - Cacao Tree Stock Ledger (monthly grid)'), 0, 0, 'L');
        $this->Cell(73, 5, $this->txt('Page '.$this->PageNo().' of {nb}'), 0, 0, 'R');
    }

    /**
     * Draw every month's grid. Real data only (RULE 5/12).
     */
    public function renderBody(): void
    {
        if ($this->months === []) {
            $this->SetFont('Helvetica', 'I', 10);
            $this->SetTextColor(...self::MUTED);
            $this->SetFillColor(...self::BRAND_LIGHT);
            $this->Cell(self::TABLE_WIDTH, 14, $this->txt('No data available for the selected period.'), 1, 1, 'C', true);

            return;
        }

        foreach ($this->months as $month) {
            $this->drawMonthHeader((string) $month['label']);

            foreach ($month['rows'] as $index => $row) {
                $this->drawEventRow($row, $index + 1);
            }

            $this->drawTotalsRow('TOTAL — '.$month['label'], $month['totals'], false);
        }

        $this->drawTotalsRow('GRAND TOTAL', $this->grandTotals, true);

        $this->drawLedgerFootnote();
        $this->drawBatchSummary();
    }

    /**
     * The ledger footnote that explains why sub-batch quantities are shown under
     * Regraft rather than Grafted (Phase 7C).
     */
    private function drawLedgerFootnote(): void
    {
        $this->ensureSummarySpace(9);

        $this->SetX(12);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(...self::MUTED);
        $this->MultiCell(
            self::TABLE_WIDTH,
            3.6,
            $this->txt('Sub-batch quantities appear under Regraft, not Grafted. A regraft from a sub-batch is a further movement of the same plants; no cross-column total is implied. Ready to Plant reflects each batch\'s current balance, shown under its original grafted month — not activity within that month. A batch\'s balance does not move to a later month\'s row as time passes.'),
            0,
            'L',
        );
        $this->SetTextColor(...self::TEXT);
    }

    /**
     * Nested per-batch summary below the grid (PRD §9.3): parent batches and
     * their regraft sub-batches with the parent link. Real data only.
     */
    private function drawBatchSummary(): void
    {
        $rows = $this->batchSummary['rows'] ?? [];

        $this->ensureSummarySpace(6.2);
        $this->SetFillColor(...self::BRAND);
        $this->Rect(12, $this->GetY(), self::TABLE_WIDTH, 6.2, 'F');
        $this->SetXY(14, $this->GetY() + 0.8);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(self::TABLE_WIDTH - 4, 4.6, $this->txt('Batch summary (nested)'), 0, 1, 'L');
        $this->SetTextColor(...self::TEXT);
        $this->SetX(12);

        // From here on, continuation pages continue the summary (not the grid).
        $this->summaryMode = true;

        $this->drawSummaryHeader();

        if ($rows === []) {
            $this->ensureSummarySpace(6.0);
            $this->SetFont('Helvetica', 'I', 8);
            $this->SetTextColor(...self::MUTED);
            $this->SetX(12);
            $this->Cell(self::TABLE_WIDTH, 6.0, $this->txt('No batches for the selected filters.'), 1, 1, 'C');
            $this->SetTextColor(...self::TEXT);

            return;
        }

        $zebra = false;

        foreach ($rows as $row) {
            $this->ensureSummarySpace(self::ROW_H);
            $zebra = ! $zebra;
            $this->drawSummaryRow($row, $zebra);
        }

        $this->ensureSummarySpace(6.2);
        $this->drawSummaryTotals($this->batchSummary['totals'] ?? []);
        $this->drawSummaryFootnote();
    }

    /**
     * Explains why the summary totals row is smaller than the sum of the
     * visible per-row `total_grafted` values (each plant counted once).
     */
    private function drawSummaryFootnote(): void
    {
        $this->ensureSummarySpace(6.4);

        $this->SetX(12);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(...self::MUTED);
        $this->MultiCell(
            self::TABLE_WIDTH,
            3.6,
            $this->txt("Total counts each plant once; a regrafted plant's quantity is not added again under its new sub-batch."),
            0,
            'L',
        );
        $this->SetTextColor(...self::TEXT);
    }

    private function drawSummaryContinuationHeader(): void
    {
        $this->SetY(12);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6, $this->txt('Batch summary (nested) - '.$this->filterLabel), 0, 1, 'L');

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() + 0.5, 285, $this->GetY() + 0.5);
        $this->SetY($this->GetY() + 2.5);
        $this->SetX(12);
        $this->drawSummaryHeader();
    }

    private function drawSummaryHeader(): void
    {
        $headers = [
            'batch' => 'Batch or sub-batch',
            'parent' => 'Parent Batch',
            'variety' => 'Scion Variety',
            'total_grafted' => 'Total Grafted',
            'available' => 'Available',
            'regraft' => 'Regraft',
            'mortality' => 'Mortality',
            'stock_out' => 'Stock Out',
            'grafted_date' => 'Date Grafted',
        ];

        if ($this->GetY() + 6.4 > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        $x = 12.0;
        $y = $this->GetY();

        $this->SetFont('Helvetica', 'B', 6.8);
        $this->SetDrawColor(...self::BRAND);
        $this->SetLineWidth(0.2);

        foreach ($headers as $key => $label) {
            $align = in_array($key, ['total_grafted', 'available', 'regraft', 'mortality', 'stock_out'], true) ? 'R' : 'L';
            [$size, $text] = $this->fitText($label, self::SUMMARY_COLUMNS[$key] - 1.4, 'B', 6.8);
            $this->SetFillColor(...self::BRAND);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', $size);
            $this->SetXY($x, $y);
            $this->Cell(self::SUMMARY_COLUMNS[$key], 6.4, $this->txt($text), 1, 0, $align, true);
            $x += self::SUMMARY_COLUMNS[$key];
        }

        $this->SetXY(12, $y + 6.4);
        $this->SetTextColor(...self::TEXT);
        $this->SetDrawColor(...self::BORDER);
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function drawSummaryRow(array $row, bool $zebra): void
    {
        $x = 12.0;
        $y = $this->GetY();
        $height = self::ROW_H;

        if ($zebra) {
            $this->SetFillColor(...self::ZEBRA);
            $this->Rect($x, $y, self::TABLE_WIDTH, $height, 'F');
        }

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.2);

        $cursor = $x;

        foreach (self::SUMMARY_COLUMNS as $width) {
            $this->Rect($cursor, $y, $width, $height, $zebra ? 'DF' : 'D');
            $cursor += $width;
        }

        $label = str_repeat('   ', (int) ($row['depth'] ?? 0)).'> '.$row['batch'];

        if (! empty($row['parent_missing']) && ! empty($row['parent'])) {
            $label .= ' (parent: '.$row['parent'].')';
        }

        $cells = [
            'batch' => [$label, 'L', self::TEXT, 'B'],
            'parent' => [(string) ($row['parent'] ?? '—'), 'L', self::MUTED, ''],
            'variety' => [(string) ($row['variety'] ?? '—'), 'L', self::TEXT, ''],
            'total_grafted' => [number_format((int) $row['total_grafted']), 'R', self::TEXT, ''],
            'available' => [number_format((int) $row['available']), 'R', self::TEXT, ''],
            'regraft' => [number_format((int) $row['regraft']), 'R', self::WARNING, ''],
            'mortality' => [number_format((int) $row['mortality']), 'R', self::DANGER, ''],
            'stock_out' => [number_format((int) $row['stock_out']), 'R', self::TEXT, ''],
            'grafted_date' => [(string) ($row['grafted_date'] ?? '—'), 'L', self::MUTED, ''],
        ];

        $cursor = $x;

        foreach ($cells as $key => [$text, $align, $color, $style]) {
            $cellWidth = self::SUMMARY_COLUMNS[$key] - 2;
            [$size, $display] = $this->fitText($text, $cellWidth - 0.4, $style, 7.0);
            $this->SetFont('Helvetica', $style, $size);
            $this->SetTextColor(...$color);
            $this->SetXY($cursor + 1, $y + (($height - 4) / 2));
            $this->Cell($cellWidth, 4, $this->txt($display), 0, 0, $align);
            $cursor += self::SUMMARY_COLUMNS[$key];
        }

        $this->SetXY($x, $y + $height);
        $this->SetTextColor(...self::TEXT);
    }

    /**
     * @param  array<string,int>  $totals
     */
    private function drawSummaryTotals(array $totals): void
    {
        $height = 6.2;
        $x = 12.0;
        $y = $this->GetY();

        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect($x, $y, self::TABLE_WIDTH, $height, 'F');
        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.2);

        $cursor = $x;

        foreach (self::SUMMARY_COLUMNS as $width) {
            $this->Rect($cursor, $y, $width, $height, 'DF');
            $cursor += $width;
        }

        $combined = self::SUMMARY_COLUMNS['batch'] + self::SUMMARY_COLUMNS['parent'] + self::SUMMARY_COLUMNS['variety'];
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetTextColor(...self::TEXT);
        $this->SetXY($x + 1, $y + (($height - 4) / 2));
        $this->Cell($combined - 2, 4, $this->txt('Total'), 0, 0, 'L');

        $values = [
            'total_grafted' => (int) ($totals['total_grafted'] ?? 0),
            'available' => (int) ($totals['available'] ?? 0),
            'regraft' => (int) ($totals['regraft'] ?? 0),
            'mortality' => (int) ($totals['mortality'] ?? 0),
            'stock_out' => (int) ($totals['stock_out'] ?? 0),
        ];

        $cursor = $x + $combined;

        foreach ($values as $key => $value) {
            $color = match ($key) {
                'regraft' => self::WARNING,
                'mortality' => self::DANGER,
                default => self::TEXT,
            };
            $this->SetTextColor(...$color);
            $this->SetXY($cursor + 1, $y + (($height - 4) / 2));
            $this->Cell(self::SUMMARY_COLUMNS[$key] - 2, 4, number_format($value), 0, 0, 'R');
            $cursor += self::SUMMARY_COLUMNS[$key];
        }

        $this->SetXY($x, $y + $height);
        $this->SetTextColor(...self::TEXT);
    }

    private function ensureSummarySpace(float $height): void
    {
        if ($this->GetY() + $height <= $this->PageBreakTrigger) {
            return;
        }

        $this->AddPage($this->CurOrientation);
        $this->SetX(12);
    }

    /**
     * Fit a string into a cell: shrink the font (down to a floor) so it fits,
     * and only truncate with an ellipsis if it is still too wide at the floor.
     * The caller sets the font; the returned text still needs `txt()`.
     *
     * @return array{0:float,1:string}
     */
    private function fitText(string $text, float $width, string $style, float $size, float $floor = 4.2): array
    {
        $display = $this->txt($text);

        if ($display === '' || $width <= 0) {
            return [$size, $text];
        }

        $this->SetFont('Helvetica', $style, $size);

        if ($this->GetStringWidth($display) <= $width) {
            return [$size, $text];
        }

        $scaled = $size * ($width / $this->GetStringWidth($display));

        if ($scaled >= $floor) {
            return [round($scaled, 2), $text];
        }

        $this->SetFont('Helvetica', $style, $floor);
        $suffix = '...';
        $cut = $display;

        while ($cut !== '' && $this->GetStringWidth($cut.$suffix) > $width) {
            $cut = substr($cut, 0, -1);
        }

        return [$floor, $cut === '' ? '' : $cut.$suffix];
    }

    private function drawSummaryStrip(): void
    {
        $y = $this->GetY() + 1.5;
        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect(12, $y, self::TABLE_WIDTH, 8, 'F');
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::TEXT);
        $this->SetXY(15, $y + 1.8);

        $cells = [
            'Seedlings: %d' => $this->summary['seedlings'] ?? 0,
            'Grafted: %d' => $this->summary['grafted'] ?? 0,
            'Mortality: %d' => $this->summary['mortality'] ?? 0,
            'Regraft: %d' => $this->summary['regraft'] ?? 0,
            'Ready to Plant: %d' => $this->summary['ready_to_plant'] ?? 0,
            'Pullout: %d' => $this->summary['pullout'] ?? 0,
        ];

        foreach ($cells as $label => $value) {
            $this->Cell(45.5, 4.4, $this->txt(sprintf($label, $value)), 0, 0, 'L');
        }

        $this->SetY($y + 8);
    }

    private function drawContinuationHeader(): void
    {
        $this->SetY(12);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6, $this->txt('Cacao Tree Stock Ledger (continued) - '.$this->filterLabel), 0, 1, 'L');

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() + 0.5, 285, $this->GetY() + 0.5);
        $this->SetY($this->GetY() + 2.5);
        $this->SetX(12);

        if ($this->currentMonthLabel !== '') {
            $this->SetFont('Helvetica', 'B', 8.5);
            $this->SetTextColor(...self::BRAND);
            $this->Cell(self::TABLE_WIDTH, 5, $this->txt($this->currentMonthLabel.' (continued)'), 0, 1, 'L');
            $this->SetX(12);
        }

        $this->drawGridHeader();
    }

    private function drawMonthHeader(string $label): void
    {
        $this->currentMonthLabel = $label;

        if ($this->GetY() + self::HEADER_H + 12 > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        $this->SetFillColor(...self::BRAND);
        $this->Rect(12, $this->GetY(), self::TABLE_WIDTH, 6.2, 'F');
        $this->SetXY(14, $this->GetY() + 0.8);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(self::TABLE_WIDTH - 4, 4.6, $this->txt($label), 0, 1, 'L');
        $this->SetX(12);

        $this->drawGridHeader();
    }

    private function drawGridHeader(): void
    {
        $x = 12.0;
        $y = $this->GetY();

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.2);
        $this->SetFillColor(...self::BRAND);
        $this->SetTextColor(255, 255, 255);

        // Spanning header (row 1).
        $this->SetFont('Helvetica', 'B', self::ROW_H >= 6 ? 7 : 6.5);
        $this->SetXY($x, $y);
        $this->Cell(self::DATE_W, self::HEADER_H, $this->txt('Date'), 1, 0, 'C', true);
        $this->Cell(self::DESC_W, self::HEADER_H, $this->txt('Description'), 1, 0, 'C', true);
        $this->Cell(self::SEEDLINGS_W, self::HEADER_H, $this->txt('Seedlings'), 1, 0, 'C', true);

        foreach ($this->sections() as $label) {
            $this->Cell(self::SECTION_W, self::HEADER_H / 2, $this->txt($label), 1, 0, 'C', true);
        }

        // Per-variety header (row 2). Each label is shrunk to fit its column
        // (the columns narrow as more scion varieties are present).
        $this->SetXY($x + self::DATE_W + self::DESC_W + self::SEEDLINGS_W, $y + self::HEADER_H / 2);

        $labelWidth = $this->colW - 0.5;

        foreach ($this->sections() as $label) {
            if ($this->varieties === []) {
                $this->Cell(self::SECTION_W, self::HEADER_H / 2, '', 1, 0, 'C', true);

                continue;
            }

            foreach ($this->varieties as $variety) {
                [$size, $text] = $this->fitText((string) $variety['name'], $labelWidth, 'B', $this->fontSize);
                $this->SetFont('Helvetica', 'B', $size);
                $this->Cell($this->colW, self::HEADER_H / 2, $this->txt($text), 1, 0, 'C', true);
            }
        }

        $this->SetXY($x, $y + self::HEADER_H);
        $this->SetTextColor(...self::TEXT);
        $this->SetDrawColor(...self::BORDER);
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function drawEventRow(array $row, int $number): void
    {
        $description = (string) $row['description'];
        $lines = max(1, $this->nbLines(self::DESC_W - 2, $description));
        $height = max(self::ROW_H, ($lines * 3.6) + 1.6);

        $this->ensureRowSpace($height);

        $x = $this->GetX();
        $y = $this->GetY();
        $fill = $number % 2 === 0;

        if ($fill) {
            $this->SetFillColor(...self::ZEBRA);
            $this->Rect($x, $y, self::TABLE_WIDTH, $height, 'F');
        }

        $this->SetFont('Helvetica', '', 6.5);
        $this->SetTextColor(...self::TEXT);

        $this->vCenter($this->shortDate((string) $row['date']), $x, $y, self::DATE_W, $height, 'C', self::TEXT, $fill);

        $this->Rect($x + self::DATE_W, $y, self::DESC_W, $height, 'D');
        $this->SetXY($x + self::DATE_W + 1, $y + 0.8);
        $this->MultiCell(self::DESC_W - 2, 3.6, $this->txt($description), 0, 'L');

        $seedlings = (int) $row['seedlings'];
        $this->vCenter($seedlings > 0 ? number_format($seedlings) : '', $x + self::DATE_W + self::DESC_W, $y, self::SEEDLINGS_W, $height, 'R', self::TEXT, $fill);

        $sectionX = $x + self::DATE_W + self::DESC_W + self::SEEDLINGS_W;

        foreach ($this->dimensions() as $dimension) {
            if ($this->varieties === []) {
                $this->drawCellBorder($sectionX, $y, self::SECTION_W, $height, $fill);
                $sectionX += self::SECTION_W;

                continue;
            }

            foreach ($this->varieties as $variety) {
                $value = (int) ($row[$dimension][$variety['id']] ?? 0);
                $color = match ($dimension) {
                    'mortality' => self::DANGER,
                    'regraft' => self::WARNING,
                    default => self::TEXT,
                };
                $this->vCenter($value > 0 ? number_format($value) : '', $sectionX, $y, $this->colW, $height, 'R', $color, $fill);
                $sectionX += $this->colW;
            }
        }

        $this->SetXY($x, $y + $height);
    }

    /**
     * @param  array<string,mixed>  $totals
     */
    private function drawTotalsRow(string $label, array $totals, bool $grand): void
    {
        $height = $grand ? 6.4 : 5.8;
        $this->ensureRowSpace($height);

        $x = $this->GetX();
        $y = $this->GetY();

        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect($x, $y, self::TABLE_WIDTH, $height, 'F');
        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.2);

        $this->SetFont('Helvetica', 'B', 6.5);
        $this->SetTextColor(...self::TEXT);
        $this->SetXY($x + 1, $y + (($height - 4) / 2));
        $this->Cell(self::DATE_W + self::DESC_W - 1, 4, $this->txt($label), 0, 0, 'L');

        $this->vCenter(number_format((int) ($totals['seedlings'] ?? 0)), $x + self::DATE_W + self::DESC_W, $y, self::SEEDLINGS_W, $height, 'R', self::TEXT, true);

        $sectionX = $x + self::DATE_W + self::DESC_W + self::SEEDLINGS_W;

        foreach ($this->dimensions() as $dimension) {
            if ($this->varieties === []) {
                $this->drawCellBorder($sectionX, $y, self::SECTION_W, $height, true);
                $sectionX += self::SECTION_W;

                continue;
            }

            foreach ($this->varieties as $variety) {
                $value = (int) ($totals[$dimension][$variety['id']] ?? 0);
                $color = match ($dimension) {
                    'mortality' => self::DANGER,
                    'regraft' => self::WARNING,
                    default => self::BRAND,
                };
                $this->vCenter($value > 0 ? number_format($value) : '0', $sectionX, $y, $this->colW, $height, 'R', $color, true, 'B', $grand ? 7 : 6.5);
                $sectionX += $this->colW;
            }
        }

        $this->SetXY($x, $y + $height);
    }

    private function drawCellBorder(float $x, float $y, float $width, float $height, bool $fill): void
    {
        $this->Rect($x, $y, $width, $height, $fill ? 'DF' : 'D');
    }

    /**
     * @return array<int,string>
     */
    private function sections(): array
    {
        return ['Grafted', 'Mortality', 'Regraft', 'Ready to Plant', 'Pullout'];
    }

    /**
     * @return array<int,string>
     */
    private function dimensions(): array
    {
        return ['grafted', 'mortality', 'regraft', 'ready', 'pullout'];
    }

    private function ensureRowSpace(float $height): void
    {
        if ($this->GetY() + $height <= $this->PageBreakTrigger) {
            return;
        }

        $this->AddPage($this->CurOrientation);
        $this->SetX(12);
        $this->drawGridHeader();
    }

    /**
     * @param  array{0:int,1:int,2:int}  $color
     */
    private function vCenter(string $text, float $x, float $y, float $width, float $height, string $align, array $color, bool $fill, string $style = '', ?float $size = null): void
    {
        [$fitted, $display] = $this->fitText($text, $width - 0.6, $style, $size ?? $this->fontSize);
        $this->SetFont('Helvetica', $style, $fitted);
        $this->SetTextColor(...$color);
        $this->SetFillColor(...($fill ? self::ZEBRA : [255, 255, 255]));
        $this->SetDrawColor(...self::BORDER);
        $this->SetXY($x, $y + (($height - 4) / 2));
        $this->Cell($width, 4, $this->txt($display), 1, 0, $align, true);
    }

    private function shortDate(string $isoDate): string
    {
        $timestamp = strtotime($isoDate);

        return $timestamp ? date('M d', $timestamp) : $isoDate;
    }

    private function nbLines(float $width, string $text): int
    {
        $this->SetFont('Helvetica', '', 6.5);

        $text = str_replace("\r", '', $text);
        $paragraphs = explode("\n", $text);
        $max = 1;

        foreach ($paragraphs as $paragraph) {
            if ($paragraph === '') {
                continue;
            }

            $words = explode(' ', $paragraph);
            $lineWidth = 0.0;
            $lineCount = 1;

            foreach ($words as $word) {
                $wordWidth = $this->GetStringWidth($word);
                $spaceWidth = $lineWidth > 0 ? $this->GetStringWidth(' ') : 0;

                if ($lineWidth + $spaceWidth + $wordWidth > $width) {
                    $lineCount++;
                    $lineWidth = $wordWidth;
                } else {
                    $lineWidth += $spaceWidth + $wordWidth;
                }
            }

            $max = max($max, $lineCount);
        }

        return $max;
    }

    /**
     * Transliterate UTF-8 text to ISO-8859-1 for the core FPDF fonts.
     */
    private function txt(string $text): string
    {
        return (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    }
}
