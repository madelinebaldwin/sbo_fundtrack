@extends('layouts.app', ['pageTitle' => 'Collections'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'collections'])

    <section class="event-collection-goal">
        <div class="event-section-heading"><div><p class="eyebrow">Collection Goal</p><h3>{{ \App\Support\Currency::format($goal) }}</h3></div><div class="event-goal-progress-label">{{ $goalProgress === null ? 'No goal set' : $goalProgress.'%' }}</div></div>
        <div class="event-progress-track" role="img" aria-label="{{ $goalProgress === null ? 'No contribution goal set' : $goalProgress.' percent of contribution goal collected' }}"><span style="width: {{ $goalProgress ?? 0 }}%"></span></div>
        <p class="form-help">{{ \App\Support\Currency::format($collectedTotal) }} collected of {{ \App\Support\Currency::format($goal) }} goal · {{ \App\Support\Currency::format($expectedTotal) }} expected across collection groups</p>
    </section>

    <div class="event-table-heading"><h3>Collections</h3>@can('manage-finances')@if($event->status === 'active')<a class="button button--primary" href="{{ route('events.manage.collections.create', $event) }}">+ Add Collection</a>@endif @endcan</div>
    <div class="table-wrap event-management-table"><table class="data-table">
        <thead><tr><th>Year / Section</th><th class="numeric">Students</th><th class="numeric">Expected</th><th class="numeric">Collected</th><th class="numeric">Remaining</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($collections as $collection)
                @php($expected = $collection->student_count * (float) $collection->amount_per_student)
                @php($collected = (float) $collection->collected_amount)
                <tr id="collection-{{ $collection->id }}">
                    <td>{{ $collection->year_section }}</td>
                    <td class="numeric">{{ number_format($collection->student_count) }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format($expected) }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format($collected) }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format(max(0, $expected - $collected)) }}</td>
                    <td><div class="row-actions"><a class="button button--primary button--small" href="{{ route('events.manage.collections.payment', [$event, $collection]) }}">View Payment</a>@can('manage-finances')@if ($collection->contributions_count === 0)<form method="POST" action="{{ route('events.manage.collections.destroy', [$event, $collection]) }}" data-confirm="Delete this collection? This cannot be undone.">@csrf @method('DELETE')<button class="button button--danger button--small" type="submit">Delete</button></form>@else<button class="button button--danger button--small" type="button" disabled title="Collections with recorded contributions cannot be deleted.">Delete</button>@endif @endcan</div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No collection groups have been created for this event.</td></tr>
            @endforelse
        </tbody>
    </table></div>
@endsection
