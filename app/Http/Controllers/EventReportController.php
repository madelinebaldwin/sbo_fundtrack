<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\ActivityLogger;
use App\Services\EventReportExporter;
use App\Services\FinancialReportService;
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

    private function export(Request $request, Event $event, string $format): Response
    {
        $validated = $request->validate([
            'signatures' => ['nullable', 'array', 'max:8'],
            'signatures.*' => ['array:title,name,position'],
            'signatures.*.title' => ['nullable', 'string', 'max:100'],
            'signatures.*.name' => ['nullable', 'string', 'max:120'],
            'signatures.*.position' => ['nullable', 'string', 'max:100'],
        ]);
        $signatures = collect($validated['signatures'] ?? [])
            ->map(fn (array $signature): array => [
                'title' => trim((string) ($signature['title'] ?? '')),
                'name' => trim((string) ($signature['name'] ?? '')),
                'position' => trim((string) ($signature['position'] ?? '')),
            ])
            ->filter(fn (array $signature): bool => filled($signature['title']) || filled($signature['name']) || filled($signature['position']))
            ->values()
            ->all();

        $event->load('academicYear');
        $totals = $this->financialReportService->totals(['event_id' => $event->id]);
        $expenses = $event->expenses()
            ->with('category:id,name')
            ->where('status', 'approved')
            ->orderBy('expense_category_id')
            ->orderBy('transaction_date')
            ->get();
        $budgetGoal = (float) $event->contribution_target;
        $expenseTotal = (float) $totals['expenses'];
        $overExpenditure = $budgetGoal > 0
            ? max(0, $expenseTotal - $budgetGoal)
            : max(0, $expenseTotal - (float) $totals['contributions']);
        $content = $format === 'pdf'
            ? $this->reportExporter->pdf($event, $totals, $expenses, $budgetGoal, $overExpenditure, $signatures)
            : $this->reportExporter->docx($event, $totals, $expenses, $budgetGoal, $overExpenditure, $signatures);

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
}
