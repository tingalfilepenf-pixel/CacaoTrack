<?php

namespace App\Support;

use FPDF;

/**
 * Printable "Cacao Tree Stock Ledger" PDF (FPDF, landscape).
 *
 * This is a tabular deliverable (PRD §13.1): every value is passed in by the
 * controller from real normalized operational data (RULE 5/12). It never
 * invents a row, a total or a status, and it needs no report table — the
 * ledger is generated live from batches, inventory, mortality and replanting.
 * Non Latin-1 characters are transliterated so the core FPDF fonts render.
 */
class StockLedgerPdf extends FPDF
{
    /** @var array<string,int|float> Column widths in millimetres (sum = TABLE_WIDTH). */
    private const COLUMNS = [
        'num' => 8,
        'batch' => 26,
        'variety' => 32,
        'scion' => 32,
        'grafted' => 24,
        'viable' => 24,
        'available' => 26,
        'mortality' => 26,
        'replanting' => 26,
        'date' => 30,
        'status' => 19,
    ];

    private const TABLE_WIDTH = 273.0;

    private const LINE_HEIGHT = 4.4;

    private const ROW_PADDING = 1.6;

    /** Brand palette (restrained agricultural green + neutral greys). */
    private const BRAND = [30, 61, 43];

    private const BRAND_LIGHT = [233, 240, 234];

    private const ZEBRA = [247, 249, 247];

    private const BORDER = [206, 212, 206];

    private const TEXT = [33, 37, 41];

    private const MUTED = [108, 117, 125];

    /**
     * @param  list<array{batch_code:string, variety:string|null, scion:string|null, grafted:int, viable:int, available:int, mortality:int, replanting:int, grafted_date:string|null, status:string}>  $rows
     * @param  array{batches:int, grafted:int, viable:int, available:int, mortality:int, replanting:int}  $totals
     */
    public function __construct(
        private readonly string $periodLabel,
        private readonly string $generatedBy,
        private readonly string $generatedAt,
        private readonly array $rows,
        private readonly array $totals,
    ) {
        parent::__construct('L', 'mm', 'A4');

        $this->SetTitle('Cacao Tree Stock Ledger - '.$periodLabel);
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
        $this->Cell(150, 6, $this->txt('Cacao Tree Stock Ledger'), 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(123, 6, $this->txt($this->periodLabel), 0, 1, 'R');

        $summaryY = $this->GetY() + 1.5;
        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect(12, $summaryY, self::TABLE_WIDTH, 8, 'F');
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::TEXT);
        $this->SetXY(15, $summaryY + 1.8);
        $this->Cell(44.5, 4.4, $this->txt(sprintf('Batches: %d', $this->totals['batches'])), 0, 0, 'L');
        $this->Cell(44.5, 4.4, $this->txt(sprintf('Grafted: %d', $this->totals['grafted'])), 0, 0, 'L');
        $this->Cell(44.5, 4.4, $this->txt(sprintf('Viable: %d', $this->totals['viable'])), 0, 0, 'L');
        $this->Cell(44.5, 4.4, $this->txt(sprintf('Available: %d', $this->totals['available'])), 0, 0, 'L');
        $this->Cell(44.5, 4.4, $this->txt(sprintf('Mortality: %d', $this->totals['mortality'])), 0, 0, 'L');
        $this->Cell(44.5, 4.4, $this->txt(sprintf('Replanted: %d', $this->totals['replanting'])), 0, 1, 'L');

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
        $this->Cell(200, 5, $this->txt('CacaoTrack - Cacao Tree Stock Ledger'), 0, 0, 'L');
        $this->Cell(73, 5, $this->txt('Page '.$this->PageNo().' of {nb}'), 0, 0, 'R');
    }

    /**
     * Draw every ledger row. Real data only (RULE 5/12).
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
    }

    private function drawContinuationHeader(): void
    {
        $this->SetY(12);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6, $this->txt('Cacao Tree Stock Ledger (continued) - '.$this->periodLabel), 0, 1, 'L');

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
            'num' => '#',
            'batch' => 'Batch',
            'variety' => 'Variety',
            'scion' => 'Scion',
            'grafted' => 'Grafted',
            'viable' => 'Viable',
            'available' => 'Available',
            'mortality' => 'Mortality',
            'replanting' => 'Replanting',
            'date' => 'Grafted Date',
            'status' => 'Status',
        ];

        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(...self::BRAND);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...self::BRAND);
        $this->SetLineWidth(0.2);

        foreach ($headers as $key => $label) {
            $align = in_array($key, ['grafted', 'viable', 'available', 'mortality', 'replanting'], true) ? 'R' : 'C';
            $this->Cell(self::COLUMNS[$key], 7, $this->txt($label), 1, 0, $align, true);
        }

        $this->Ln();
    }

    /**
     * @param  array{batch_code:string, variety:string|null, scion:string|null, grafted:int, viable:int, available:int, mortality:int, replanting:int, grafted_date:string|null, status:string}  $row
     */
    private function drawRow(int $number, array $row): void
    {
        $batchText = $this->txt($row['batch_code']);
        $varietyText = $this->txt($row['variety'] ?? '-');
        $scionText = $this->txt($row['scion'] ?? '-');

        $contentLines = max(
            $this->linesFor($batchText, self::COLUMNS['batch'], 'B', 9),
            $this->linesFor($varietyText, self::COLUMNS['variety'], '', 8.5),
            $this->linesFor($scionText, self::COLUMNS['scion'], '', 8.5),
            1,
        );
        $height = $contentLines * self::LINE_HEIGHT + (self::ROW_PADDING * 2);

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

        $this->vCenter((string) $number, $x, $y, self::COLUMNS['num'], $height, 'C', self::TEXT, 'B', 9);

        $batchX = $x + self::COLUMNS['num'];
        $this->SetXY($batchX, $contentTop);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['batch'], self::LINE_HEIGHT, $batchText, 0, 'L');

        $varietyX = $batchX + self::COLUMNS['batch'];
        $this->SetXY($varietyX, $contentTop);
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['variety'], self::LINE_HEIGHT, $varietyText, 0, 'L');

        $scionX = $varietyX + self::COLUMNS['variety'];
        $this->SetXY($scionX, $contentTop);
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['scion'], self::LINE_HEIGHT, $scionText, 0, 'L');

        $graftedX = $scionX + self::COLUMNS['scion'];
        $this->vCenter(number_format($row['grafted']), $graftedX, $y, self::COLUMNS['grafted'], $height, 'R', self::TEXT, '', 8.5);

        $viableX = $graftedX + self::COLUMNS['grafted'];
        $this->vCenter(number_format($row['viable']), $viableX, $y, self::COLUMNS['viable'], $height, 'R', self::TEXT, '', 8.5);

        $availableX = $viableX + self::COLUMNS['viable'];
        $this->vCenter(number_format($row['available']), $availableX, $y, self::COLUMNS['available'], $height, 'R', self::TEXT, '', 8.5);

        $mortalityX = $availableX + self::COLUMNS['available'];
        $this->vCenter(number_format($row['mortality']), $mortalityX, $y, self::COLUMNS['mortality'], $height, 'R', [176, 42, 55], '', 8.5);

        $replantingX = $mortalityX + self::COLUMNS['mortality'];
        $this->vCenter(number_format($row['replanting']), $replantingX, $y, self::COLUMNS['replanting'], $height, 'R', [176, 84, 10], '', 8.5);

        $dateX = $replantingX + self::COLUMNS['replanting'];
        $this->vCenter($row['grafted_date'] ?? '-', $dateX, $y, self::COLUMNS['date'], $height, 'C', self::TEXT, '', 8.5);

        $statusX = $dateX + self::COLUMNS['date'];
        $this->vCenter($this->txt($row['status']), $statusX, $y, self::COLUMNS['status'], $height, 'C', $this->statusColor($row['status']), 'B', 8.5);

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

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function statusColor(string $status): array
    {
        return match (strtolower($status)) {
            'active' => [21, 115, 71],
            'depleted' => [176, 84, 10],
            default => [108, 117, 125],
        };
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
