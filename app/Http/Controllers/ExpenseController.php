<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'voided'])],
        ]);

        $expenses = Expense::query()
            ->with(['event:id,name', 'category:id,name', 'creator:id,name', 'reviewer:id,name'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($searchQuery) => $searchQuery
                    ->where('description', 'like', $search)
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $search)));
            })
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->integer('event_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('transaction_date')
            ->paginate(15)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'events' => Event::query()->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', [
            'events' => Event::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate($this->rules(true));
        abort_unless(Event::query()->whereKey($data['event_id'])->where('status', 'active')->exists(), 422, 'Select an active event.');
        $receipt = $request->file('receipt')?->store('receipts');
        if ($request->hasFile('receipt') && ! is_string($receipt)) {
            return back()->withErrors(['receipt' => 'The receipt could not be stored. Please try again.'])->withInput();
        }
        unset($data['receipt']);
        $expense = DB::transaction(function () use ($data, $request, $receipt, $activityLogger): Expense {
            $expense = Expense::create([
                ...$data,
                'created_by' => $request->user()->id,
                'receipt_path' => $receipt,
                'status' => 'pending',
            ]);
            $activityLogger->record('expense.created', 'Submitted expense: '.$expense->description, $expense, [
                'amount' => $expense->amount,
                'status' => $expense->status,
            ]);

            return $expense;
        });

        return redirect()->route('expenses.show', $expense)->with('status', 'Expense submitted for review. It is not included in totals until approved.');
    }

    public function show(Expense $expense): View
    {
        $expense->load(['event.academicYear', 'category', 'creator:id,name', 'reviewer:id,name']);

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense): View
    {
        abort_unless($expense->status === 'pending', 409, 'Only pending expenses can be edited.');

        return view('expenses.edit', [
            'expense' => $expense,
            'events' => Event::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate($this->rules(false));
        $newReceipt = $request->file('receipt')?->store('receipts');
        if ($request->hasFile('receipt') && ! is_string($newReceipt)) {
            return back()->withErrors(['receipt' => 'The receipt could not be stored. Please try again.'])->withInput();
        }
        unset($data['receipt']);
        $oldReceipt = $expense->receipt_path;

        DB::transaction(function () use ($data, $expense, $newReceipt, $activityLogger): void {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            abort_unless($lockedExpense->status === 'pending', 409, 'Only pending expenses can be edited.');
            $lockedExpense->update([
                ...$data,
                ...($newReceipt ? ['receipt_path' => $newReceipt] : []),
            ]);
            $activityLogger->record('expense.updated', 'Updated pending expense: '.$lockedExpense->description, $lockedExpense);
        });

        if ($newReceipt && $oldReceipt) {
            Storage::disk()->delete($oldReceipt);
        }

        return redirect()->route('expenses.show', $expense)->with('status', 'Expense updated.');
    }

    public function review(Request $request, Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'voided'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $expense, $request, $activityLogger): void {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $allowedTransition = ($lockedExpense->status === 'pending' && in_array($data['status'], ['approved', 'rejected'], true))
                || ($lockedExpense->status === 'approved' && $data['status'] === 'voided');
            abort_unless($allowedTransition, 409, 'This expense status cannot be changed that way.');
            abort_if($data['status'] === 'approved' && $lockedExpense->created_by === $request->user()->id, 403, 'You cannot approve an expense you submitted.');

            $previousStatus = $lockedExpense->status;
            $lockedExpense->update([
                'status' => $data['status'],
                'reviewed_by' => $request->user()->id,
                'notes' => $data['review_note'] ?? $lockedExpense->notes,
            ]);
            $activityLogger->record('expense.status_changed', 'Changed expense status from '.$previousStatus.' to '.$lockedExpense->status, $lockedExpense, [
                'previous_status' => $previousStatus,
                'status' => $lockedExpense->status,
                'review_note' => $data['review_note'] ?? null,
            ]);
        });

        return back()->with('status', 'Expense status updated.');
    }

    /** @return array<string, array<int, string|Rule>> */
    private function rules(bool $requireSubmissionKey): array
    {
        return [
            'submission_key' => $requireSubmissionKey
                ? ['required', 'uuid', Rule::unique('expenses', 'submission_key')]
                : ['prohibited'],
            'event_id' => ['required', 'integer', Rule::exists('events', 'id')->where('status', 'active')],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
