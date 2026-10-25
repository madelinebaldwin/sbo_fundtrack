@extends('layouts.app', ['pageTitle' => 'Expenses'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'expenses'])

    <div class="event-table-heading"><div><h3>Event Expenses</h3><p class="form-help">Only approved expenses are included in financial totals.</p></div>@can('manage-finances')@if($event->status === 'active')<a class="button button--primary" href="{{ route('events.manage.expenses.create', $event) }}">+ Add Expense</a>@endif @endcan</div>

    <div class="table-wrap event-management-table"><table class="data-table">
        <thead><tr><th>Event</th><th>Category</th><th>Description</th><th class="numeric">Amount</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr id="expense-{{ $expense->id }}">
                    <td>{{ $event->name }}</td><td>{{ $expense->category->name }}</td><td>{{ $expense->description }}</td>
                    <td class="numeric">{{ \App\Support\Currency::format((float) $expense->amount) }}</td><td>{{ $expense->transaction_date->format('M j, Y') }}</td>
                    <td>
                        <div class="event-expense-actions">
                            @can('manage-finances')
                                @if ($expense->status === 'pending' && $event->status === 'active')
                                    <a class="button button--outline button--small" href="{{ route('events.manage.expenses.edit', [$event, $expense]) }}">Edit</a>
                                @endif
                                <form method="POST" action="{{ route('events.manage.expenses.destroy', [$event, $expense]) }}" data-confirm="Delete this expense? This cannot be undone.">@csrf @method('DELETE')<button class="button button--danger button--small" type="submit">Delete</button></form>
                            @endcan
                            @can('review-expenses')
                                @if ($expense->status === 'pending' && $expense->created_by !== auth()->id())
                                    <form class="event-expense-review" method="POST" action="{{ route('events.manage.expenses.review', [$event, $expense]) }}">@csrf @method('PATCH')<select name="status" aria-label="Review decision for {{ $expense->description }}"><option value="approved">Approve</option><option value="rejected">Reject</option></select><button class="button button--primary button--small" type="submit">Save</button></form>
                                @elseif ($expense->status === 'approved')
                                    <form class="event-expense-review" method="POST" action="{{ route('events.manage.expenses.review', [$event, $expense]) }}" data-confirm="Void this approved expense?">@csrf @method('PATCH')<input type="hidden" name="status" value="voided"><button class="button button--muted button--small" type="submit">Void</button></form>
                                @endif
                            @endcan
                            @if ($expense->status === 'rejected' || $expense->status === 'voided')<span class="form-help">No action</span>@endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No expenses have been recorded for this event.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $expenses->links() }}</div>
@endsection
