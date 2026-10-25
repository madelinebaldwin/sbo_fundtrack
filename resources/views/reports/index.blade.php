@extends('layouts.app', ['pageTitle' => 'Reports'])

@section('content')
    <form class="filter-bar filter-bar--spaced" method="GET" action="{{ route('reports.index') }}">
        <div class="filter-fields">
            <label class="filter-field">Event<select name="event_id"><option value="">All events</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) ($filters['event_id'] ?? '') === (string) $event->id)>{{ $event->name }}</option>@endforeach</select></label>
            <label class="filter-field">From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="filter-field">To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <div class="filter-actions"><button class="button button--primary" type="submit">Filter</button><a class="button button--outline" href="{{ route('reports.index') }}">Clear</a></div>
        </div>
        <span class="filter-spacer"></span><a class="button button--primary" href="{{ route('reports.export', $filters) }}">↓ Download Report</a>
    </form>

    <div class="table-wrap"><table class="data-table"><thead><tr><th>Transaction</th><th>Date</th><th>Event</th><th>Category</th><th>Description</th><th class="numeric">Amount</th><th>Action</th></tr></thead><tbody>
        @forelse ($transactions as $transaction)
            <tr>
                <td>{{ $transaction->transaction_type }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($transaction->transaction_date)->format('M j, Y') }}</td>
                <td>{{ $transaction->event_name }}</td>
                <td>{{ $transaction->category_name }}</td>
                <td>{{ $transaction->description }}</td>
                <td class="numeric">{{ \App\Support\Currency::format((float) $transaction->amount) }}</td>
                <td>
                    <div class="row-actions">
                        <a class="button button--outline button--small" href="{{ route('events.manage.reports', $transaction->event_id) }}">View</a>
                        @if ($transaction->source === 'expense')
                            @can('manage-finances')
                                <form method="POST" action="{{ route('expenses.destroy', $transaction->target_id) }}" data-confirm="Delete this expense transaction? This cannot be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button--danger button--small" type="submit">Delete</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="table-empty">No posted financial transactions match these filters.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $transactions->links() }}</div>
@endsection
