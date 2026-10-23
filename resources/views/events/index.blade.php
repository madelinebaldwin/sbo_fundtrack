@extends('layouts.app', ['pageTitle' => 'Events'])

@section('content')
    <form class="filter-bar filter-bar--spaced" method="GET" action="{{ route('events.index') }}">
        <div class="filter-fields">
            <label class="filter-field filter-field--search">Search event<input type="search" name="search" value="{{ request('search') }}" placeholder="Event name"></label>
            <label class="filter-field">Status<select name="status"><option value="">All statuses</option>@foreach (['active', 'archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        </div>
        <div class="filter-actions"><button class="button button--primary" type="submit">Filter</button><a class="button button--outline" href="{{ route('events.index') }}">Clear</a></div>
        @can('manage-events')<span class="filter-spacer"></span><a class="button button--primary" href="{{ route('events.create') }}">+ Add Event</a>@endcan
    </form>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Event</th><th>Date</th><th>Academic year</th><th class="numeric">Target</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse ($events as $event)
                <tr>
                    <td><a class="text-link" href="{{ route('events.show', $event) }}">{{ $event->name }}</a></td>
                    <td>{{ $event->event_date?->format('M j, Y') ?? '—' }}</td>
                    <td>{{ $event->academicYear->name }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format((float) $event->contribution_target) }}</td>
                    <td><span class="status-pill status-pill--{{ $event->status }}">{{ ucfirst($event->status) }}</span></td>
                    <td><div class="row-actions"><a class="button button--primary button--small" href="{{ route('events.show', $event) }}">View</a>@can('manage-events') @if ($event->status !== 'archived')<a class="button button--outline button--small" href="{{ route('events.edit', $event) }}">Edit</a><form method="POST" action="{{ route('events.archive', $event) }}" data-confirm="Archive this event? Financial records will be retained.">@csrf @method('PATCH')<button class="button button--muted button--small" type="submit">Archive</button></form>@endif @endcan</div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No events yet. Add an event to start tracking organization activity.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $events->links() }}</div>
@endsection
