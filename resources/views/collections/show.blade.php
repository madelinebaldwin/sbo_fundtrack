@extends('layouts.app', ['pageTitle' => 'Manage Collection'])

@section('content')
    @php($remaining = $expected - (float) $collected)
    @php($contributionColumns = auth()->user()->can('review-expenses') ? 8 : 7)
    <div class="page-toolbar"><div><span class="status-pill status-pill--{{ $collection->status }}">{{ ucfirst($collection->status) }}</span><h2 class="page-title">{{ $collection->year_section }} · {{ $collection->event->name }}</h2></div><div class="row-actions"><a class="button button--outline" href="{{ route('collections.index') }}">Back to Collections</a>@can('review-expenses') @if($collection->status === 'active')<form method="POST" action="{{ route('collections.close', $collection) }}" data-confirm="Close this collection? No new contributions can be recorded afterward.">@csrf @method('PATCH')<button class="button button--muted" type="submit">Close Collection</button></form>@endif @endcan</div></div>
    <section class="detail-grid">
        <article class="detail-card"><small>Academic year</small><strong>{{ $collection->event->academicYear->name }}</strong></article>
        <article class="detail-card"><small>Students recorded / expected</small><strong>{{ number_format($studentsRecorded) }} / {{ number_format($collection->student_count) }}</strong></article>
        <article class="detail-card"><small>Expected</small><strong>{{ \App\Support\Currency::format($expected) }}</strong></article>
        <article class="detail-card"><small>Collected</small><strong>{{ \App\Support\Currency::format((float) $collected) }}</strong></article>
        <article class="detail-card"><small>Remaining</small><strong>@if($remaining < 0) Over by {{ \App\Support\Currency::format(abs($remaining)) }} @else {{ \App\Support\Currency::format($remaining) }} @endif</strong></article>
    </section>

    @can('manage-finances')
        @if ($collection->status === 'active')
            <section class="form-panel form-panel--narrow section-gap"><h2>Record a contribution</h2>
                <form method="POST" action="{{ route('contributions.store', $collection) }}">@csrf
                    <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
                    <div class="form-grid">
                        <label class="form-field form-field--full">Student reference<input name="student_reference" value="{{ old('student_reference') }}" maxlength="80" placeholder="Use an approved student ID/reference" required>@error('student_reference')<span class="form-error">{{ $message }}</span>@enderror</label>
                        <label class="form-field">Amount (PHP)<input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>@error('amount')<span class="form-error">{{ $message }}</span>@enderror</label>
                        <label class="form-field">Date paid<input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required>@error('paid_at')<span class="form-error">{{ $message }}</span>@enderror</label>
                        <label class="form-field">Payment method<select name="payment_method" required>@foreach (['cash' => 'Cash', 'bank transfer' => 'Bank transfer', 'e-wallet' => 'E-wallet', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="form-field">Reference number<input name="reference_number" value="{{ old('reference_number') }}" maxlength="100"></label>
                        <label class="form-field form-field--full">Notes<textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
                    </div>
                    <div class="form-actions"><button class="button button--primary" type="submit">Record Payment</button></div>
                </form>
            </section>
        @else
            <p class="notice">This collection is closed and no longer accepts contributions.</p>
        @endif
    @endcan

    <section class="panel section-gap"><div class="panel-heading"><div><p class="eyebrow">Recorded payments</p><h2>Contribution history</h2></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Student reference</th><th>Method</th><th>Reference</th><th class="numeric">Amount</th><th>Status</th><th>Recorded by</th>@can('review-expenses')<th>Correction</th>@endcan</tr></thead><tbody>
            @forelse ($contributions as $contribution)
                <tr><td>{{ $contribution->paid_at->format('M j, Y') }}</td><td>{{ $contribution->student_reference }}</td><td>{{ ucfirst($contribution->payment_method) }}</td><td>{{ $contribution->reference_number ?: '—' }}</td><td class="numeric">{{ \App\Support\Currency::format((float) $contribution->amount) }}</td><td><span class="status-pill status-pill--{{ $contribution->status }}">{{ ucfirst($contribution->status) }}</span></td><td>{{ $contribution->recorder?->name ?? 'System' }}</td>
                    @can('review-expenses')<td>@if($contribution->status === 'posted')<form class="row-actions" method="POST" action="{{ route('contributions.void', $contribution) }}">@csrf @method('PATCH')<input class="form-control correction-reason" name="reason" placeholder="Correction reason" aria-label="Correction reason" required><button class="button button--danger button--small" type="submit" data-confirm="Void this contribution? It will be retained in the audit trail.">Void</button></form>@else — @endif</td>@endcan
                </tr>
            @empty
                <tr><td colspan="{{ $contributionColumns }}" class="table-empty">No contributions have been recorded for this target.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="pagination">{{ $contributions->links() }}</div>
    </section>
@endsection
