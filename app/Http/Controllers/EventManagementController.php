<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Collection;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\Expense;
use App\Services\FinancialReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventManagementController extends Controller
{
    public function __construct(private readonly FinancialReportService $financialReportService) {}

    public function redirect(Event $event): RedirectResponse
    {
        return redirect()->route('events.manage.overview', $event);
    }

    public function overview(Event $event): View
    {
        $event->load('academicYear');
        $totals = $this->financialReportService->totals(['event_id' => $event->id]);
        $budgetGoal = (float) $event->contribution_target;
        $approvedExpenses = (float) $totals['expenses'];
        $budgetUsedPercent = $budgetGoal > 0 ? min(100, (int) round($approvedExpenses / $budgetGoal * 100)) : null;

        return view('events.manage.overview', [
            'event' => $event,
            'totals' => $totals,
            'budgetGoal' => $budgetGoal,
            'budgetUsedPercent' => $budgetUsedPercent,
            'budgetRemainingPercent' => $budgetUsedPercent === null ? null : 100 - $budgetUsedPercent,
        ]);
    }

    public function collections(Event $event): View
    {
        $collections = $event->collections()
            ->withSum(['contributions as collected_amount' => fn (Builder $query) => $query->where('status', 'posted')], 'amount')
            ->withCount(['contributions as posted_contributions_count' => fn (Builder $query) => $query->where('status', 'posted')])
            ->withCount('contributions')
            ->orderBy('year_section')
            ->get();
        $expectedTotal = $collections->sum(fn (Collection $collection): float => $collection->student_count * (float) $collection->amount_per_student);
        $collectedTotal = $collections->sum(fn (Collection $collection): float => (float) $collection->collected_amount);
        $goal = (float) $event->contribution_target;

        return view('events.manage.collections', [
            'event' => $event,
            'collections' => $collections,
            'expectedTotal' => $expectedTotal,
            'collectedTotal' => $collectedTotal,
            'goal' => $goal,
            'goalProgress' => $goal > 0 ? min(100, (int) round($collectedTotal / $goal * 100)) : null,
        ]);
    }

    public function expenses(Event $event): View
    {
        $expenses = $event->expenses()
            ->with(['category:id,name', 'creator:id,name', 'reviewer:id,name'])
            ->latest('transaction_date')
            ->paginate(15);

        return view('events.manage.expenses', compact('event', 'expenses'));
    }

    public function reports(Event $event): View
    {
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

        return view('events.manage.reports', [
            'event' => $event,
            'totals' => $totals,
            'expenses' => $expenses,
            'budgetGoal' => $budgetGoal,
            'overExpenditure' => $budgetGoal > 0 ? max(0, $expenseTotal - $budgetGoal) : max(0, $expenseTotal - (float) $totals['contributions']),
        ]);
    }

    public function analytics(Event $event): View
    {
        $event->load('academicYear');
        $totals = $this->financialReportService->totals(['event_id' => $event->id]);
        [$periodStart, $periodEnd] = $this->reportingPeriod($event);
        $monthlyTotals = $this->financialReportService->monthlyTotals([
            'event_id' => $event->id,
            'from' => $periodStart->toDateString(),
            'to' => $periodEnd->toDateString(),
        ]);
        $monthlyTotals['labels'] = $this->monthLabels($periodStart, $periodEnd);
        $expenseCategories = $event->expenses()
            ->where('expenses.status', 'approved')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->select('expense_categories.name as label')
            ->selectRaw('SUM(expenses.amount) as amount')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn (object $row): array => ['label' => $row->label, 'amount' => (float) $row->amount])
            ->all();

        return view('events.manage.analytics', compact('event', 'totals', 'monthlyTotals', 'expenseCategories'));
    }

    public function history(Event $event): View
    {
        $collectionIds = $event->collections()->select('id');
        $contributionIds = Contribution::query()->whereIn('collection_id', $collectionIds)->select('id');
        $expenseIds = $event->expenses()->select('id');
        $activities = ActivityLog::query()
            ->with('actor:id,name')
            ->where(function (Builder $query) use ($event, $collectionIds, $contributionIds, $expenseIds): void {
                $query->where(function (Builder $eventLogs) use ($event): void {
                    $eventLogs->where('subject_type', Event::class)->where('subject_id', $event->id);
                })->orWhere(function (Builder $collectionLogs) use ($collectionIds): void {
                    $collectionLogs->where('subject_type', Collection::class)->whereIn('subject_id', $collectionIds);
                })->orWhere(function (Builder $contributionLogs) use ($contributionIds): void {
                    $contributionLogs->where('subject_type', Contribution::class)->whereIn('subject_id', $contributionIds);
                })->orWhere(function (Builder $expenseLogs) use ($expenseIds): void {
                    $expenseLogs->where('subject_type', Expense::class)->whereIn('subject_id', $expenseIds);
                });
            })
            ->latest()
            ->paginate(25);

        return view('events.manage.history', compact('event', 'activities'));
    }

    public function payment(Event $event, Collection $collection): View
    {
        $collection = $event->collections()->whereKey($collection->id)->firstOrFail();
        $collected = (float) $collection->contributions()->where('status', 'posted')->sum('amount');
        $expected = $collection->student_count * (float) $collection->amount_per_student;

        return view('events.manage.payment', [
            'event' => $event,
            'collection' => $collection,
            'collected' => $collected,
            'expected' => $expected,
            'remaining' => max(0, $expected - $collected),
            'submissionKey' => (string) Str::uuid(),
        ]);
    }

    /** @return array{Carbon, Carbon} */
    private function reportingPeriod(Event $event): array
    {
        $start = $event->starts_at ?? $event->event_date;
        $end = $event->ends_at ?? $event->event_date;

        if ($start === null && $end === null) {
            return [now()->startOfYear(), now()->endOfYear()];
        }

        $start = $start?->copy() ?? $end->copy();
        $end = $end?->copy() ?? $start->copy();

        return [$start->startOfMonth(), $end->endOfMonth()];
    }

    /** @return list<string> */
    private function monthLabels(Carbon $start, Carbon $end): array
    {
        $labels = [];
        $month = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();

        while ($month->lessThanOrEqualTo($lastMonth)) {
            $labels[] = $month->format('M Y');
            $month->addMonth();
        }

        return $labels;
    }
}
