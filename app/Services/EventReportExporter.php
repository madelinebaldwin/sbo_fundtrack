<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Expense;
use App\Support\Currency;
use Illuminate\Support\Collection;
use Phar;
use PharData;
use RuntimeException;

class EventReportExporter
{
    /**
     * @param  array{contributions: string, expenses: string, balance: string}  $totals
     * @param  Collection<int, Expense>  $expenses
     * @param  list<array{title?: ?string, name?: ?string, position?: ?string}>  $signatures
     */
    public function pdf(Event $event, array $totals, Collection $expenses, float $totalBudget, float $overExpenditure, array $signatures): string
    {
        $mastheadLogo = $this->loadPngForPdf(public_path('images/report-masthead-logos.png'));
        $pages = $this->drawPdfPages($event, $totals, $expenses, $totalBudget, $overExpenditure, $signatures, $mastheadLogo !== null);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $firstPageObject = 5;

        if ($mastheadLogo !== null) {
            $objects[5] = '<< /Type /XObject /Subtype /Image /Width '.$mastheadLogo['width'].' /Height '.$mastheadLogo['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns '.$mastheadLogo['width'].' >> /Length '.strlen($mastheadLogo['data'])." >>\nstream\n".$mastheadLogo['data']."\nendstream";
            $firstPageObject = 6;
        }
        $pageReferences = [];

        foreach ($pages as $index => $pageStream) {
            $pageObject = $firstPageObject + $index * 2;
            $contentObject = $pageObject + 1;
            $pageReferences[] = $pageObject.' 0 R';
            $imageResources = $mastheadLogo === null ? '' : ' /XObject << /ReportLogos 5 0 R >>';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.$imageResources.' >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = '<< /Length '.strlen($pageStream)." >>\nstream\n".$pageStream."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageReferences).'] /Count '.count($pageReferences).' >>';
        ksort($objects);

        $output = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($output);
            $output .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($output);
        $lastObject = max(array_keys($objects));
        $output .= "xref\n0 ".($lastObject + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= $lastObject; $number++) {
            $output .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }
        $output .= "trailer\n<< /Size ".($lastObject + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $output;
    }

    /**
     * @param  array{contributions: string, expenses: string, balance: string}  $totals
     * @param  Collection<int, Expense>  $expenses
     * @param  list<array{title?: ?string, name?: ?string, position?: ?string}>  $signatures
     */
    public function docx(Event $event, array $totals, Collection $expenses, float $totalBudget, float $overExpenditure, array $signatures): string
    {
        if (! class_exists(PharData::class)) {
            throw new RuntimeException('The PharData extension is required to create Word reports.');
        }

        $path = tempnam(sys_get_temp_dir(), 'fundtrack-report-');
        if (! is_string($path)) {
            throw new RuntimeException('Could not create a temporary report file.');
        }
        unlink($path);

        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            $mastheadLogo = file_get_contents(public_path('images/report-masthead-logos.png'));
            $hasMastheadLogo = is_string($mastheadLogo);
            $archive['[Content_Types].xml'] = $this->contentTypesXml();
            $archive['_rels/.rels'] = $this->rootRelationshipsXml();
            $archive['word/document.xml'] = $this->documentXml($event, $totals, $expenses, $totalBudget, $overExpenditure, $signatures, $hasMastheadLogo);
            $archive['word/_rels/document.xml.rels'] = $this->documentRelationshipsXml($hasMastheadLogo);
            if ($hasMastheadLogo) {
                $archive['word/media/report-masthead-logos.png'] = $mastheadLogo;
            }
            unset($archive);

            $content = file_get_contents($path);
            if (! is_string($content) || ! str_starts_with($content, 'PK')) {
                throw new RuntimeException('The Word report could not be generated.');
            }

            return $content;
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param  array{contributions: string, expenses: string, balance: string}  $totals
     * @param  Collection<int, Expense>  $expenses
     * @param  list<array{title?: ?string, name?: ?string, position?: ?string}>  $signatures
     * @return list<string>
     */
    private function drawPdfPages(Event $event, array $totals, Collection $expenses, float $totalBudget, float $overExpenditure, array $signatures, bool $hasMastheadLogo): array
    {
        $pages = [];
        $stream = '';
        $pageNumber = 0;
        $pageHeight = 841.89;
        $left = 28.35;
        $right = 566.93;
        $contentWidth = $right - $left;
        $y = 0.0;
        $text = function (string $value, float $x, float $top, float $size = 8, bool $bold = false) use (&$stream, $pageHeight): void {
            $font = $bold ? 'F2' : 'F1';
            $stream .= 'BT /'.$font.' '.$size.' Tf 0.03 0.03 0.03 rg '.$x.' '.($pageHeight - $top).' Td ('.$this->pdfEscape($value).") Tj ET\n";
        };
        $greenText = function (string $value, float $x, float $top, float $size = 8, bool $bold = false) use (&$stream, $pageHeight): void {
            $font = $bold ? 'F2' : 'F1';
            $stream .= 'BT /'.$font.' '.$size.' Tf 0.09 0.42 0.23 rg '.$x.' '.($pageHeight - $top).' Td ('.$this->pdfEscape($value).") Tj ET\n";
        };
        $greenRule = function (float $x1, float $top1, float $x2, float $top2, float $width = 0.6) use (&$stream, $pageHeight): void {
            $stream .= '0.09 0.42 0.23 RG '.$width.' w '.$x1.' '.($pageHeight - $top1).' m '.$x2.' '.($pageHeight - $top2).' l S'."\n";
        };
        $drawMastheadLogo = function () use (&$stream, $pageHeight): void {
            $width = 142.0;
            $height = 46.7;
            $x = (595.28 - $width) / 2;
            $bottom = $pageHeight - 28.0 - $height;
            $stream .= 'q '.$width.' 0 0 '.$height.' '.$x.' '.$bottom.' cm /ReportLogos Do Q'."\n";
        };
        $line = function (float $x1, float $top1, float $x2, float $top2, float $width = 0.6) use (&$stream, $pageHeight): void {
            $stream .= '0.25 0.25 0.25 RG '.$width.' w '.$x1.' '.($pageHeight - $top1).' m '.$x2.' '.($pageHeight - $top2).' l S'."\n";
        };
        $box = function (float $x, float $top, float $width, float $height, bool $shade = false) use (&$stream, $pageHeight): void {
            $bottom = $pageHeight - $top - $height;
            $stream .= $shade ? '0.871 0.871 0.871 rg 0.27 0.27 0.27 RG ' : '1 1 1 rg 0.27 0.27 0.27 RG ';
            $stream .= '0.75 w '.$x.' '.$bottom.' '.$width.' '.$height.' re B'."\n";
        };
        $startPage = function (bool $firstPage = false) use (&$pages, &$stream, &$pageNumber, &$y, $text, $greenText, $line, $greenRule, $drawMastheadLogo, $hasMastheadLogo, $left, $right, $contentWidth, $pageHeight, $event): void {
            if ($pageNumber > 0) {
                $line($left, $pageHeight - 42, $right, $pageHeight - 42, 0.5);
                $text('Page '.$pageNumber, $right - 36, $pageHeight - 27, 7);
                $pages[] = $stream;
            }
            $pageNumber++;
            $stream = '';
            if ($hasMastheadLogo) {
                $drawMastheadLogo();
            }
            $greenText('ISABELA STATE UNIVERSITY', $left, 47, 10, true);
            $greenText('University for People, Nature, Entrepreneurship and Innovation', $left, 62, 6.5);
            $campus = 'CAUAYAN CAMPUS';
            $campusWidth = mb_strwidth($campus) * 5.0;
            $greenText($campus, $right - $campusWidth, 47, 10, true);
            $campusAddress = 'San Fermin, Cauayan City, Isabela, Philippines 3305';
            $greenText($campusAddress, $right - (mb_strwidth($campusAddress) * 3.25), 62, 6.5);
            $greenRule($left, 77, $right, 77, 1.0);
            $y = $firstPage ? 105 : 96;
            if ($firstPage) {
                $reportTitle = 'FINANCIAL REPORT';
                $text($reportTitle, $left + (($contentWidth - (mb_strwidth($reportTitle) * 7.5)) / 2), $y, 15, true);
                $y += 22.4;
                foreach ($this->wrapPdfText($event->name, $contentWidth - 24, 12) as $eventNameLine) {
                    $eventNameWidth = mb_strwidth($eventNameLine) * 6;
                    $text($eventNameLine, $left + (($contentWidth - $eventNameWidth) / 2), $y, 12, true);
                    $y += 14.4;
                }
                $y += 2.8;
                $reportDate = $event->academicYear->name.' | Generated '.now()->format('F j, Y');
                foreach ($this->wrapPdfText($reportDate, $contentWidth - 24, 8) as $reportDateLine) {
                    $reportDateWidth = mb_strwidth($reportDateLine) * 4;
                    $text($reportDateLine, $left + (($contentWidth - $reportDateWidth) / 2), $y, 8);
                    $y += 9.6;
                }
                $y += 11.3;
            } else {
                foreach ($this->wrapPdfText('FINANCIAL REPORT | '.$event->name, $contentWidth, 9) as $continuationTitleLine) {
                    $text($continuationTitleLine, $left, $y, 9, true);
                    $y += 11;
                }
            }
        };
        $ensureSpace = function (float $height) use (&$y, $startPage, $pageHeight): void {
            if ($y + $height > $pageHeight - 62) {
                $startPage();
            }
        };
        $heading = function (string $label, float $fontSize = 10) use (&$y, $text, $left, $ensureSpace): void {
            $ensureSpace(24);
            $text($label, $left, $y, $fontSize, true);
            $y += 7;
        };
        $wrapCell = fn (string $value, float $width, float $fontSize = 7.5): array => $this->wrapPdfText($value, $width - 9, $fontSize);
        $row = function (array $cells, array $widths, bool $shade = false, bool $bold = false, array $rightAlignedColumns = [], array $centeredColumns = [], float $fontSize = 7.5) use (&$y, $left, $box, $text, $wrapCell): float {
            $cellLines = [];
            foreach ($widths as $index => $width) {
                $cellLines[$index] = $wrapCell((string) ($cells[$index] ?? ''), $width, $fontSize);
            }
            $lineCount = max(array_map('count', $cellLines));
            $lineGap = $fontSize * 1.15;
            $baselineOffset = $fontSize * 1.25;
            $height = max($fontSize * 1.2 + 5.6, $baselineOffset + (($lineCount - 1) * $lineGap) + 5.6);
            $x = $left;
            foreach ($widths as $index => $width) {
                $box($x, $y, $width, $height, $shade);
                foreach ($cellLines[$index] as $lineIndex => $cellLine) {
                    $lineWidth = mb_strwidth($cellLine) * $fontSize * 0.5;
                    $textX = in_array($index, $rightAlignedColumns, true)
                        ? $x + $width - 5 - $lineWidth
                        : (in_array($index, $centeredColumns, true) ? $x + max(4, ($width - $lineWidth) / 2) : $x + 4);
                    $text($cellLine, $textX, $y + $baselineOffset + ($lineIndex * $lineGap), $fontSize, $bold);
                }
                $x += $width;
            }
            $y += $height;

            return $height;
        };

        $startPage(true);
        $ensureSpace(130);
        $heading('I. TOTAL BUDGET');
        $summaryWidth = min($contentWidth, 420.0);
        $row(['TOTAL BUDGET', Currency::format($totalBudget)], [$summaryWidth * 0.62, $summaryWidth * 0.38], false, true, [1], [], 8.25);
        $y += 9;

        $heading('II. DISBURSEMENT');
        $columns = [$contentWidth * 0.09, $contentWidth * 0.10, $contentWidth * 0.45, $contentWidth * 0.18, $contentWidth * 0.18];
        $drawExpenseHeader = function () use ($row, $columns): void {
            $row(['Quantity', 'Unit', 'Article and Description', 'Unit Price', 'Amount'], $columns, false, true, [3, 4], [0, 1], 9.75);
        };
        $startDisbursementContinuation = function () use ($startPage, &$y, $text, $left, $drawExpenseHeader): void {
            $startPage();
            $text('II. DISBURSEMENT (CONTINUED)', $left, $y, 10, true);
            $y += 8;
            $drawExpenseHeader();
        };
        $drawExpenseHeader();
        $expenseCellLines = function (Expense $expense) use ($wrapCell, $columns): array {
            return [
                $wrapCell($expense->formattedQuantity(), $columns[0]),
                $wrapCell($expense->unit, $columns[1]),
                $wrapCell($expense->description, $columns[2]),
                $wrapCell((float) $expense->unit_price > 0 ? Currency::format((float) $expense->unit_price) : '—', $columns[3]),
                $wrapCell(Currency::format((float) $expense->amount), $columns[4]),
            ];
        };
        foreach ($expenses->groupBy(fn (Expense $expense): string => $expense->category->name)->sortKeys(SORT_NATURAL | SORT_FLAG_CASE) as $categoryName => $categoryExpenses) {
            $categoryLines = $wrapCell(mb_strtoupper((string) $categoryName), $contentWidth, 7.5);
            $categoryHeight = max(17.3, 12.2 + ((count($categoryLines) - 1) * 11.2) + 5.6);
            if ($y + $categoryHeight + 27 > $pageHeight - 62) {
                $startDisbursementContinuation();
            }
            $box($left, $y, $contentWidth, $categoryHeight, true);
            foreach ($categoryLines as $lineIndex => $categoryLine) {
                $categoryLabelWidth = mb_strwidth($categoryLine) * 4.875;
                $text($categoryLine, $left + max(4, ($contentWidth - $categoryLabelWidth) / 2), $y + 12.2 + ($lineIndex * 11.2), 9.75, true);
            }
            $y += $categoryHeight;
            foreach ($categoryExpenses as $expense) {
                $cellLines = $expenseCellLines($expense);
                $descriptionLines = $cellLines[2];
                $lineOffset = 0;

                do {
                    $availableLines = (int) floor(($pageHeight - 62 - $y - 5.6 - 9) / 8.625);
                    if ($availableLines < 2) {
                        $startDisbursementContinuation();
                        $availableLines = (int) floor(($pageHeight - 62 - $y - 5.6 - 9) / 8.625);
                    }
                    $lineCount = min(count($descriptionLines) - $lineOffset, $availableLines);
                    $cells = [
                        $lineOffset === 0 ? implode("\n", $cellLines[0]) : '',
                        $lineOffset === 0 ? implode("\n", $cellLines[1]) : '',
                        implode("\n", array_slice($descriptionLines, $lineOffset, $lineCount)),
                        $lineOffset === 0 ? implode("\n", $cellLines[3]) : '',
                        $lineOffset === 0 ? implode("\n", $cellLines[4]) : '',
                    ];
                    $row($cells, $columns, false, false, [3, 4], [0, 1]);
                    $lineOffset += $lineCount;

                    if ($lineOffset < count($descriptionLines)) {
                        $startDisbursementContinuation();
                    }
                } while ($lineOffset < count($descriptionLines));
            }
        }
        if ($expenses->isEmpty()) {
            $emptyMessage = 'No approved disbursements for this event.';
            $emptyMessageWidth = mb_strwidth($emptyMessage) * 3.75;
            $emptyRowHeight = 60.0;
            if ($y + $emptyRowHeight > $pageHeight - 62) {
                $startDisbursementContinuation();
            }
            $box($left, $y, $contentWidth, $emptyRowHeight);
            $emptyMessageX = $left + (($contentWidth - $emptyMessageWidth) / 2);
            $emptyMessageTop = $y + ($emptyRowHeight / 2) + 3;
            $stream .= 'BT /F1 7.5 Tf 0.47 0.47 0.47 rg '.$emptyMessageX.' '.($pageHeight - $emptyMessageTop).' Td ('.$this->pdfEscape($emptyMessage).") Tj ET\n";
            $y += $emptyRowHeight;
        }
        if ($y + 28 > $pageHeight - 62) {
            $startDisbursementContinuation();
        }
        $line($left, $y, $right, $y, 1.125);
        $row(['', '', '', 'GRAND TOTAL', Currency::format((float) $totals['expenses'])], $columns, false, true, [4], [], 9.75);
        $y += 9;

        $ensureSpace(130);
        $heading('III. OVERALL EXPENSES');
        $summaryColumns = [$summaryWidth * 0.74, $summaryWidth * 0.26];
        foreach ([
            ['OVERALL FUNDS', Currency::format((float) $totals['contributions'])],
            ['TOTAL EXPENSES', Currency::format((float) $totals['expenses'])],
            ['REMAINING BALANCE', Currency::format((float) $totals['balance'])],
            ['OVEREXPENDITURE / DEFICIT', Currency::format($overExpenditure)],
        ] as $summaryRow) {
            $row($summaryRow, $summaryColumns, false, true, [1], [], 8.25);
        }
        $y += 23;

        $signaturePairs = array_chunk($this->reportSignatures($signatures), 2);
        $signatureLayouts = [];
        foreach ($signaturePairs as $signaturePair) {
            $blocks = [];
            foreach ($signaturePair as $signature) {
                $width = ($contentWidth - 28.35) / 2;
                $blocks[] = [
                    'title' => $wrapCell($signature['title'], $width),
                    'name' => $wrapCell($signature['name'] !== '' ? $signature['name'] : 'Name', $width),
                    'position' => $wrapCell($signature['position'] !== '' ? $signature['position'] : 'Position', $width, 6.5),
                ];
            }
            $titleLines = max(array_map(fn (array $block): int => count($block['title']), $blocks));
            $nameLines = max(array_map(fn (array $block): int => count($block['name']), $blocks));
            $positionLines = max(array_map(fn (array $block): int => count($block['position']), $blocks));
            $signatureLayouts[] = [
                'blocks' => $blocks,
                'height' => max(58.5, 8 + ($titleLines * 8) + 18 + ($nameLines * 8) + ($positionLines * 8) + 5),
            ];
        }
        $firstSignatureHeight = $signatureLayouts[0]['height'] ?? 0;
        $ensureSpace(24 + $firstSignatureHeight);
        $heading('SIGNATURES', 9);
        foreach ($signatureLayouts as $signatureLayout) {
            $pageBeforeSignaturePair = $pageNumber;
            $ensureSpace($signatureLayout['height']);
            if ($pageNumber !== $pageBeforeSignaturePair) {
                $text('SIGNATURES (CONTINUED)', $left, $y, 9, true);
                $y += 10;
            }
            foreach ($signatureLayout['blocks'] as $index => $signatureBlock) {
                $width = ($contentWidth - 28.35) / 2;
                $x = $left + ($index * ($width + 28.35));
                foreach ($signatureBlock['title'] as $lineIndex => $titleLine) {
                    $text($titleLine, $x, $y + 8 + ($lineIndex * 8), 7, true);
                }
                $titleLineCount = count($signatureBlock['title']);
                $signatureLineY = $y + 8 + ($titleLineCount * 8) + 18;
                $line($x, $signatureLineY, $x + $width, $signatureLineY, 0.7);
                foreach ($signatureBlock['name'] as $lineIndex => $nameLine) {
                    $text($nameLine, $x, $signatureLineY + 11 + ($lineIndex * 8), 7, true);
                }
                foreach ($signatureBlock['position'] as $lineIndex => $positionLine) {
                    $positionTop = $signatureLineY + 11 + (count($signatureBlock['name']) * 8);
                    $text($positionLine, $x, $positionTop + ($lineIndex * 8), 6.5);
                }
            }
            $y += $signatureLayout['height'] + 11.34;
        }

        $line($left, $pageHeight - 42, $right, $pageHeight - 42, 0.5);
        $text('Page '.$pageNumber, $right - 36, $pageHeight - 27, 7);
        $pages[] = $stream;

        return $pages;
    }

    /** @param list<array{title?: ?string, name?: ?string, position?: ?string}> $signatures
     * @return list<array{title: string, name: string, position: string}>
     */
    private function reportSignatures(array $signatures): array
    {
        if ($signatures !== []) {
            return array_map(fn (array $signature): array => [
                'title' => (string) ($signature['title'] ?? ''),
                'name' => (string) ($signature['name'] ?? ''),
                'position' => (string) ($signature['position'] ?? ''),
            ], $signatures);
        }

        return array_map(fn (string $title): array => [
            'title' => $title,
            'name' => '',
            'position' => '',
        ], [
            'Prepared by',
            'Attested by',
            'Verified as true and correct',
            'Recommending approval',
            'Approved',
        ]);
    }

    /** @param array{title?: ?string, name?: ?string, position?: ?string} $signature */
    private function signatureCell(array $signature): string
    {
        return implode("\n", [
            (string) ($signature['title'] ?? ''),
            '',
            '______________________________',
            (string) ($signature['name'] ?? ''),
            (string) ($signature['position'] ?? ''),
        ]);
    }

    private function documentXml(Event $event, array $totals, Collection $expenses, float $totalBudget, float $overExpenditure, array $signatures, bool $hasMastheadLogo): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body>';
        $xml .= $this->wordMastheadTable($hasMastheadLogo);
        $xml .= $this->wordParagraph('FINANCIAL REPORT', true, 30);
        $xml .= $this->wordParagraph($event->name, true, 24);
        $xml .= $this->wordParagraph('Academic year: '.$event->academicYear->name.' | Generated: '.now()->format('F j, Y'));
        $xml .= $this->wordParagraph('I. TOTAL BUDGET', true, 22);
        $xml .= $this->wordTable([
            ['TOTAL BUDGET', Currency::format($totalBudget)],
        ], [6500, 2860]);
        $xml .= $this->wordParagraph('II. DISBURSEMENT', true, 22);
        $expenseRows = [['Quantity', 'Unit', 'Category and description', 'Unit price', 'Amount']];
        foreach ($expenses->groupBy(fn (Expense $expense): string => $expense->category->name)->sortKeys(SORT_NATURAL | SORT_FLAG_CASE) as $categoryName => $categoryExpenses) {
            $expenseRows[] = ['__category', strtoupper($categoryName)];
            foreach ($categoryExpenses as $expense) {
                $expenseRows[] = [
                    $expense->formattedQuantity(),
                    $expense->unit,
                    $expense->description,
                    (float) $expense->unit_price > 0 ? Currency::format((float) $expense->unit_price) : '—',
                    Currency::format((float) $expense->amount),
                ];
            }
        }
        if ($expenses->isEmpty()) {
            $expenseRows[] = ['', '', 'No approved disbursements for this event.', '', ''];
        }
        $expenseRows[] = ['', '', 'GRAND TOTAL', '', Currency::format((float) $totals['expenses'])];
        $xml .= $this->wordTable($expenseRows, [1000, 1000, 3360, 2000, 2000]);
        $xml .= $this->wordParagraph('III. OVERALL EXPENSES', true, 22);
        $xml .= $this->wordTable([
            ['OVERALL FUNDS', Currency::format((float) $totals['contributions'])],
            ['TOTAL EXPENSES', Currency::format((float) $totals['expenses'])],
            ['REMAINING BALANCE', Currency::format((float) $totals['balance'])],
            ['OVEREXPENDITURE / DEFICIT', Currency::format($overExpenditure)],
        ], [7000, 2360], true, true);
        $xml .= $this->wordParagraph('Signatures', true, 22);
        $signatureRows = [];
        foreach (array_chunk($this->reportSignatures($signatures), 2) as $signaturePair) {
            $signatureRows[] = [
                $this->signatureCell($signaturePair[0]),
                $this->signatureCell($signaturePair[1] ?? []),
            ];
        }
        $xml .= $this->wordTable($signatureRows, [4680, 4680], false);
        $xml .= '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr></w:body></w:document>';

        return $xml;
    }

    private function wordParagraph(string $text, bool $bold = false, int $size = 22, ?string $color = null, ?string $alignment = null): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $runProperties = ($bold ? '<w:b/>' : '').($color === null ? '' : '<w:color w:val="'.$color.'"/>').'<w:sz w:val="'.$size.'"/>';

        $paragraphProperties = $alignment === null ? '' : '<w:pPr><w:jc w:val="'.$alignment.'"/></w:pPr>';

        return '<w:p>'.$paragraphProperties.'<w:r><w:rPr>'.$runProperties.'</w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';
    }

    private function wordMastheadTable(bool $hasMastheadLogo): string
    {
        $columns = [3550, 2260, 3550];
        $cells = [
            $this->wordParagraph('ISABELA STATE UNIVERSITY', true, 24, '176B3B')
                .$this->wordParagraph('University for People, Nature, Entrepreneurship and Innovation', false, 12, '176B3B'),
            $hasMastheadLogo ? $this->wordImageParagraph() : '<w:p/>',
            $this->wordParagraph('CAUAYAN CAMPUS', true, 18, '176B3B', 'right')
                .$this->wordParagraph('San Fermin, Cauayan City, Isabela, Philippines 3305', false, 12, '176B3B', 'right'),
        ];
        $xml = '<w:tbl><w:tblPr><w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $xml .= '<w:'.$edge.' w:val="nil"/>';
        }
        $xml .= '</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>';
        foreach ($columns as $width) {
            $xml .= '<w:gridCol w:w="'.$width.'"/>';
        }
        $xml .= '</w:tblGrid><w:tr>';
        foreach ($cells as $index => $content) {
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.$columns[$index].'" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'.$content.'</w:tc>';
        }

        return $xml.'</w:tr></w:tbl>';
    }

    private function wordImageParagraph(): string
    {
        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline><wp:extent cx="2095500" cy="690563"/><wp:docPr id="1" name="Report masthead logos"/><wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="Report masthead logos"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="2095500" cy="690563"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<int>|null  $columnWidths
     */
    private function wordTable(array $rows, ?array $columnWidths = null, bool $styleFirstRow = true, bool $rightAlignLast = false): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $xml .= '<w:'.$edge.' w:val="single" w:sz="4" w:space="0" w:color="777777"/>';
        }
        $xml .= '</w:tblBorders></w:tblPr><w:tblGrid>';
        $columnCount = max(1, count($rows[0] ?? []));
        $widths = $columnWidths ?? array_fill(0, $columnCount, intdiv(9360, $columnCount));
        $widths[$columnCount - 1] += 9360 - array_sum($widths);
        foreach ($widths as $width) {
            $xml .= '<w:gridCol w:w="'.$width.'"/>';
        }
        $xml .= '</w:tblGrid>';
        foreach ($rows as $rowIndex => $row) {
            if (($row[0] ?? null) === '__category') {
                $category = htmlspecialchars($row[1] ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $xml .= '<w:tr><w:tc><w:tcPr><w:tcW w:w="9360" w:type="dxa"/><w:gridSpan w:val="'.$columnCount.'"/><w:shd w:fill="D9D9D9"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'.$category.'</w:t></w:r></w:p></w:tc></w:tr>';

                continue;
            }

            $xml .= '<w:tr>';
            foreach (array_pad($row, $columnCount, '') as $column => $cell) {
                $lineContent = implode('<w:br/>', array_map(
                    fn (string $line): string => '<w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</w:t>',
                    explode("\n", $cell),
                ));
                $isHeader = $styleFirstRow && $rowIndex === 0;
                $shading = $isHeader ? '<w:shd w:fill="E5E5E5"/>' : '';
                $runProperties = $isHeader ? '<w:rPr><w:b/></w:rPr>' : '';
                $alignment = $rightAlignLast && $column === $columnCount - 1 ? '<w:pPr><w:jc w:val="right"/></w:pPr>' : '';
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.$widths[$column].'" w:type="dxa"/>'.$shading.'</w:tcPr><w:p>'.$alignment.'<w:r>'.$runProperties.$lineContent.'</w:r></w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
    }

    private function documentRelationshipsXml(bool $hasMastheadLogo): string
    {
        $relationships = '';
        if ($hasMastheadLogo) {
            $relationships .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/report-masthead-logos.png"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
    }

    /**
     * @return array{width: int, height: int, data: string}|null
     */
    private function loadPngForPdf(string $path): ?array
    {
        $png = file_get_contents($path);
        if (! is_string($png) || ! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        $offset = 8;
        $width = 0;
        $height = 0;
        $bitDepth = 0;
        $colorType = -1;
        $compressedImageData = '';
        $pngLength = strlen($png);

        while ($offset + 12 <= $pngLength) {
            $chunkLength = unpack('Nlength', substr($png, $offset, 4))['length'];
            $chunkType = substr($png, $offset + 4, 4);
            $chunkData = substr($png, $offset + 8, $chunkLength);

            if (strlen($chunkData) !== $chunkLength || $offset + 12 + $chunkLength > $pngLength) {
                return null;
            }

            if ($chunkType === 'IHDR' && $chunkLength === 13) {
                $header = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $chunkData);
                $width = $header['width'];
                $height = $header['height'];
                $bitDepth = $header['bitDepth'];
                $colorType = $header['colorType'];
            } elseif ($chunkType === 'IDAT') {
                $compressedImageData .= $chunkData;
            } elseif ($chunkType === 'IEND') {
                break;
            }

            $offset += 12 + $chunkLength;
        }

        if ($width < 1 || $height < 1 || $bitDepth !== 8 || $colorType !== 2 || $compressedImageData === '') {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'data' => $compressedImageData,
        ];
    }

    /** @return list<string> */
    private function wrapPdfText(string $value, float $availableWidth, float $fontSize): array
    {
        $maximumWidth = max(1, (int) floor($availableWidth / ($fontSize * 0.52)));
        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $candidateLine = $currentLine === '' ? $word : $currentLine.' '.$word;
            if (mb_strwidth($candidateLine) <= $maximumWidth) {
                $currentLine = $candidateLine;

                continue;
            }
            if ($currentLine !== '') {
                $lines[] = $currentLine;
                $currentLine = '';
            }

            $wordLine = '';
            foreach (mb_str_split($word) as $character) {
                if ($wordLine !== '' && mb_strwidth($wordLine.$character) > $maximumWidth) {
                    $lines[] = $wordLine;
                    $wordLine = '';
                }
                $wordLine .= $character;
            }
            $currentLine = $wordLine;
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function pdfEscape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $text = is_string($encoded) ? $encoded : preg_replace('/[^\x20-\x7E]/', '?', $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $text);
    }
}
