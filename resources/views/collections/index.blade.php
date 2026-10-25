@extends('layouts.app', ['pageTitle' => 'Collections'])

@section('content')
    <form class="filter-bar filter-bar--spaced" method="GET" action="{{ route('collections.index') }}">
        <div class="filter-fields">
            <label class="filter-field filter-field--search">Search<input type="search" name="search" value="{{ request('search') }}" placeholder="Year level or section"></label>
            <label class="filter-field">Academic year<select name="academic_year_id"><option value="">All years</option>@foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected((string) request('academic_year_id') === (string) $year->id)>{{ $year->name }}</option>@endforeach</select></label>
            <label class="filter-field">Event<select name="event_id"><option value="">All events</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->name }}</option>@endforeach</select></label>
            <div class="filter-actions"><button class="button button--primary" type="submit">Filter</button><a class="button button--outline" href="{{ route('collections.index') }}">Clear</a></div>
        </div>
        @can('manage-finances')<a class="button button--primary" href="{{ route('collections.create') }}">+ Add Collection</a>@endcan
    </form>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Academic year</th><th>Year / Section</th><th>Event</th><th class="numeric">Students</th><th class="numeric">Expected</th><th class="numeric">Collected</th><th class="numeric">Remaining</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($collections as $collection)
                @php($expected = (float) $collection->amount_per_student * $collection->student_count)
                @php($collected = (float) ($collection->collected_amount ?? 0))
                <tr>
                    <td>{{ $collection->event->academicYear->name }}</td><td>{{ $collection->year_section }}</td><td>{{ $collection->event->name }}</td>
                    <td class="numeric">{{ number_format($collection->student_count) }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format($expected) }}</td><td class="numeric">{{ \App\Support\Currency::format($collected) }}</td>
                    <td class="numeric">@if($collected > $expected) Over by {{ \App\Support\Currency::format($collected - $expected) }} @else {{ \App\Support\Currency::format($expected - $collected) }} @endif</td>
                    <td><div class="row-actions">@can('manage-finances')<a class="button button--primary button--small" href="{{ route('events.manage.collections.payment', [$collection->event, $collection]) }}">Manage</a>@else<a class="button button--primary button--small" href="{{ route('events.manage.collections', $collection->event) }}#collection-{{ $collection->id }}">Manage</a>@endcan @can('manage-finances')@if ($collection->contributions_count === 0)<form method="POST" action="{{ route('collections.destroy', $collection) }}" data-confirm="Delete this collection? This cannot be undone.">@csrf @method('DELETE')<button class="button button--danger button--small" type="submit">Delete</button></form>@else<button class="button button--danger button--small" type="button" disabled title="Collections with recorded contributions cannot be deleted.">Delete</button>@endif @endcan</div></td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No collection targets match these filters. Add a target to start recording contributions.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $collections->links() }}</div>
@endsection
