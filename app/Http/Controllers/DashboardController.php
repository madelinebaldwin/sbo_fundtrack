<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Services\FinancialReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $financialReports): View|RedirectResponse
    {
        $academicYears = AcademicYear::query()->orderByDesc('start_year')->get();
        $activeAcademicYear = $academicYears->firstWhere('is_active', true);
        $defaultAcademicYearId = $activeAcademicYear?->id ?? $academicYears->first()?->id;
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
        ]);
        $selectedAcademicYearId = $request->query('academic_year_id', $defaultAcademicYearId);
        $academicYear = $academicYears->firstWhere('id', (int) ($validated['academic_year_id'] ?? $selectedAcademicYearId));
        $filters = $academicYear ? ['academic_year_id' => $academicYear->id] : [];
        $monthlyTotals = $financialReports->monthlyTotals($filters, $academicYear);
        $totals = $financialReports->totals($filters);
        $distribution = $financialReports->expenseDistribution($filters);
        $latestTransactions = $financialReports->transactionsQuery($filters)
            ->orderByDesc('transaction_date')
            ->orderByDesc('transaction_id')
            ->orderByDesc('source')
            ->paginate(5, ['*'], 'transactions_page')
            ->withQueryString();
        $activities = ActivityLog::query()
            ->with('actor:id,name')
            ->latest()
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'activities_page')
            ->withQueryString();

        if ($redirect = $this->redirectIfPaginationPageIsUnavailable($latestTransactions)) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfPaginationPageIsUnavailable($activities)) {
            return $redirect;
        }

        return view('dashboard', [
            'academicYear' => $academicYear,
            'academicYears' => $academicYears,
            'selectedAcademicYearId' => $academicYear?->id,
            'eventCount' => Event::query()->where('status', '!=', 'archived')
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->count(),
            'totals' => $totals,
            'monthlyTotals' => $monthlyTotals,
            'expenseDistribution' => $distribution,
            'latestTransactions' => $latestTransactions,
            'activities' => $activities,
        ]);
    }
}
