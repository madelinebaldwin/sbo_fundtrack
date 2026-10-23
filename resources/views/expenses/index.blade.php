@extends('layouts.app', ['pageTitle' => 'Expenses'])

@section('content')
    <form class="filter-bar filter-bar--spaced" method="GET" action="{{ route('expenses.index') }}">
        <div class="filter-fields">
            <label class="filter-field filter-field--search">Search<input type="search" name="search" value="{{ request('search') }}" placeholder="Category or Description"></label>
            <label class="filter-field">Event<select name="event_id"><option value="">All events</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->name }}</option>@endforeach</select></label>
            <label class="filter-field">Status<select name="status"><option value="">All statuses</option>@foreach (['pending', 'approved', 'rejected', 'voided'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
            <div class="filter-actions"><button class="button button--primary" type="submit">Filter</button><a class="button button--outline" href="{{ route('expenses.index') }}">Clear</a></div>
        </div>
        @can('manage-finances')<a class="button button--primary" href="{{ route('expenses.create') }}">+ Add Expense</a>@endcan
    </form>

    <div class="table-wrap"><table class="data-table"><thead><tr><th>Event</th><th>Category</th><th>Description</th><th class="numeric">Amount</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse ($expenses as $expense)
            <tr><td>{{ $expense->event->name }}</td><td>{{ $expense->category->name }}</td><td>{{ $expense->description }}</td><td class="numeric">{{ \App\Support\Currency::format((float) $expense->amount) }}</td><td>{{ $expense->transaction_date->format('M j, Y') }}</td><td><span class="status-pill status-pill--{{ $expense->status }}">{{ ucfirst($expense->status) }}</span></td><td><div class="row-actions"><a class="button button--primary button--small" href="{{ route('expenses.show', $expense) }}">Manage</a>@can('manage-finances') @if($expense->status === 'pending')<a class="button button--outline button--small" href="{{ route('expenses.edit', $expense) }}">Edit</a>@endif @endcan</div></td></tr>
        @empty
            <tr><td colspan="7" class="table-empty">No expenses match these filters.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $expenses->links() }}</div>
@endsection
