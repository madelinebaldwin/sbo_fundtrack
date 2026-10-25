@extends('layouts.app', ['pageTitle' => 'Events'])

@section('content')
    <form class="filter-bar filter-bar--spaced" method="GET" action="{{ route('events.index') }}">
        <div class="filter-fields">
            <label class="filter-field filter-field--search">Search event<input type="search" name="search" value="{{ request('search') }}" placeholder="Event name"></label>
        </div>
        <div class="filter-actions"><button class="button button--primary" type="submit">Filter</button><a class="button button--outline" href="{{ route('events.index') }}">Clear</a></div>
        @can('manage-events')<span class="filter-spacer"></span><a class="button button--primary" href="{{ route('events.create') }}">+ Add Event</a>@endcan
    </form>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Event</th><th>Date</th><th>Academic year</th><th class="numeric">Total Collections</th><th class="numeric">Total Expenses</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ $event->name }}</td>
                    <td>{{ $event->event_date?->format('M j, Y') ?? '—' }}</td>
                    <td>{{ $event->academicYear->name }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format((float) $event->total_collected) }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format((float) $event->total_expenses) }}</td>
                    <td><div class="row-actions"><a class="button button--primary button--small" href="{{ route('events.manage.overview', $event) }}">Manage</a>@can('manage-events')@if ($event->collections_count === 0 && $event->expenses_count === 0)<form method="POST" action="{{ route('events.destroy', $event) }}" data-confirm="Delete this event? This cannot be undone.">@csrf @method('DELETE')<button class="button button--danger button--small" type="submit">Delete</button></form>@else<button class="button button--danger button--small" type="button" disabled title="Events with financial records cannot be deleted.">Delete</button>@endif @endcan</div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No events yet. Add an event to start tracking organization activity.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $events->links() }}</div>
@endsection
