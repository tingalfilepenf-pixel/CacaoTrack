<?php

namespace App\Support;

use FPDF;

/**
 * Printable "Mortality Root-Cause Matrix" PDF (FPDF, landscape).
 *
 * This is a tabular deliverable (PRD §13.2): every cell, row total and grand
 * total is passed in by the controller from real normalized operational data
 * (mortality records + signed adjustments) — RULE 5/12. The matrix is generated
 * live; no report table is involved (PRD §9, ERD §9.4).
 *
 * Non Latin-1 characters are transliterated so the core FPDF fonts render.
 */
class MortalityMatrixPdf extends FPDF
{
    private const TABLE_WIDTH = 273.0;

    private const LINE_HEIGHT = 4.6;

    private const ROW_PADDING = 1.8;

    /** Brand palette (shared with StockLedgerPdf — restrained agricultural green). */
    private const BRAND = [30, 61, 43];

    private const BRAND_LIGHT = [233, 240, 234];

    private const ZEBRA = [247, 249, 247];

    private const BORDER = [206, 212, 206];

    private const TEXT = [33, 37, 41];

    private const MUTED = [108, 117, 125];

    private const DANGER = [176, 42, 55];

    private readonly float $varietyWidth;

    private readonly float $causeWidth;

    private readonly float $totalWidth;

    /**
     * @param  list<string>  $causes
     * @param  list<array{variety:string, cells:array<string,int>, total:int}>  $rows
     * @param  array<string,int>  $totalsByCause
     * @param  array{raw:int, adjustment:int, effective:int}  $totals
     */
    public function __construct(
        private readonly string $filterLabel,
        private readonly string $generatedBy,
        private readonly string $generatedAt,
        private readonly array $causes,
        private readonly array $rows,
        private readonly array $totalsByCause,
        private readonly array $totals,
    ) {
        parent::__construct('L', 'mm', 'A4');

        $this->SetTitle('Mortality Root-Cause Matrix - '.$filterLabel);
        $this->SetAuthor('CacaoTrack');
        $this->SetCreator('CacaoTrack');
        $this->SetMargins(12, 14, 12);
        $this->SetAutoPageBreak(true, 18);
        $this->SetCompression(false);
        $this->AliasNbPages();

        $total = 24.0;
        $variety = 60.0;
        $causeCount = max(1, count($this->causes));
        $minCause = 20.0;
        $remaining = self::TABLE_WIDTH - $variety - $total;

        if ($remaining / $causeCount < $minCause) {
            $variety = max(36.0, self::TABLE_WIDTH - $total - ($minCause * $causeCount));
            $remaining = self::TABLE_WIDTH - $variety - $total;
        }

        $this->varietyWidth = $variety;
        $this->causeWidth = $remaining / $causeCount;
        $this->totalWidth = $total;
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
        $this->Cell(150, 6, $this->txt('Mortality Root-Cause Matrix'), 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(123, 6, $this->txt($this->filterLabel), 0, 1, 'R');

        $summaryY = $this->GetY() + 1.5;
        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect(12, $summaryY, self::TABLE_WIDTH, 8, 'F');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::TEXT);
        $this->SetXY(15, $summaryY + 1.8);
        $this->Cell(91, 4.4, $this->txt(sprintf('Raw mortality: %d', $this->totals['raw'])), 0, 0, 'L');
        $this->Cell(91, 4.4, $this->txt(sprintf('Adjustments: %+d', $this->totals['adjustment'])), 0, 0, 'L');
        $this->Cell(91, 4.4, $this->txt(sprintf('Effective mortality: %d', $this->totals['effective'])), 0, 1, 'L');

        $this->SetY($summaryY + 9.5);
        $this->SetX(12);
        $this->drawTableHeader();
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
        $this->Cell(200, 5, $this->txt('CacaoTrack - Mortality Root-Cause Matrix'), 0, 0, 'L');
        $this->Cell(73, 5, $this->txt('Page '.$this->PageNo().' of {nb}'), 0, 0, 'R');
    }

    /**
     * Draw every variety row plus the grand-total row. Real data only (RULE 5/12).
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

        $this->drawGrandTotal();
    }

    private function drawContinuationHeader(): void
    {
        $this->SetY(12);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6, $this->txt('Mortality Root-Cause Matrix (continued) - '.$this->filterLabel), 0, 1, 'L');

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() + 0.5, 285, $this->GetY() + 0.5);
        $this->SetY($this->GetY() + 2.5);
        $this->SetX(12);
        $this->drawTableHeader();
    }

    private function drawTableHeader(): void
    {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(...self::BRAND);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...self::BRAND);
        $this->SetLineWidth(0.2);

        $this->Cell($this->varietyWidth, 7, $this->txt('Variety'), 1, 0, 'L', true);

        foreach ($this->causes as $cause) {
            $this->Cell($this->causeWidth, 7, $this->txt($cause), 1, 0, 'C', true);
        }

        $this->Cell($this->totalWidth, 7, $this->txt('Total'), 1, 1, 'C', true);

        // Reset body drawing colour/state after the white-on-green header.
        $this->SetTextColor(...self::TEXT);
        $this->SetDrawColor(...self::BORDER);
    }

    /**
     * @param  array{variety:string, cells:array<string,int>, total:int}  $row
     */
    private function drawRow(int $number, array $row): void
    {
        $varietyText = $this->txt($row['variety']);
        $lines = max(1, $this->linesFor($varietyText, $this->varietyWidth - 2, 'B', 9));
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
        foreach (array_merge([$this->varietyWidth], array_fill(0, count($this->causes), $this->causeWidth), [$this->totalWidth]) as $width) {
            $this->Rect($cursor, $y, $width, $height, 'D');
            $cursor += $width;
        }

        $this->SetXY($x + 1.2, $contentTop);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell($this->varietyWidth - 2, self::LINE_HEIGHT, $varietyText, 0, 'L');

        $causeX = $x + $this->varietyWidth;
        foreach ($this->causes as $cause) {
            $this->vCenter(number_format($row['cells'][$cause] ?? 0), $causeX, $y, $this->causeWidth, $height, 'C', self::TEXT, '', 8.5);
            $causeX += $this->causeWidth;
        }

        $this->vCenter(number_format($row['total']), $causeX, $y, $this->totalWidth, $height, 'C', self::DANGER, 'B', 8.5);

        $this->SetXY($x, $y + $height);
    }

    private function drawGrandTotal(): void
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
        foreach (array_merge([$this->varietyWidth], array_fill(0, count($this->causes), $this->causeWidth), [$this->totalWidth]) as $width) {
            $this->Rect($cursor, $y, $width, $height, 'D');
            $cursor += $width;
        }

        $this->vCenter('Grand Total', $x, $y, $this->varietyWidth, $height, 'L', self::TEXT, 'B', 8.5);

        $causeX = $x + $this->varietyWidth;
        foreach ($this->causes as $cause) {
            $this->vCenter(number_format($this->totalsByCause[$cause] ?? 0), $causeX, $y, $this->causeWidth, $height, 'C', self::TEXT, 'B', 8.5);
            $causeX += $this->causeWidth;
        }

        $this->vCenter(number_format($this->totals['effective']), $causeX, $y, $this->totalWidth, $height, 'C', self::DANGER, 'B', 9.5);

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
