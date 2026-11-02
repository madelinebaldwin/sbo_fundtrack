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
        $pages = $this->drawPdfPages($event, $totals, $expenses, $totalBudget, $overExpenditure, $signatures);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageReferences = [];

        foreach ($pages as $index => $pageStream) {
            $pageObject = 5 + $index * 2;
            $contentObject = $pageObject + 1;
            $pageReferences[] = $pageObject.' 0 R';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentObject.' 0 R >>';
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
            $archive['[Content_Types].xml'] = $this->contentTypesXml();
            $archive['_rels/.rels'] = $this->rootRelationshipsXml();
            $archive['word/document.xml'] = $this->documentXml($event, $totals, $expenses, $totalBudget, $overExpenditure, $signatures);
            $archive['word/_rels/document.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
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
    private function drawPdfPages(Event $event, array $totals, Collection $expenses, float $totalBudget, float $overExpenditure, array $signatures): array
    {
        $pages = [];
        $stream = '';
        $pageNumber = 0;
        $pageHeight = 842.0;
        $left = 38.0;
        $right = 557.0;
        $contentWidth = $right - $left;
        $y = 0.0;
        $text = function (string $value, float $x, float $top, float $size = 8, bool $bold = false) use (&$stream, $pageHeight): void {
            $font = $bold ? 'F2' : 'F1';
            $stream .= 'BT /'.$font.' '.$size.' Tf 0.12 0.12 0.12 rg '.$x.' '.($pageHeight - $top).' Td ('.$this->pdfEscape($value).") Tj ET\n";
        };
        $line = function (float $x1, float $top1, float $x2, float $top2, float $width = 0.6) use (&$stream, $pageHeight): void {
            $stream .= '0.25 0.25 0.25 RG '.$width.' w '.$x1.' '.($pageHeight - $top1).' m '.$x2.' '.($pageHeight - $top2).' l S'."\n";
        };
        $box = function (float $x, float $top, float $width, float $height, bool $shade = false) use (&$stream, $pageHeight): void {
            $bottom = $pageHeight - $top - $height;
            $stream .= $shade ? '0.88 0.88 0.88 rg 0.25 0.25 0.25 RG ' : '1 1 1 rg 0.25 0.25 0.25 RG ';
            $stream .= '0.5 w '.$x.' '.$bottom.' '.$width.' '.$height.' re B'."\n";
        };
        $startPage = function (bool $firstPage = false) use (&$pages, &$stream, &$pageNumber, &$y, $text, $line, $left, $right, $contentWidth, $pageHeight, $event): void {
            if ($pageNumber > 0) {
                $line($left, $pageHeight - 30, $right, $pageHeight - 30, 0.5);
                $text('Page '.$pageNumber, $right - 36, $pageHeight - 15, 7);
                $pages[] = $stream;
            }
            $pageNumber++;
            $stream = '';
            $text('ISABELA STATE UNIVERSITY', $left, 28, 10, true);
            $text('CAUAYAN CAMPUS', $left, 41, 8, true);
            $text('BAGONG PILIPINAS', $right - 88, 34, 7.5, true);
            $line($left, 50, $right, 50, 1.2);
            $y = 66;
            if ($firstPage) {
                $reportTitle = 'FINANCIAL REPORT';
                $text($reportTitle, $left + (($contentWidth - (mb_strwidth($reportTitle) * 7.5)) / 2), $y, 15, true);
                $y += 19;
                $eventName = mb_strimwidth($event->name, 0, 68, '...');
                $eventNameWidth = mb_strwidth($eventName) * 5.5;
                $text($eventName, $left + (($contentWidth - $eventNameWidth) / 2), $y, 11, true);
                $y += 14;
                $reportDate = 'Academic year: '.$event->academicYear->name.' | Generated: '.now()->format('F j, Y');
                $reportDateWidth = mb_strwidth($reportDate) * 3.75;
                $text($reportDate, $left + (($contentWidth - $reportDateWidth) / 2), $y, 7.5);
                $y += 18;
            } else {
                $text('FINANCIAL REPORT — '.mb_strimwidth($event->name, 0, 56, '...'), $left, $y, 9, true);
                $y += 17;
            }
        };
        $ensureSpace = function (float $height) use (&$y, $startPage, $pageHeight): void {
            if ($y + $height > $pageHeight - 43) {
                $startPage();
            }
        };
        $heading = function (string $label) use (&$y, $text, $left, $ensureSpace): void {
            $ensureSpace(24);
            $text($label, $left, $y, 9.5, true);
            $y += 7;
        };
        $row = function (array $cells, array $widths, float $height, bool $shade = false, bool $bold = false) use (&$y, $left, $box, $text): void {
            $x = $left;
            foreach ($widths as $index => $width) {
                $box($x, $y, $width, $height, $shade);
                $cell = (string) ($cells[$index] ?? '');
                $limit = max(1, (int) floor(($width - 8) / 4));
                foreach (array_slice(explode("\n", wordwrap($cell, $limit, "\n", true)), 0, max(1, (int) floor(($height - 5) / 9))) as $lineIndex => $cellLine) {
                    $text($cellLine, $x + 4, $y + 10 + ($lineIndex * 9), 7, $bold);
                }
                $x += $width;
            }
            $y += $height;
        };

        $startPage(true);
        $heading('I. TOTAL BUDGET');
        $row(['TOTAL BUDGET', Currency::format($totalBudget)], [$contentWidth * 0.62, $contentWidth * 0.38], 20, false, true);
        $y += 10;

        $heading('II. DISBURSEMENT');
        $columns = [48.0, 52.0, 235.0, 92.0, 92.0];
        $row(['Quantity', 'Unit', 'Article and Description', 'Unit Price', 'Amount'], $columns, 21, true, true);
        foreach ($expenses->groupBy(fn (Expense $expense): string => $expense->category->name)->sortKeys(SORT_NATURAL | SORT_FLAG_CASE) as $categoryName => $categoryExpenses) {
            $ensureSpace(18);
            $box($left, $y, $contentWidth, 17, true);
            $categoryLabel = mb_strtoupper((string) $categoryName);
            $categoryLabelWidth = mb_strwidth($categoryLabel) * 3.75;
            $text($categoryLabel, $left + (($contentWidth - $categoryLabelWidth) / 2), $y + 11, 7.5, true);
            $y += 17;
            foreach ($categoryExpenses as $expense) {
                $wrappedDescription = wordwrap($expense->description, 42, "\n", true);
                $height = max(20.0, count(explode("\n", $wrappedDescription)) * 9 + 6);
                $ensureSpace($height);
                $row([
                    $expense->formattedQuantity(),
                    $expense->unit,
                    $wrappedDescription,
                    (float) $expense->unit_price > 0 ? Currency::format((float) $expense->unit_price) : '—',
                    Currency::format((float) $expense->amount),
                ], $columns, $height);
            }
        }
        if ($expenses->isEmpty()) {
            $row(['', '', 'No approved disbursements for this event.', '', ''], $columns, 21);
        }
        $row(['', '', '', 'GRAND TOTAL', Currency::format((float) $totals['expenses'])], $columns, 21, false, true);
        $y += 10;

        $heading('III. OVERALL EXPENSES');
        $summaryColumns = [$contentWidth * 0.62, $contentWidth * 0.38];
        foreach ([
            ['OVERALL FUNDS', Currency::format((float) $totals['contributions'])],
            ['TOTAL EXPENSES', Currency::format((float) $totals['expenses'])],
            ['REMAINING BALANCE', Currency::format((float) $totals['balance'])],
            ['OVEREXPENDITURE / DEFICIT', Currency::format($overExpenditure)],
        ] as $summaryRow) {
            $row($summaryRow, $summaryColumns, 19, false, true);
        }
        $y += 10;

        $heading('SIGNATURES');
        foreach (array_chunk($this->reportSignatures($signatures), 2) as $signaturePair) {
            $ensureSpace(73);
            foreach ($signaturePair as $index => $signature) {
                $x = $left + ($index * ($contentWidth / 2)) + 8;
                $width = ($contentWidth / 2) - 24;
                $text($signature['title'], $x, $y + 11, 7.5, true);
                $line($x, $y + 39, $x + $width, $y + 39, 0.7);
                $text($signature['name'], $x, $y + 50, 7.5, true);
                $text($signature['position'], $x, $y + 61, 7);
            }
            $y += 73;
        }

        $line($left, $pageHeight - 30, $right, $pageHeight - 30, 0.5);
        $text('Page '.$pageNumber, $right - 36, $pageHeight - 15, 7);
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

    private function documentXml(Event $event, array $totals, Collection $expenses, float $totalBudget, float $overExpenditure, array $signatures): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>';
        $xml .= $this->wordParagraph('ISABELA STATE UNIVERSITY', true, 24);
        $xml .= $this->wordParagraph('CAUAYAN CAMPUS | STUDENT BODY ORGANIZATION', true, 18);
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
        ], [6500, 2860]);
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

    private function wordParagraph(string $text, bool $bold = false, int $size = 22): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $runProperties = ($bold ? '<w:b/>' : '').'<w:sz w:val="'.$size.'"/>';

        return '<w:p><w:r><w:rPr>'.$runProperties.'</w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<int>|null  $columnWidths
     */
    private function wordTable(array $rows, ?array $columnWidths = null, bool $styleFirstRow = true): string
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
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.$widths[$column].'" w:type="dxa"/>'.$shading.'</w:tcPr><w:p><w:r>'.$runProperties.$lineContent.'</w:r></w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
    }

    private function pdfEscape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $text = is_string($encoded) ? $encoded : preg_replace('/[^\x20-\x7E]/', '?', $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $text);
    }
}
