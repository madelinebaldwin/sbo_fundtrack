<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\ActivityLogger;
use App\Services\EventReportExporter;
use App\Services\FinancialReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class EventReportController extends Controller
{
    public function __construct(
        private readonly EventReportExporter $reportExporter,
        private readonly FinancialReportService $financialReportService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function pdf(Request $request, Event $event): Response
    {
        return $this->export($request, $event, 'pdf');
    }

    public function docx(Request $request, Event $event): Response
    {
        return $this->export($request, $event, 'docx');
    }

    public function saveSignatures(Request $request, Event $event): RedirectResponse
    {
        $event->update(['report_signatures' => $this->validatedSignatures($request)]);

        return redirect()->route('events.manage.reports', $event)->with('status', 'Report signatures saved and updated on the report.');
    }

    private function export(Request $request, Event $event, string $format): Response
    {
        $signatures = collect($this->validatedSignatures($request))
            ->filter(fn (array $signature): bool => filled($signature['title']) || filled($signature['name']) || filled($signature['position']))
            ->values()
            ->all();
        if ($signatures === []) {
            $signatures = $event->report_signatures ?? [];
        }

        $event->load('academicYear');
        $totals = $this->financialReportService->totals(['event_id' => $event->id]);
        $expenses = $event->expenses()
            ->with('category:id,name')
            ->where('status', 'approved')
            ->orderBy('expense_category_id')
            ->orderBy('transaction_date')
            ->get();
        $totalBudget = (float) $totals['contributions'];
        $expenseTotal = (float) $totals['expenses'];
        $overExpenditure = max(0, $expenseTotal - (float) $totals['contributions']);
        $content = $format === 'pdf'
            ? $this->reportExporter->pdf($event, $totals, $expenses, $totalBudget, $overExpenditure, $signatures)
            : $this->reportExporter->docx($event, $totals, $expenses, $totalBudget, $overExpenditure, $signatures);

        $this->activityLogger->record('report.exported', 'Exported event report ('.$format.'): '.$event->name, $event, ['format' => $format]);

        $filename = Str::slug($event->name).'-financial-report-'.now()->format('Ymd').'.'.$format;
        $contentType = $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /** @return list<array{title: string, name: string, position: string}> */
    private function validatedSignatures(Request $request): array
    {
        $validated = $request->validate([
            'signatures' => ['nullable', 'array', 'max:8'],
            'signatures.*' => ['array:title,name,position'],
            'signatures.*.title' => ['nullable', 'string', 'max:100'],
            'signatures.*.name' => ['nullable', 'string', 'max:120'],
            'signatures.*.position' => ['nullable', 'string', 'max:100'],
        ]);

        return collect($validated['signatures'] ?? [])
            ->map(fn (array $signature): array => [
                'title' => trim((string) ($signature['title'] ?? '')),
                'name' => trim((string) ($signature['name'] ?? '')),
                'position' => trim((string) ($signature['position'] ?? '')),
            ])
            ->values()
            ->all();
    }
}
