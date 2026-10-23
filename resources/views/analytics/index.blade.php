@extends('layouts.app', ['pageTitle' => 'Analytics'])

@section('content')
    <form class="filter-bar" method="GET" action="{{ route('analytics.index') }}">
        <div class="filter-fields">
            <label class="filter-field">Event<select name="event_id"><option value="">All events</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) ($filters['event_id'] ?? '') === (string) $event->id)>{{ $event->name }}</option>@endforeach</select></label>
            <label class="filter-field">From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="filter-field">To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <div class="filter-actions"><button class="button button--primary" type="submit">Filter</button><a class="button button--outline" href="{{ route('analytics.index') }}">Clear</a></div>
        </div>
    </form>

    <section class="summary-grid summary-grid--three" aria-label="Selected financial totals">
        <article class="summary-card"><h2>Total Collections</h2><p>{{ \App\Support\Currency::format((float) $totals['contributions']) }}</p></article>
        <article class="summary-card"><h2>Total Expenses</h2><p>{{ \App\Support\Currency::format((float) $totals['expenses']) }}</p></article>
        <article class="summary-card"><h2>Available balance</h2><p>{{ \App\Support\Currency::format((float) $totals['balance']) }}</p></article>
    </section>

    <section class="chart-grid">
        <article class="panel chart-panel"><div class="panel-heading"><h2>Contributions vs Expenses</h2></div>
            @if (array_sum($monthlyTotals['contributions']) + array_sum($monthlyTotals['expenses']) > 0)
                <div class="chart-wrap"><canvas data-fundtrack-chart="bar" data-chart="{{ json_encode($monthlyTotals, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}" aria-label="Contributions and expenses by month"></canvas></div>
            @else
                <div class="chart-empty">No posted transactions match this date range.</div>
            @endif
        </article>
        <article class="panel chart-panel"><div class="panel-heading"><h2>Expenses by Events</h2></div>
            @if ($expenseDistribution->isNotEmpty())
                <div class="chart-wrap chart-wrap--doughnut"><canvas data-fundtrack-chart="doughnut" data-chart="{{ json_encode($expenseDistribution, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}" aria-label="Expense distribution by event"></canvas></div>
            @else
                <div class="chart-empty">Approved expenses will appear when there is activity.</div>
            @endif
        </article>
    </section>
@endsection
