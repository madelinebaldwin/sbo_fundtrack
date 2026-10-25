@extends('layouts.app', ['pageTitle' => 'Reports'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'reports'])

    <div class="event-report-toolbar"><div><h3>{{ $event->name }} Financial Report</h3><p class="form-help">Generated {{ now()->format('F j, Y') }}</p></div></div>
    <section class="event-report-section">
        <h3>I. TOTAL BUDGET</h3>
        <div class="table-wrap"><table class="data-table"><tbody><tr><th>Contribution goal</th><td class="numeric">{{ \App\Support\Currency::format($budgetGoal) }}</td></tr><tr><th>Collections received</th><td class="numeric">{{ \App\Support\Currency::format((float) $totals['contributions']) }}</td></tr></tbody></table></div>
    </section>

    <section class="event-report-section">
        <h3>II. DISBURSEMENT</h3>
        <div class="table-wrap"><table class="data-table event-report-table"><thead><tr><th>Quantity</th><th>Unit</th><th>Category and Description</th><th class="numeric">Unit Price</th><th class="numeric">Amount</th></tr></thead><tbody>
            @php($groupedExpenses = $expenses->groupBy(fn ($expense) => $expense->category->name))
            @forelse ($groupedExpenses as $categoryName => $categoryExpenses)
                <tr class="event-report-category"><th colspan="5">{{ strtoupper($categoryName) }}</th></tr>
                @foreach ($categoryExpenses as $expense)
                    <tr><td>1</td><td>Item</td><td>{{ $expense->description }}</td><td class="numeric">{{ \App\Support\Currency::format((float) $expense->amount) }}</td><td class="numeric">{{ \App\Support\Currency::format((float) $expense->amount) }}</td></tr>
                @endforeach
            @empty
                <tr><td colspan="5" class="table-empty">No approved disbursements for this event.</td></tr>
            @endforelse
            <tr class="event-report-total"><th colspan="4">GRAND TOTAL</th><th class="numeric">{{ \App\Support\Currency::format((float) $totals['expenses']) }}</th></tr>
        </tbody></table></div>
    </section>

    <section class="event-report-section">
        <h3>III. OVERALL EXPENSES</h3>
        <div class="table-wrap"><table class="data-table"><tbody>
            <tr><th>Overall expenses</th><td class="numeric">{{ \App\Support\Currency::format((float) $totals['expenses']) }}</td></tr>
            <tr><th>Total expenses</th><td class="numeric">{{ \App\Support\Currency::format((float) $totals['expenses']) }}</td></tr>
            <tr><th>Remaining balance</th><td class="numeric">{{ \App\Support\Currency::format((float) $totals['balance']) }}</td></tr>
            <tr><th>Over expenditure / deficit</th><td class="numeric">{{ \App\Support\Currency::format($overExpenditure) }}</td></tr>
        </tbody></table></div>
    </section>

    <section class="event-signature-section">
        <div class="event-section-heading"><div><h3>Signatures</h3><p class="form-help">Add the signatories to include in your exported report.</p></div><button class="button button--primary" type="button" data-signature-add>+ Add Signature</button></div>
        <form method="POST" action="{{ route('events.manage.reports.pdf', $event) }}" data-signature-form>
            @csrf
            <div class="event-signature-list" data-signature-list>
                <div class="event-signature-row" data-signature-row>
                    <label class="form-field">Title<input name="signatures[0][title]" maxlength="100"></label>
                    <label class="form-field">Name<input name="signatures[0][name]" maxlength="120"></label>
                    <label class="form-field">Position<input name="signatures[0][position]" maxlength="100"></label>
                    <button class="button button--outline button--small" type="button" data-signature-remove aria-label="Remove signature row">Remove</button>
                </div>
            </div>
            <div class="event-report-exports"><button class="button button--primary" type="submit" formaction="{{ route('events.manage.reports.docx', $event) }}">WORD DOCS</button><button class="button button--primary" type="submit" formaction="{{ route('events.manage.reports.pdf', $event) }}">PDF</button></div>
        </form>
    </section>
@endsection
