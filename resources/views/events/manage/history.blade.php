@extends('layouts.app', ['pageTitle' => 'Events'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'history'])

    <section class="panel event-history-panel"><div class="panel-heading"><div><h3>Event History</h3><p class="form-help">Recorded changes and financial activity for this event.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Date and time</th><th>Activity</th><th>Description</th><th class="numeric">Amount</th><th>User</th></tr></thead><tbody>
            @forelse ($activities as $activity)
                <tr><td>{{ $activity->created_at->format('M j, Y g:i A') }}</td><td>{{ str($activity->action)->replace('.', ' ')->title() }}</td><td>{{ $activity->description }}</td><td class="numeric">@if(is_numeric(data_get($activity->metadata, 'amount'))){{ \App\Support\Currency::format((float) data_get($activity->metadata, 'amount')) }}@else—@endif</td><td>{{ $activity->actor?->name ?? 'System' }}</td></tr>
            @empty
                <tr><td colspan="5" class="table-empty">No activity has been recorded for this event.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="pagination">{{ $activities->links() }}</div>
    </section>
@endsection
