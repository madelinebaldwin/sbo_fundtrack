@extends('layouts.app', ['pageTitle' => 'Analytics'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'analytics'])

    <section class="event-summary-grid" aria-label="Event financial summary">
        <article class="event-summary-card"><h3>Total Collections</h3><p>{{ \App\Support\Currency::format((float) $totals['contributions']) }}</p></article>
        <article class="event-summary-card"><h3>Total Expenses</h3><p>{{ \App\Support\Currency::format((float) $totals['expenses']) }}</p></article>
        <article class="event-summary-card"><h3>Available Balance</h3><p class="{{ (float) $totals['balance'] < 0 ? 'is-negative' : '' }}">{{ \App\Support\Currency::format((float) $totals['balance']) }}</p></article>
    </section>

    <section class="event-chart-grid">
        <article class="event-chart-panel panel"><h3>Contributions vs Expenses</h3>
            @if (array_sum($monthlyTotals['contributions']) + array_sum($monthlyTotals['expenses']) > 0)
                <div class="event-chart-wrap"><canvas data-fundtrack-chart="bar" data-chart="{{ json_encode($monthlyTotals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" aria-label="Monthly contributions and expenses for {{ $event->name }}"></canvas></div>
            @else
                <p class="chart-empty">No posted contributions or approved expenses in this event's date range.</p>
            @endif
        </article>
        <article class="event-chart-panel panel"><h3>Expenses by Category</h3>
            @if (count($expenseCategories))
                <div class="event-chart-wrap event-chart-wrap--doughnut"><canvas data-fundtrack-chart="doughnut" data-chart="{{ json_encode($expenseCategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" aria-label="Approved expenses grouped by category for {{ $event->name }}"></canvas></div>
            @else
                <p class="chart-empty">No approved expenses to chart yet.</p>
            @endif
        </article>
    </section>
@endsection
