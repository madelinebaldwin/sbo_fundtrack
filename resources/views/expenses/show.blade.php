@extends('layouts.app', ['pageTitle' => 'Expense Details'])

@section('content')
    <div class="page-toolbar"><div><h2 class="page-title">{{ $expense->description }}</h2></div><div class="row-actions"><a class="button button--outline" href="{{ route('expenses.index') }}">Back to Expenses</a>@can('manage-finances')@if($expense->status === 'pending')<a class="button button--primary" href="{{ route('expenses.edit', $expense) }}">Edit</a>@endif @endcan</div></div>
    <section class="detail-grid">
        <article class="detail-card"><small>Event</small><strong>{{ $expense->event->name }}</strong></article>
        <article class="detail-card"><small>Category</small><strong>{{ $expense->category->name }}</strong></article>
        <article class="detail-card"><small>Amount</small><strong>{{ \App\Support\Currency::format((float) $expense->amount) }}</strong></article>
        <article class="detail-card"><small>Transaction date</small><strong>{{ $expense->transaction_date->format('M j, Y') }}</strong></article>
        <article class="detail-card"><small>Submitted by</small><strong>{{ $expense->creator?->name ?? 'System' }}</strong></article>
        <article class="detail-card"><small>Reviewed by</small><strong>{{ $expense->reviewer?->name ?? 'Not reviewed' }}</strong></article>
    </section>
    <section class="panel section-gap"><div class="panel-heading"><h2>Supporting details</h2></div><p>{{ $expense->notes ?: 'No notes provided.' }}</p>@if($expense->receipt_path)<a class="button button--outline button--small" href="{{ route('expenses.receipt', $expense) }}">Download receipt</a>@endif</section>

    @can('review-expenses')
        @if ($expense->status === 'pending' || $expense->status === 'approved')
            <section class="form-panel form-panel--narrow section-gap"><h2>{{ $expense->status === 'pending' ? 'Review expense' : 'Void approved expense' }}</h2>
                <form method="POST" action="{{ route('expenses.review', $expense) }}">@csrf @method('PATCH')
                    <div class="form-grid">
                        @if ($expense->status === 'pending')
                            @if ($expense->created_by !== auth()->id())<label class="form-field">Decision<select name="status"><option value="approved">Approve</option><option value="rejected">Reject</option></select></label>@else<p class="form-help form-field--full">You cannot approve an expense you submitted. Another administrator or treasurer must review it.</p>@endif
                        @else
                            <input type="hidden" name="status" value="voided"><p class="form-help form-field--full">Voiding removes this expense from totals while preserving the record.</p>
                        @endif
                        <label class="form-field form-field--full">Review note<textarea name="review_note" maxlength="1000">{{ old('review_note') }}</textarea></label>
                    </div>
                    @if ($expense->status === 'approved' || $expense->created_by !== auth()->id())<div class="form-actions"><button class="button {{ $expense->status === 'approved' ? 'button--danger' : 'button--primary' }}" type="submit" data-confirm="Save this expense review decision?">{{ $expense->status === 'approved' ? 'Void Expense' : 'Save Review' }}</button></div>@endif
                </form>
            </section>
        @endif
    @endcan
@endsection
