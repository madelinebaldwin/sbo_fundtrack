<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\ActivityLogger;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, FinancialReportService $financialReports): View
    {
        $filters = $this->validatedFilters($request);
        $transactions = $financialReports->transactionsQuery($filters)
            ->orderByDesc('transaction_date')
            ->orderByDesc('transaction_id')
            ->paginate(20)
            ->withQueryString();

        return view('reports.index', [
            'transactions' => $transactions,
            'events' => Event::query()->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, FinancialReportService $financialReports, ActivityLogger $activityLogger): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $totals = $financialReports->totals($filters);
        $eventName = isset($filters['event_id']) ? Event::query()->whereKey($filters['event_id'])->value('name') : 'All events';
        $activityLogger->record('report.exported', 'Exported a filtered financial report.', null, [
            'event_id' => isset($filters['event_id']) ? (int) $filters['event_id'] : null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ]);

        $transactions = $financialReports->transactionsQuery($filters)
            ->orderByDesc('transaction_date')
            ->orderByDesc('transaction_id');

        return response()->streamDownload(function () use ($transactions, $filters, $totals, $eventName): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['FundTrack Financial Report']);
            fputcsv($output, ['Generated', now()->format('Y-m-d H:i:s T')]);
            fputcsv($output, ['Event', $this->safeCsvCell((string) $eventName), 'From', $filters['from'] ?? 'Any date', 'To', $filters['to'] ?? 'Any date']);
            fputcsv($output, []);
            fputcsv($output, ['Transaction', 'Date', 'Event', 'Category', 'Description', 'Amount (PHP)']);

            foreach ($transactions->cursor() as $transaction) {
                fputcsv($output, [
                    $transaction->transaction_type,
                    $transaction->transaction_date,
                    $this->safeCsvCell($transaction->event_name),
                    $this->safeCsvCell($transaction->category_name),
                    $this->safeCsvCell($transaction->description),
                    number_format((float) $transaction->amount, 2, '.', ''),
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['Total contributions (PHP)', $totals['contributions']]);
            fputcsv($output, ['Total expenses (PHP)', $totals['expenses']]);
            fputcsv($output, ['Available balance (PHP)', $totals['balance']]);

            fclose($output);
        }, 'fundtrack-report-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{event_id?: int, from?: string, to?: string} */
    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
