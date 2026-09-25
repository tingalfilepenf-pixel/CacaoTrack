<?php

namespace App\Support;

use FPDF;

/**
 * Printable "Daily Activity Checklist" PDF (FPDF).
 *
 * Renders only real saved data passed in by the controller (RULE 5/12) — it
 * never invents values. Non Latin-1 characters are transliterated so the
 * core FPDF fonts can render them.
 */
class ChecklistPdf extends FPDF
{
    /** @var array{num: int, task: int, priority: int, status: int, recorded: int, remark: int} */
    private const COLUMNS = [
        'num' => 8,
        'task' => 64,
        'priority' => 20,
        'status' => 22,
        'recorded' => 36,
        'remark' => 36,
    ];

    private const TABLE_WIDTH = 186.0;

    private const LINE_HEIGHT = 4.6;

    private const ROW_PADDING = 1.8;

    /** Brand palette (restrained agricultural green + neutral greys). */
    private const BRAND = [30, 61, 43];

    private const BRAND_LIGHT = [233, 240, 234];

    private const ZEBRA = [247, 249, 247];

    private const BORDER = [206, 212, 206];

    private const TEXT = [33, 37, 41];

    private const MUTED = [108, 117, 125];

    /**
     * @param  list<array{task_name: string, description: string|null, priority: string, status: string, completed_by: string|null, verified_by: string|null, remark: string|null}>  $rows
     */
    public function __construct(
        private readonly string $dateLabel,
        private readonly string $generatedBy,
        private readonly string $generatedAt,
        private readonly array $rows,
        private readonly int $completedCount,
        private readonly int $pendingCount,
    ) {
        parent::__construct('P', 'mm', 'A4');

        $this->SetTitle('Daily Activity Checklist - '.$dateLabel);
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
        $this->Cell(90, 8, $this->txt('CacaoTrack'), 0, 0, 'L');

        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(235, 240, 235);
        $this->SetXY(108, $bandY + 2.4);
        $this->Cell(87, 4, $this->txt('Generated '.$this->generatedAt), 0, 0, 'R');
        $this->SetXY(108, $bandY + 6.8);
        $this->Cell(87, 4, $this->txt('By '.$this->generatedBy), 0, 0, 'R');

        $this->SetY($bandY + 15.5);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...self::TEXT);
        $this->Cell(120, 6, $this->txt('Daily Activity Checklist'), 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(66, 6, $this->txt($this->dateLabel), 0, 1, 'R');

        $summaryY = $this->GetY() + 1.5;
        $this->SetFillColor(...self::BRAND_LIGHT);
        $this->Rect(12, $summaryY, self::TABLE_WIDTH, 8, 'F');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::TEXT);
        $this->SetXY(15, $summaryY + 1.8);
        $this->Cell(58, 4.4, $this->txt(sprintf('Total tasks: %d', count($this->rows))), 0, 0, 'L');
        $this->Cell(58, 4.4, $this->txt(sprintf('Completed: %d', $this->completedCount)), 0, 0, 'L');
        $this->Cell(55, 4.4, $this->txt(sprintf('Pending: %d', $this->pendingCount)), 0, 0, 'L');

        $this->SetY($summaryY + 9.5);
        $this->SetX(12);
        $this->drawTableHeader();
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
        $this->Cell(120, 5, $this->txt('CacaoTrack - Daily Activity Checklist'), 0, 0, 'L');
        $this->Cell(66, 5, $this->txt('Page '.$this->PageNo().' of {nb}'), 0, 0, 'R');
    }

    /**
     * Draw every task row, then the signature block.
     */
    public function renderBody(): void
    {
        if ($this->rows === []) {
            $this->SetFont('Helvetica', 'I', 10);
            $this->SetTextColor(...self::MUTED);
            $this->SetFillColor(...self::BRAND_LIGHT);
            $this->Cell(self::TABLE_WIDTH, 14, $this->txt('No tasks are recorded for this date.'), 1, 1, 'C', true);
            $this->Ln(2);
            $this->drawSignatures();

            return;
        }

        foreach ($this->rows as $index => $row) {
            $this->drawRow($index + 1, $row);
        }

        $this->drawSignatures();
    }

    private function drawContinuationHeader(): void
    {
        $this->SetY(12);
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6, $this->txt('Daily Activity Checklist (continued) - '.$this->dateLabel), 0, 1, 'L');

        $this->SetDrawColor(...self::BORDER);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY() + 0.5, 198, $this->GetY() + 0.5);
        $this->SetY($this->GetY() + 2.5);
        $this->SetX(12);
        $this->drawTableHeader();
    }

    private function drawTableHeader(): void
    {
        $headers = [
            'num' => '#',
            'task' => 'Task',
            'priority' => 'Priority',
            'status' => 'Status',
            'recorded' => 'Completed / Verified By',
            'remark' => 'Remarks',
        ];

        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(...self::BRAND);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...self::BRAND);
        $this->SetLineWidth(0.2);

        foreach ($headers as $key => $label) {
            $this->Cell(self::COLUMNS[$key], 7, $this->txt($label), 1, 0, 'C', true);
        }

        $this->Ln();
    }

    private function drawRow(int $number, array $row): void
    {
        $taskName = $this->txt($row['task_name']);
        $description = ! empty($row['description']) ? $this->txt($row['description']) : '';

        $recordedText = $row['completed_by'] !== null
            ? $this->txt($row['completed_by'])
            : '-';
        if ($row['verified_by'] !== null && $row['verified_by'] !== $row['completed_by']) {
            $recordedText .= "\n".$this->txt('Verified: '.$row['verified_by']);
        } elseif ($row['completed_by'] !== null) {
            $recordedText .= "\n".$this->txt('(self-verified)');
        }

        $remarkText = $this->txt($row['remark'] ?? '-');

        $taskLines = $this->linesFor($taskName, self::COLUMNS['task'], 'B', 9);
        $descLines = $description !== '' ? $this->linesFor($description, self::COLUMNS['task'], 'I', 8) : 0;
        $recordedLines = $this->linesFor($recordedText, self::COLUMNS['recorded'], '', 9);
        $remarkLines = $this->linesFor($remarkText, self::COLUMNS['remark'], '', 9);

        $contentLines = max($taskLines + $descLines, $recordedLines, $remarkLines, 1);
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

        $taskX = $x + self::COLUMNS['num'];
        $this->SetXY($taskX, $contentTop);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['task'], self::LINE_HEIGHT, $taskName, 0, 'L');

        if ($description !== '') {
            $this->SetXY($taskX, $this->GetY());
            $this->SetFont('Helvetica', 'I', 8);
            $this->SetTextColor(...self::MUTED);
            $this->MultiCell(self::COLUMNS['task'], self::LINE_HEIGHT, $description, 0, 'L');
        }

        $priorityX = $taskX + self::COLUMNS['task'];
        $this->drawBadge($priorityX, $y, self::COLUMNS['priority'], $height, ucfirst($row['priority']), $this->priorityColors($row['priority']));

        $statusX = $priorityX + self::COLUMNS['priority'];
        $this->vCenter($row['status'], $statusX, $y, self::COLUMNS['status'], $height, 'C', $this->statusColor($row['status']), 'B', 9);

        $recordedX = $statusX + self::COLUMNS['status'];
        $this->SetXY($recordedX, $contentTop);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['recorded'], self::LINE_HEIGHT, $recordedText, 0, 'L');

        $remarkX = $recordedX + self::COLUMNS['recorded'];
        $this->SetXY($remarkX, $contentTop);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(self::COLUMNS['remark'], self::LINE_HEIGHT, $remarkText, 0, 'L');

        $this->SetXY($x, $y + $height);
    }

    /**
     * @param  array{0: array{0: int, 1: int, 2: int}, 1: array{0: int, 1: int, 2: int}}  $colors
     */
    private function drawBadge(float $x, float $y, float $width, float $height, string $text, array $colors): void
    {
        [$background, $foreground] = $colors;

        $badgeHeight = 5.2;
        $badgeWidth = $width - 5;
        $badgeX = $x + 2.5;
        $badgeY = $y + ($height - $badgeHeight) / 2;

        $this->SetFillColor(...$background);
        $this->Rect($badgeX, $badgeY, $badgeWidth, $badgeHeight, 'F');

        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(...$foreground);
        $this->SetXY($badgeX, $badgeY + (($badgeHeight - 4.2) / 2));
        $this->Cell($badgeWidth, 4.2, $this->txt($text), 0, 0, 'C');
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $color
     */
    private function vCenter(string $text, float $x, float $y, float $width, float $height, string $align, array $color, string $style = '', float $size = 9): void
    {
        $this->SetFont('Helvetica', $style, $size);
        $this->SetTextColor(...$color);
        $this->SetXY($x, $y + (($height - self::LINE_HEIGHT) / 2));
        $this->Cell($width, self::LINE_HEIGHT, $this->txt($text), 0, 0, $align);
    }

    /**
     * @return array{0: array{0: int, 1: int, 2: int}, 1: array{0: int, 1: int, 2: int}}
     */
    private function priorityColors(string $priority): array
    {
        return match (strtolower($priority)) {
            'high' => [[220, 53, 69], [255, 255, 255]],
            'low' => [[108, 117, 125], [255, 255, 255]],
            default => [[255, 193, 7], [33, 37, 41]],
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function statusColor(string $status): array
    {
        return strtolower($status) === 'completed'
            ? [21, 115, 71]
            : [176, 84, 10];
    }

    private function drawSignatures(): void
    {
        if ($this->GetY() + 42 > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        $columnWidth = 87.0;
        $gap = 12.0;

        $this->Ln(10);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::TEXT);
        $this->Cell($columnWidth, 6, $this->txt('Prepared by:'), 0, 0, 'L');
        $this->Cell($gap, 6, '', 0, 0);
        $this->Cell(0, 6, $this->txt('Verified by:'), 0, 1, 'L');

        $this->Ln(12);
        $this->SetDrawColor(120, 120, 120);
        $this->SetLineWidth(0.3);
        $this->Cell($columnWidth, 6, '', 'T', 0, 'L');
        $this->Cell($gap, 6, '', 0, 0);
        $this->Cell(0, 6, '', 'T', 1, 'L');

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::MUTED);
        $this->Cell($columnWidth, 5, $this->txt($this->generatedBy.' - signature / date'), 0, 0, 'L');
        $this->Cell($gap, 5, '', 0, 0);
        $this->Cell(0, 5, $this->txt('Signature over printed name / date'), 0, 1, 'L');
    }

    private function linesFor(string $text, float $width, string $style = '', float $size = 9): int
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
