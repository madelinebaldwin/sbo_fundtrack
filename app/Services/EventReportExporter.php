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
    public function pdf(Event $event, array $totals, Collection $expenses, float $budgetGoal, float $overExpenditure, array $signatures): string
    {
        $lines = $this->reportLines($event, $totals, $expenses, $budgetGoal, $overExpenditure, $signatures);
        $pages = array_chunk($lines, 54);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];
        $pageReferences = [];

        foreach ($pages as $index => $pageLines) {
            $pageObject = 4 + $index * 2;
            $contentObject = $pageObject + 1;
            $pageReferences[] = $pageObject.' 0 R';
            $text = "BT\n/F1 9 Tf\n48 756 Td\n12 TL\n";
            foreach ($pageLines as $line) {
                $text .= '('.$this->pdfEscape($line).") Tj\nT*\n";
            }
            $text .= 'ET';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = '<< /Length '.strlen($text)." >>\nstream\n".$text."\nendstream";
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
    public function docx(Event $event, array $totals, Collection $expenses, float $budgetGoal, float $overExpenditure, array $signatures): string
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
            $archive['word/document.xml'] = $this->documentXml($event, $totals, $expenses, $budgetGoal, $overExpenditure, $signatures);
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

    /** @return list<string> */
    private function reportLines(Event $event, array $totals, Collection $expenses, float $budgetGoal, float $overExpenditure, array $signatures): array
    {
        $lines = [
            'FUNDTRACK EVENT FINANCIAL REPORT',
            str_repeat('=', 70),
            'Event: '.$event->name,
            'Academic year: '.$event->academicYear->name,
            'Generated: '.now()->format('F j, Y'),
            '',
            'I. TOTAL BUDGET',
            'Contribution goal: '.Currency::format($budgetGoal),
            '',
            'II. DISBURSEMENT',
            sprintf('%-5s %-10s %-31s %12s %12s', 'Qty', 'Unit', 'Category / Description', 'Unit Price', 'Amount'),
            str_repeat('-', 75),
        ];

        foreach ($expenses as $expense) {
            $lines[] = sprintf(
                '%-5s %-10s %-31s %12s %12s',
                '1',
                'Item',
                $expense->category->name.' / '.$expense->description,
                number_format((float) $expense->amount, 2),
                number_format((float) $expense->amount, 2),
            );
        }

        $lines[] = str_repeat('-', 75);
        $lines[] = 'GRAND TOTAL: '.Currency::format((float) $totals['expenses']);
        $lines[] = '';
        $lines[] = 'III. OVERALL EXPENSES';
        $lines[] = 'Total collections: '.Currency::format((float) $totals['contributions']);
        $lines[] = 'Total expenses: '.Currency::format((float) $totals['expenses']);
        $lines[] = 'Remaining balance: '.Currency::format((float) $totals['balance']);
        $lines[] = 'Over expenditure / deficit: '.Currency::format($overExpenditure);
        $lines[] = '';
        $lines[] = 'SIGNATURES';

        foreach ($signatures as $signature) {
            $lines[] = trim(implode(' | ', array_filter([
                $signature['title'] ?? '',
                $signature['name'] ?? '',
                $signature['position'] ?? '',
            ], fn (?string $value): bool => filled($value))));
        }

        return $lines;
    }

    private function documentXml(Event $event, array $totals, Collection $expenses, float $budgetGoal, float $overExpenditure, array $signatures): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>';
        $xml .= $this->wordParagraph('FundTrack Event Financial Report', true, 30);
        $xml .= $this->wordParagraph('Event: '.$event->name, true, 24);
        $xml .= $this->wordParagraph('Academic year: '.$event->academicYear->name.' · Generated: '.now()->format('F j, Y'));
        $xml .= $this->wordParagraph('I. TOTAL BUDGET', true, 22);
        $xml .= $this->wordTable([
            ['Description', 'Amount'],
            ['Contribution goal', Currency::format($budgetGoal)],
            ['Collections received', Currency::format((float) $totals['contributions'])],
        ]);
        $xml .= $this->wordParagraph('II. DISBURSEMENT', true, 22);
        $expenseRows = [['Quantity', 'Unit', 'Category and description', 'Unit price', 'Amount']];
        foreach ($expenses as $expense) {
            $expenseRows[] = ['1', 'Item', $expense->category->name.' — '.$expense->description, Currency::format((float) $expense->amount), Currency::format((float) $expense->amount)];
        }
        $expenseRows[] = ['', '', 'GRAND TOTAL', '', Currency::format((float) $totals['expenses'])];
        $xml .= $this->wordTable($expenseRows);
        $xml .= $this->wordParagraph('III. OVERALL EXPENSES', true, 22);
        $xml .= $this->wordTable([
            ['Description', 'Amount'],
            ['Total expenses', Currency::format((float) $totals['expenses'])],
            ['Remaining balance', Currency::format((float) $totals['balance'])],
            ['Over expenditure / deficit', Currency::format($overExpenditure)],
        ]);
        $xml .= $this->wordParagraph('Signatures', true, 22);
        $signatureRows = [['Title', 'Name', 'Position']];
        foreach ($signatures as $signature) {
            $signatureRows[] = [$signature['title'] ?? '', $signature['name'] ?? '', $signature['position'] ?? ''];
        }
        if (count($signatureRows) === 1) {
            $signatureRows[] = ['', '', ''];
        }
        $xml .= $this->wordTable($signatureRows);
        $xml .= '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr></w:body></w:document>';

        return $xml;
    }

    private function wordParagraph(string $text, bool $bold = false, int $size = 22): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $runProperties = ($bold ? '<w:b/>' : '').'<w:sz w:val="'.$size.'"/>';

        return '<w:p><w:r><w:rPr>'.$runProperties.'</w:rPr><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';
    }

    /** @param list<list<string>> $rows */
    private function wordTable(array $rows): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $xml .= '<w:'.$edge.' w:val="single" w:sz="4" w:space="0" w:color="777777"/>';
        }
        $xml .= '</w:tblBorders></w:tblPr><w:tblGrid>';
        $columnCount = max(1, count($rows[0] ?? []));
        for ($column = 0; $column < $columnCount; $column++) {
            $xml .= '<w:gridCol w:w="'.(9360 / $columnCount).'"/>';
        }
        $xml .= '</w:tblGrid>';
        foreach ($rows as $rowIndex => $row) {
            $xml .= '<w:tr>';
            foreach (array_pad($row, $columnCount, '') as $cell) {
                $escaped = htmlspecialchars($cell, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.(9360 / $columnCount).'" w:type="dxa"/></w:tcPr><w:p><w:r>'.($rowIndex === 0 ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p></w:tc>';
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
