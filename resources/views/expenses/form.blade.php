<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-expense-calculator>
    @csrf
    @if ($method !== 'POST') @method($method) @else <input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}"> @endif
    <div class="form-grid">
        @if ($managementEvent)
            <input type="hidden" name="event_id" value="{{ $managementEvent->id }}">
            <p class="form-field"><strong>Event:</strong> {{ $managementEvent->name }}</p>
        @else
            <label class="form-field">Event<select name="event_id" required><option value="">Choose event</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) old('event_id', $expense->event_id ?? '') === (string) $event->id)>{{ $event->name }}</option>@endforeach</select>@error('event_id')<span class="form-error">{{ $message }}</span>@enderror</label>
        @endif
        <label class="form-field">Category<input name="category" value="{{ old('category', isset($expense) ? $expense->category->name : '') }}" maxlength="80" required>@error('category')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field form-field--full">Description<input name="description" maxlength="200" value="{{ old('description', $expense->description ?? '') }}" required>@error('description')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field">Quantity<input type="number" name="quantity" min="0.001" step="0.001" value="{{ old('quantity', $expense->quantity ?? '1') }}" required>@error('quantity')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field">Unit<input type="text" name="unit" maxlength="50" placeholder="e.g. pcs, pack, kg" value="{{ old('unit', $expense->unit ?? 'Item') }}" required>@error('unit')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field">Unit price (PHP)<input type="number" name="unit_price" min="0.01" step="0.01" placeholder="Leave blank if unavailable" value="{{ old('unit_price', $expense->unit_price ?? '') }}" aria-describedby="expense-unit-price-help">@error('unit_price')<span class="form-error">{{ $message }}</span>@enderror<span id="expense-unit-price-help" class="form-help">Optional. Enter a unit price to calculate the amount from quantity.</span></label>
        <label class="form-field">Amount (PHP)<input class="calculated-amount" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount', $expense->amount ?? '') }}" required aria-describedby="expense-amount-help"><span id="expense-amount-help" class="form-help">Enter an amount when unit price is unavailable; it calculates automatically when unit price is provided.</span>@error('amount')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field">Transaction date<input class="date-input" type="date" name="transaction_date" value="{{ old('transaction_date', isset($expense) ? $expense->transaction_date->toDateString() : now()->toDateString()) }}" required>@error('transaction_date')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field form-field--full">Receipt or supporting document<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf">@if(isset($expense) && $expense->receipt_path)<span class="form-help">A receipt is already attached. Upload a new file to replace it.</span>@endif<span class="form-help">PDF, JPG, JPEG, or PNG up to 5 MB. Stored privately.</span>@error('receipt')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field form-field--full">Notes<textarea name="notes" maxlength="5000">{{ old('notes', $expense->notes ?? '') }}</textarea></label>
    </div>
    <p class="form-help">New expenses are approved automatically and immediately included in the event’s financial totals and reports.</p>
    <div class="form-actions"><a class="button button--outline" href="{{ $managementEvent ? route('events.manage.expenses', $managementEvent) : (isset($expense) ? route('expenses.show', $expense) : route('expenses.index')) }}">Cancel</a><button class="button button--primary" type="submit">Save Expense</button></div>
</form>
