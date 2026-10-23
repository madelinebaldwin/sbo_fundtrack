@extends('layouts.app', ['pageTitle' => 'Dashboard'])

@section('content')
    <div class="page-toolbar dashboard-toolbar">
        <div class="academic-year">Academic Year: <strong>{{ now()->year }}-{{ now()->year + 1 }}</strong></div>
    </div>

    <section class="summary-grid summary-grid--four" aria-label="Financial summary">
        <article class="summary-card"><h2>Total Events</h2><p>{{ number_format($eventCount) }}</p></article>
        <article class="summary-card"><h2>Total Contributions</h2><p>{{ \App\Support\Currency::format((float) $totals['contributions']) }}</p></article>
        <article class="summary-card"><h2>Total Expenses</h2><p>{{ \App\Support\Currency::format((float) $totals['expenses']) }}</p></article>
        <article class="summary-card"><h2>Remaining</h2><p>{{ \App\Support\Currency::format((float) $totals['balance']) }}</p></article>
    </section>

    <section class="chart-grid" aria-label="Financial charts">
        <article class="panel chart-panel">
            <div class="panel-heading"><h2>Contributions vs Expenses</h2></div>
            @if (array_sum($monthlyTotals['contributions']) + array_sum($monthlyTotals['expenses']) > 0)
                <div class="chart-wrap"><canvas data-fundtrack-chart="bar" data-chart="{{ json_encode($monthlyTotals, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}" aria-label="Monthly contributions compared with expenses"></canvas></div>
            @else
                <div class="chart-empty">No posted financial activity for this academic year yet.</div>
            @endif
        </article>
        <article class="panel chart-panel">
            <div class="panel-heading"><h2>Expenses by Events</h2></div>
            @if ($expenseDistribution->isNotEmpty())
                <div class="chart-wrap chart-wrap--doughnut"><canvas data-fundtrack-chart="doughnut" data-chart="{{ json_encode($expenseDistribution, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}" aria-label="Expense distribution by event"></canvas></div>
            @else
                <div class="chart-empty">Approved expenses will appear here.</div>
            @endif
        </article>
    </section>

    <section class="lower-grid">
        <article class="panel">
            <div class="panel-heading"><div><p class="eyebrow">Latest entries</p><h2>Recent Transactions</h2></div><a class="text-link" href="{{ route('reports.index') }}">View all</a></div>
            <div class="table-wrap">
                <table class="data-table data-table--compact">
                    <thead><tr><th>Date</th><th>Type</th><th>Event</th><th>Description</th><th class="numeric">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($latestTransactions as $transaction)
                            <tr><td>{{ \Illuminate\Support\Carbon::parse($transaction->transaction_date)->format('M j, Y') }}</td><td>{{ $transaction->transaction_type }}</td><td>{{ $transaction->event_name }}</td><td>{{ $transaction->description }}</td><td class="numeric">{{ \App\Support\Currency::format((float) $transaction->amount) }}</td><td><span class="status-pill status-pill--{{ $transaction->status }}">{{ ucfirst($transaction->status) }}</span></td></tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">No posted transactions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
        <article class="panel">
            <div class="panel-heading"><div><p class="eyebrow">Audit trail</p><h2>Recent Activities</h2></div></div>
            <ol class="activity-list">
                @forelse ($activities as $activity)
                    <li><span class="activity-dot" aria-hidden="true"></span><div><p>{{ $activity->description }}</p><small>{{ $activity->actor?->name ?? 'System' }} · {{ $activity->created_at->diffForHumans() }}</small></div></li>
                @empty
                    <li class="activity-empty">Application activity will appear here as work is recorded.</li>
                @endforelse
            </ol>
        </article>
    </section>
@endsection
