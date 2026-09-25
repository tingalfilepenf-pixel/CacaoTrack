<?php

namespace App\Support;

use FPDF;

/**
 * Printable "Gap Report" PDF (FPDF, landscape).
 *
 * Historical deliverable (PRD §9.5): per batch, expected = `viable_count`,
 * actual = `available_count`, pulled out = summed Stock_Out_Detail quantity, and
 * gap = `Batch::effectiveMortalityQuantity()` (recorded mortality still
 * unreflected in `available_count`). The old `expected − actual − pulled out`
 * identity always resolves to 0 because Stock Out is already netted out of the
 * live balance on every validated release. `expected`/`actual`/`pulled_out` are
 * retained as context columns only. Every value is passed in by the
 * controller from the centralized AnalyticsMetrics::gapReport() (RULE 5/6/12).
 * The report is generated live — no report table is involved (PRD §9, ERD §9.4).
 *
 * Non Latin-1 characters are transliterated so the core FPDF fonts render.
 */
class GapReportPdf extends FPDF
{
    private const TABLE_WIDTH = 273.0;

    private const LINE_HEIGHT = 4.6;

    private const ROW_PADDING = 1.8;

    /** @var array<string,float> Column widths in millimetres (sum = TABLE_WIDTH). */
    private const COLUMNS = [
        'batch' => 44.0,
        'variety' => 62.0,
        'expected' => 41.0,
        'actual' => 41.0,
        'pulled_out' => 44.0,
        'gap' => 41.0,
    ];

    /** Brand palette (shared with the other report PDFs). */
    private const BRAND = [30, 61, 43];

    private const BRAND_LIGHT = [233, 240, 234];

    private const ZEBRA = [247, 249, 247];

    private const BORDER = [206, 212, 206];

    private const TEXT = [33, 37, 41];

    private const MUTED = [108, 117, 125];

    private const DANGER = [176, 42, 55];

    /**
     * @param  list<array{batch:string, variety:string, expected:int, actual:int, pulled_out:int, gap:int}>  $rows
     * @param  array{expected:int, actual:int, pulled_out:int, gap:int}  $totals
     */
    public function __construct(
        private readonly string $filterLabel,
        private readonly string $generatedBy,
        private readonly string $generatedAt,
        private readonly array $rows,
        private readonly array $totals,
    ) {
        parent::__construct('L', 'mm', 'A4');

        $this->SetTitle('Gap Report - '.$filterLabel);
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
        $this->Cell(150, 6, $this->txt('Gap Report'), 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(123, 6, $this->txt($this->filterLabel), 0, 1, 'R');

        $summaryY = $this->GetY() + 1.5;
        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect(12, $summaryY, self::TABLE_WIDTH, 8, 'F');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::TEXT);
        $this->SetXY(15, $summaryY + 1.8);
        $this->Cell(68, 4.4, $this->txt(sprintf('Expected: %d', $this->totals['expected'])), 0, 0, 'L');
        $this->Cell(68, 4.4, $this->txt(sprintf('Available: %d', $this->totals['actual'])), 0, 0, 'L');
        $this->Cell(68, 4.4, $this->txt(sprintf('Pulled out: %d', $this->totals['pulled_out'])), 0, 0, 'L');
        $this->Cell(69, 4.4, $this->txt(sprintf('Gap (effective mortality): %d', $this->totals['gap'])), 0, 1, 'L');

        $this->SetY($summaryY + 9.5);
        $this->SetX(12);
        $this->drawTableHeader();
        $this->drawGapNote();
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
        $this->Cell(200, 5, $this->txt('CacaoTrack - Gap Report'), 0, 0, 'L');
        $this->Cell(73, 5, $this->txt('Page '.$this->PageNo().' of {nb}'), 0, 0, 'R');
    }

    /**
     * Draw every batch row plus the totals row. Real data only (RULE 5/12).
     */
    public function renderBody(): void
    {
        if ($this->rows === []) {
            $this->SetFont('Helvetica', 'I', 10);
            $this->SetTextColor(...self::MUTED);
            $this->SetFillColor(...self::BRAND_LIGHT);
            $this->Cell(self::TABLE_WIDTH, 14, $this->txt('No data available for the selected period.'), 1, 1, 'C', true);

            return;
        }

        foreach ($this->rows as $index => $row) {
            $this->drawRow($index + 1, $row);
        }

        $this->drawTotals();
    }

    private function drawContinuationHeader(): void
    {
        $this->SetY(12);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6, $this->txt('Gap Report (continued) - '.$this->filterLabel), 0, 1, 'L');

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() + 0.5, 285, $this->GetY() + 0.5);
        $this->SetY($this->GetY() + 2.5);
        $this->SetX(12);
        $this->drawTableHeader();
    }

    private function drawTableHeader(): void
    {
        $headers = [
            'batch' => 'Batch',
            'variety' => 'Scion Variety',
            'expected' => 'Expected',
            'actual' => 'Available',
            'pulled_out' => 'Pulled Out',
            'gap' => 'Gap (Mortality)',
        ];

        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(...self::BRAND);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...self::BRAND);
        $this->SetLineWidth(0.2);

        foreach ($headers as $key => $label) {
            $align = in_array($key, ['expected', 'actual', 'pulled_out', 'gap'], true) ? 'R' : 'L';
            $this->Cell(self::COLUMNS[$key], 7, $this->txt($label), 1, 0, $align, true);
        }

        $this->Ln();

        $this->SetTextColor(...self::TEXT);
        $this->SetDrawColor(...self::BORDER);
    }

    /**
     * One-line definition of "Gap" so the printed report is self-describing
     * (traceability) rather than a bare number.
     */
    private function drawGapNote(): void
    {
        $this->SetFont('Helvetica', 'I', 7);
        $this->SetTextColor(...self::MUTED);
        $this->SetX(12);
        $this->MultiCell(
            self::TABLE_WIDTH,
            3.6,
            $this->txt('Gap = effective mortality: Stock Out is already netted out of the live available balance on every validated release, so recorded mortality is the only quantity still unreflected in Available.'),
            0,
            'L',
        );
        $this->SetTextColor(...self::TEXT);
    }

    /**
     * @param  array{batch:string, variety:string, expected:int, actual:int, gap:int}  $row
     */
    private function drawRow(int $number, array $row): void
    {
        $batchText = $this->txt($row['batch']);
        $varietyText = $this->txt($row['variety']);
        $lines = max(
            $this->linesFor($batchText, self::COLUMNS['batch'] - 2, 'B', 8.5),
            $this->linesFor($varietyText, self::COLUMNS['variety'] - 2, '', 8.5),
            1,
        );
        $height = $lines * self::LINE_HEIGHT + (self::ROW_PADDING * 2);

        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        $x = $this->GetX();
        $y = $this->GetY();
        $contentTop = $y + self::ROW_PADDING;

        if ($number % 2 === 0) {
            $this->SetFillColor(...self::ZEBRA);
            $this->Rect($x, $y, self::TABLE_WIDTH, $height, 'F');
        }

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.2);
        $cursor = $x;
        foreach (self::COLUMNS as $width) {
            $this->Rect($cursor, $y, $width, $height, 'D');
            $cursor += $width;
        }

        $this->SetXY($x + 1.2, $contentTop);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['batch'] - 2, self::LINE_HEIGHT, $batchText, 0, 'L');

        $varietyX = $x + self::COLUMNS['batch'];
        $this->SetXY($varietyX + 1.2, $contentTop);
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['variety'] - 2, self::LINE_HEIGHT, $varietyText, 0, 'L');

        $expectedX = $varietyX + self::COLUMNS['variety'];
        $this->vCenter(number_format($row['expected']), $expectedX, $y, self::COLUMNS['expected'], $height, 'R', self::TEXT, '', 8.5);

        $actualX = $expectedX + self::COLUMNS['expected'];
        $this->vCenter(number_format($row['actual']), $actualX, $y, self::COLUMNS['actual'], $height, 'R', self::TEXT, '', 8.5);

        $pulledX = $actualX + self::COLUMNS['actual'];
        $this->vCenter(number_format($row['pulled_out']), $pulledX, $y, self::COLUMNS['pulled_out'], $height, 'R', self::TEXT, '', 8.5);

        $gapX = $pulledX + self::COLUMNS['pulled_out'];
        $gapColor = $row['gap'] > 0 ? self::DANGER : self::BRAND;
        $this->vCenter(number_format($row['gap']), $gapX, $y, self::COLUMNS['gap'], $height, 'R', $gapColor, 'B', 8.5);

        $this->SetXY($x, $y + $height);
    }

    private function drawTotals(): void
    {
        $height = 7.5;

        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        $x = $this->GetX();
        $y = $this->GetY();

        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect($x, $y, self::TABLE_WIDTH, $height, 'F');
        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.2);

        $cursor = $x;
        foreach (self::COLUMNS as $width) {
            $this->Rect($cursor, $y, $width, $height, 'D');
            $cursor += $width;
        }

        $combinedWidth = self::COLUMNS['batch'] + self::COLUMNS['variety'];
        $this->vCenter('Total', $x, $y, $combinedWidth, $height, 'L', self::TEXT, 'B', 8.5);

        $expectedX = $x + $combinedWidth;
        $this->vCenter(number_format($this->totals['expected']), $expectedX, $y, self::COLUMNS['expected'], $height, 'R', self::TEXT, 'B', 8.5);

        $actualX = $expectedX + self::COLUMNS['expected'];
        $this->vCenter(number_format($this->totals['actual']), $actualX, $y, self::COLUMNS['actual'], $height, 'R', self::TEXT, 'B', 8.5);

        $pulledX = $actualX + self::COLUMNS['actual'];
        $this->vCenter(number_format($this->totals['pulled_out']), $pulledX, $y, self::COLUMNS['pulled_out'], $height, 'R', self::TEXT, 'B', 8.5);

        $gapX = $pulledX + self::COLUMNS['pulled_out'];
        $this->vCenter(number_format($this->totals['gap']), $gapX, $y, self::COLUMNS['gap'], $height, 'R', self::DANGER, 'B', 9.5);

        $this->SetXY($x, $y + $height);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $color
     */
    private function vCenter(string $text, float $x, float $y, float $width, float $height, string $align, array $color, string $style = '', float $size = 8.5): void
    {
        $this->SetFont('Helvetica', $style, $size);
        $this->SetTextColor(...$color);
        $this->SetXY($x, $y + (($height - self::LINE_HEIGHT) / 2));
        $this->Cell($width, self::LINE_HEIGHT, $this->txt($text), 0, 0, $align);
    }

    private function linesFor(string $text, float $width, string $style = '', float $size = 8.5): int
    {
        $this->SetFont('Helvetica', $style, $size);

        return $this->nbLines($width, $text);
    }

    private function nbLines(float $width, string $text): int
    {
        $text = str_replace("\r", '', $text);
        $paragraphs = explode("\n", $text);
        $max = 1;

        foreach ($paragraphs as $paragraph) {
            if ($paragraph === '') {
                $max = max($max, 1);

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
