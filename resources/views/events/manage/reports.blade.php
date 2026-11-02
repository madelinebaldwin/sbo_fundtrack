@extends('layouts.app', ['pageTitle' => 'Reports'])

@section('content')
    @include('events.manage._header', ['activeTab' => 'reports'])

    @php($groupedExpenses = $expenses->groupBy(fn ($expense) => $expense->category->name)->sortKeys(SORT_NATURAL | SORT_FLAG_CASE))
    <article class="event-report-paper">
        <header class="event-report-masthead">
            <div class="event-report-institution">
                <strong>ISABELA STATE UNIVERSITY</strong>
                <span>CAUAYAN CAMPUS</span>
                <small>San Fermin, Cauayan City, Isabela</small>
            </div>
            <span class="event-report-republic">BAGONG PILIPINAS</span>
        </header>
        <div class="event-report-title">
            <h2>FINANCIAL REPORT</h2>
            <h3>{{ $event->name }}</h3>
            <p>{{ $event->academicYear->name }} | Generated {{ now()->format('F j, Y') }}</p>
        </div>

        <section class="event-report-section">
            <h3>I. TOTAL BUDGET</h3>
            <div class="table-wrap"><table class="data-table event-report-summary-table"><tbody><tr><th>TOTAL BUDGET</th><td class="numeric">{{ \App\Support\Currency::format($totalBudget) }}</td></tr></tbody></table></div>
        </section>

        <section class="event-report-section">
            <h3>II. DISBURSEMENT</h3>
            <div class="table-wrap"><table class="data-table event-report-table"><thead><tr><th>Quantity</th><th>Unit</th><th>Article and Description</th><th class="numeric">Unit Price</th><th class="numeric">Amount</th></tr></thead><tbody>
                @forelse ($groupedExpenses as $categoryName => $categoryExpenses)
                    <tr class="event-report-category"><th colspan="5">{{ strtoupper($categoryName) }}</th></tr>
                    @foreach ($categoryExpenses as $expense)
                        <tr><td>{{ $expense->formattedQuantity() }}</td><td>{{ $expense->unit }}</td><td>{{ $expense->description }}</td><td class="numeric">{{ (float) $expense->unit_price > 0 ? \App\Support\Currency::format((float) $expense->unit_price) : '—' }}</td><td class="numeric">{{ \App\Support\Currency::format((float) $expense->amount) }}</td></tr>
                    @endforeach
                @empty
                    <tr><td colspan="5" class="table-empty">No approved disbursements for this event.</td></tr>
                @endforelse
                <tr class="event-report-total"><th colspan="4">GRAND TOTAL</th><th class="numeric">{{ \App\Support\Currency::format((float) $totals['expenses']) }}</th></tr>
            </tbody></table></div>
        </section>

        <section class="event-report-section">
            <h3>III. OVERALL EXPENSES</h3>
            <div class="table-wrap"><table class="data-table event-report-summary-table"><tbody>
                <tr><th>OVERALL FUNDS</th><td class="numeric">{{ \App\Support\Currency::format((float) $totals['contributions']) }}</td></tr>
                <tr><th>TOTAL EXPENSES</th><td class="numeric">{{ \App\Support\Currency::format((float) $totals['expenses']) }}</td></tr>
                <tr><th>REMAINING BALANCE</th><td class="numeric">{{ \App\Support\Currency::format((float) $totals['balance']) }}</td></tr>
                <tr><th>OVEREXPENDITURE / DEFICIT</th><td class="numeric">{{ \App\Support\Currency::format($overExpenditure) }}</td></tr>
            </tbody></table></div>
        </section>
        <section class="event-report-signatures">
            <h3>Signatures</h3>
            <div class="event-report-signature-grid">
                @foreach ($signatures as $signature)
                    <div class="event-report-signature-block">
                        <span>{{ $signature['title'] }}</span>
                        <i aria-hidden="true"></i>
                        <strong>{{ $signature['name'] ?: 'Name' }}</strong>
                        <small>{{ $signature['position'] ?: 'Position' }}</small>
                    </div>
                @endforeach
            </div>
        </section>
    </article>

    <section class="event-signature-section">
        <div class="event-section-heading"><div><h3>Report Signatures</h3><p class="form-help">Enter the names and positions to print in the approval section.</p></div><button class="button button--primary" type="button" data-signature-add>+ Add Signature</button></div>
        <form method="POST" action="{{ route('events.manage.reports.signatures', $event) }}" data-signature-form>
            @csrf
            <div class="event-signature-list" data-signature-list>
                @foreach ($signatures as $index => $signature)
                    <div class="event-signature-row" data-signature-row>
                        <label class="form-field">Approval role<input name="signatures[{{ $index }}][title]" value="{{ $signature['title'] }}" maxlength="100"></label>
                        <label class="form-field">Name<input name="signatures[{{ $index }}][name]" value="{{ $signature['name'] }}" maxlength="120"></label>
                        <label class="form-field">Position<input name="signatures[{{ $index }}][position]" value="{{ $signature['position'] }}" maxlength="100"></label>
                        <button class="button button--outline button--small" type="button" data-signature-remove aria-label="Remove signature row">Remove</button>
                    </div>
                @endforeach
            </div>
            <div class="event-report-exports"><button class="button button--primary" type="submit">Save Signatures</button><button class="button button--primary" type="submit" formaction="{{ route('events.manage.reports.docx', $event) }}">WORD DOCS</button><button class="button button--primary" type="submit" formaction="{{ route('events.manage.reports.pdf', $event) }}">PDF</button></div>
        </form>
    </section>
@endsection
