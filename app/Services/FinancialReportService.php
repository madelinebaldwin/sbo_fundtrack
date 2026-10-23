<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /** @param array{event_id?: int|string|null, from?: string|null, to?: string|null} $filters */
    public function transactionsQuery(array $filters = []): Builder
    {
        $contributions = DB::table('contributions as contribution')
            ->join('collections as collection', 'collection.id', '=', 'contribution.collection_id')
            ->join('events as event', 'event.id', '=', 'collection.event_id')
            ->where('contribution.status', 'posted')
            ->selectRaw("'Contribution' as transaction_type")
            ->addSelect([
                'contribution.id as transaction_id',
                'collection.id as target_id',
                'contribution.paid_at as transaction_date',
                'event.id as event_id',
                'event.name as event_name',
                DB::raw("'Contribution' as category_name"),
                'contribution.student_reference as description',
                'contribution.amount',
                'contribution.status',
                DB::raw("'contribution' as source"),
            ]);

        $expenses = DB::table('expenses as expense')
            ->join('events as event', 'event.id', '=', 'expense.event_id')
            ->join('expense_categories as category', 'category.id', '=', 'expense.expense_category_id')
            ->where('expense.status', 'approved')
            ->selectRaw("'Expense' as transaction_type")
            ->addSelect([
                'expense.id as transaction_id',
                'expense.id as target_id',
                'expense.transaction_date',
                'event.id as event_id',
                'event.name as event_name',
                'category.name as category_name',
                'expense.description',
                'expense.amount',
                'expense.status',
                DB::raw("'expense' as source"),
            ]);

        $transactions = $contributions->unionAll($expenses);
        $query = DB::query()->fromSub($transactions, 'transactions');

        if (! empty($filters['event_id'])) {
            $query->where('event_id', (int) $filters['event_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('transaction_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('transaction_date', '<=', $filters['to']);
        }

        return $query;
    }

    /** @param array{event_id?: int|string|null, from?: string|null, to?: string|null} $filters
     * @return array{contributions: string, expenses: string, balance: string}
     */
    public function totals(array $filters = []): array
    {
        $totals = $this->transactionsQuery($filters)->selectRaw(
            "COALESCE(SUM(CASE WHEN source = 'contribution' THEN amount ELSE 0 END), 0) as contributions, COALESCE(SUM(CASE WHEN source = 'expense' THEN amount ELSE 0 END), 0) as expenses"
        )->first();

        $contributions = (float) $totals->contributions;
        $expenses = (float) $totals->expenses;

        return [
            'contributions' => number_format($contributions, 2, '.', ''),
            'expenses' => number_format($expenses, 2, '.', ''),
            'balance' => number_format($contributions - $expenses, 2, '.', ''),
        ];
    }

    /** @param array{event_id?: int|string|null, from?: string|null, to?: string|null} $filters
     * @return array{labels: list<string>, contributions: list<float>, expenses: list<float>}
     */
    public function monthlyTotals(array $filters = [], ?AcademicYear $academicYear = null): array
    {
        $yearFilters = $filters;
        $hasDateFilters = ! empty($filters['from']) || ! empty($filters['to']);

        if ($academicYear !== null && ! $hasDateFilters) {
            $yearFilters['from'] = $academicYear->starts_at->toDateString();
            $yearFilters['to'] = $academicYear->ends_at->toDateString();
            $start = $academicYear->starts_at->copy()->startOfMonth();
            $end = $academicYear->ends_at->copy()->startOfMonth();
        } elseif ($hasDateFilters) {
            $startDate = $filters['from'] ?? $filters['to'];
            $endDate = $filters['to'] ?? $filters['from'];
            $start = Carbon::parse($startDate)->startOfMonth();
            $end = Carbon::parse($endDate)->startOfMonth();
        } else {
            $start = now()->startOfYear();
            $end = now()->endOfYear()->startOfMonth();
        }

        $months = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');
            $months[$key] = ['label' => $cursor->format('M'), 'contributions' => 0.0, 'expenses' => 0.0];
            $cursor->addMonth();
        }

        $monthExpression = DB::connection()->getDriverName() === 'mysql'
            ? "DATE_FORMAT(transaction_date, '%Y-%m')"
            : "strftime('%Y-%m', transaction_date)";
        $totalsByMonth = $this->transactionsQuery($yearFilters)
            ->selectRaw($monthExpression.' as month_key, source, SUM(amount) as total')
            ->groupBy('month_key', 'source')
            ->get();

        foreach ($totalsByMonth as $row) {
            if (isset($months[$row->month_key])) {
                $key = $row->source === 'contribution' ? 'contributions' : 'expenses';
                $months[$row->month_key][$key] = (float) $row->total;
            }
        }

        return [
            'labels' => array_values(array_column($months, 'label')),
            'contributions' => array_values(array_column($months, 'contributions')),
            'expenses' => array_values(array_column($months, 'expenses')),
        ];
    }

    /** @param array{event_id?: int|string|null, from?: string|null, to?: string|null} $filters
     * @return SupportCollection<int, object{event_name: string, amount: string}>
     */
    public function expenseDistribution(array $filters = []): SupportCollection
    {
        $query = DB::table('expenses as expense')
            ->join('events as event', 'event.id', '=', 'expense.event_id')
            ->where('expense.status', 'approved')
            ->select('event.name as event_name')
            ->selectRaw('SUM(expense.amount) as amount')
            ->groupBy('event.id', 'event.name')
            ->orderByDesc('amount');

        if (! empty($filters['event_id'])) {
            $query->where('event.id', (int) $filters['event_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('expense.transaction_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('expense.transaction_date', '<=', $filters['to']);
        }

        return $query->get();
    }
}
