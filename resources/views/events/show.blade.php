@extends('layouts.app', ['pageTitle' => 'Event Details'])

@section('content')
    @php($collectedTotal = (float) $event->collections->sum(fn ($collection) => (float) $collection->collected_amount))
    <div class="page-toolbar"><div><span class="status-pill status-pill--{{ $event->status }}">{{ ucfirst($event->status) }}</span><h2 class="page-title">{{ $event->name }}</h2></div><div class="row-actions"><a class="button button--outline" href="{{ route('events.index') }}">Back to Events</a>@can('manage-finances')<a class="button button--primary" href="{{ route('collections.create', ['event_id' => $event->id]) }}">Add Collection</a>@endcan @can('manage-events')@if($event->status !== 'archived')<a class="button button--outline" href="{{ route('events.edit', $event) }}">Edit Event</a>@endif @endcan</div></div>
    <section class="detail-grid">
        <article class="detail-card"><small>Academic year</small><strong>{{ $event->academicYear->name }}</strong></article>
        <article class="detail-card"><small>Event date</small><strong>{{ $event->event_date?->format('M j, Y') ?? 'Not set' }}</strong></article>
        <article class="detail-card"><small>Venue</small><strong>{{ $event->venue ?: 'Not set' }}</strong></article>
        <article class="detail-card"><small>Contribution target</small><strong>{{ \App\Support\Currency::format((float) $event->contribution_target) }}</strong></article>
        <article class="detail-card"><small>Posted contributions</small><strong>{{ \App\Support\Currency::format($collectedTotal) }}</strong></article>
        <article class="detail-card"><small>Approved expenses</small><strong>{{ \App\Support\Currency::format((float) $event->approved_expenses) }}</strong></article>
    </section>
    <section class="panel"><div class="panel-heading"><h2>Description</h2></div><p>{{ $event->description ?: 'No description provided.' }}</p><p class="form-help">Created by {{ $event->creator?->name ?? 'System' }} · {{ $event->created_at->format('M j, Y') }}</p></section>
@endsection
