<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Services\FinancialReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(FinancialReportService $financialReports): View
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        $filters = $academicYear ? [
            'from' => $academicYear->starts_at->toDateString(),
            'to' => $academicYear->ends_at->toDateString(),
        ] : [];
        $monthlyTotals = $financialReports->monthlyTotals($filters, $academicYear);
        $totals = $financialReports->totals($filters);
        $distribution = $financialReports->expenseDistribution($filters);
        $latestTransactions = $financialReports->transactionsQuery($filters)
            ->orderByDesc('transaction_date')
            ->orderByDesc('transaction_id')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'academicYear' => $academicYear,
            'eventCount' => Event::query()->where('status', '!=', 'archived')
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->count(),
            'totals' => $totals,
            'monthlyTotals' => $monthlyTotals,
            'expenseDistribution' => $distribution,
            'latestTransactions' => $latestTransactions,
            'activities' => ActivityLog::query()->with('actor:id,name')->latest()->limit(8)->get(),
        ]);
    }
}
