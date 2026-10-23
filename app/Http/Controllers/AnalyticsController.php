<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Event;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $financialReports): View
    {
        $filters = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        $chartFilters = $filters;

        if ($academicYear && ! isset($filters['from']) && ! isset($filters['to'])) {
            $chartFilters['from'] = $academicYear->starts_at->toDateString();
            $chartFilters['to'] = $academicYear->ends_at->toDateString();
        }

        return view('analytics.index', [
            'filters' => $filters,
            'events' => Event::query()->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name']),
            'totals' => $financialReports->totals($chartFilters),
            'monthlyTotals' => $financialReports->monthlyTotals($chartFilters, $academicYear),
            'expenseDistribution' => $financialReports->expenseDistribution($chartFilters),
        ]);
    }
}
