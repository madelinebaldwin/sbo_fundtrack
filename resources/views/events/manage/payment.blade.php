@extends('layouts.app', ['pageTitle' => 'Collections'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'collections'])

    <section class="event-payment-panel panel">
        <h3>Record a payment</h3>
        <div class="event-payment-context">
            <p><span>Year</span><strong>{{ $collection->year_section }}</strong></p>
            <p><span>Students</span><strong>{{ number_format($collection->student_count) }}</strong></p>
            <p><span>Remaining</span><strong>{{ \App\Support\Currency::format($remaining) }}</strong></p>
        </div>
        @if ($collection->status !== 'active')
            <p class="notice">This collection is closed and no longer accepts payments.</p>
        @elseif ($remaining <= 0)
            <p class="notice">This collection is fully paid.</p>
        @else
            <form method="POST" action="{{ route('events.manage.collections.payment.store', [$event, $collection]) }}">
                @csrf
                <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
                <div class="form-grid">
                    <label class="form-field">Amount (PHP)<input type="number" name="amount" min="0.01" max="{{ number_format($remaining, 2, '.', '') }}" step="0.01" value="{{ old('amount') }}" required>@error('amount')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field">Paid date<input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required>@error('paid_at')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field">Payment method<select name="payment_method" required>@foreach (['cash' => 'Cash', 'bank transfer' => 'Bank transfer', 'e-wallet' => 'E-wallet', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="form-field">Reference number<input name="reference_number" value="{{ old('reference_number') }}" maxlength="100"></label>
                    <label class="form-field form-field--full">Notes<textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
                </div>
                <div class="form-actions"><a class="button button--outline" href="{{ route('events.manage.collections', $event) }}">Cancel</a><button class="button button--primary" type="submit">Save Payment</button></div>
            </form>
        @endif
    </section>
@endsection
