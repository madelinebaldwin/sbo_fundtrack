@extends('layouts.app', ['pageTitle' => 'Events'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'overview'])

    <section class="event-summary-grid" aria-label="Event financial summary">
        <article class="event-summary-card"><h3>Collections</h3><p>{{ \App\Support\Currency::format((float) $totals['contributions']) }}</p></article>
        <article class="event-summary-card"><h3>Expenses</h3><p>{{ \App\Support\Currency::format((float) $totals['expenses']) }}</p></article>
        <article class="event-summary-card"><h3>Remaining</h3><p class="{{ (float) $totals['balance'] < 0 ? 'is-negative' : '' }}">{{ \App\Support\Currency::format((float) $totals['balance']) }}</p></article>
    </section>

    <section class="event-overview-grid">
        <article class="event-details-panel panel">
            <h3>Event Details</h3>
            <dl>
                <div><dt>Date</dt><dd>@if($event->starts_at){{ $event->starts_at->format('M j, Y') }}@if($event->ends_at && !$event->ends_at->isSameDay($event->starts_at)) – {{ $event->ends_at->format('M j, Y') }}@endif @else{{ $event->event_date?->format('M j, Y') ?? 'Not set' }}@endif</dd></div>
                <div><dt>Academic year</dt><dd>{{ $event->academicYear->name }}</dd></div>
                <div><dt>Contribution goal</dt><dd>{{ \App\Support\Currency::format($budgetGoal) }}</dd></div>
                <div class="event-details-description"><dt>Description</dt><dd>{{ $event->description ?: 'No description provided.' }}</dd></div>
            </dl>
        </article>

        <aside class="event-budget-panel panel" aria-label="Budget goal progress">
            <div><h3>Budget Goal</h3><p class="event-budget-value">{{ \App\Support\Currency::format($budgetGoal) }}</p></div>
            @if ($budgetUsedPercent === null)
                <p class="event-budget-empty">Set a contribution goal to see budget progress.</p>
            @else
                <div class="event-budget-visual" role="img" aria-label="{{ $budgetUsedPercent }}% of the contribution goal spent; {{ $budgetRemainingPercent }}% remaining">
                    <div class="event-budget-fill" style="height: {{ $budgetUsedPercent }}%"></div>
                </div>
                <div class="event-budget-legend"><p><strong>{{ $budgetUsedPercent }}%</strong><span>Used</span></p><p><strong>{{ $budgetRemainingPercent }}%</strong><span>Remaining</span></p></div>
            @endif
        </aside>
    </section>
@endsection
