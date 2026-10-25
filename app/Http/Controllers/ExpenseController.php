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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
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
            'managementEvent' => null,
        ]);
    }

    public function createForEvent(Event $event): View
    {
        abort_unless($event->status === 'active', 404);

        return view('expenses.create', [
            'events' => Event::query()->whereKey($event->id)->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'managementEvent' => $event,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->persist($request, $activityLogger);
    }

    public function storeForEvent(Request $request, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($event->status === 'active', 404);
        $request->merge(['event_id' => $event->id]);

        return $this->persist($request, $activityLogger, $event);
    }

    private function persist(Request $request, ActivityLogger $activityLogger, ?Event $managementEvent = null): RedirectResponse
    {
        $request->merge(['category' => trim((string) $request->input('category'))]);
        $data = $request->validate($this->rules(true));
        abort_unless(Event::query()->whereKey($data['event_id'])->where('status', 'active')->exists(), 422, 'Select an active event.');
        $receipt = $request->file('receipt')?->store('receipts');
        if ($request->hasFile('receipt') && ! is_string($receipt)) {
            return back()->withErrors(['receipt' => 'The receipt could not be stored. Please try again.'])->withInput();
        }
        unset($data['receipt']);
        $expense = DB::transaction(function () use ($data, $request, $receipt, $activityLogger): Expense {
            $category = $this->resolveCategory($data['category']);
            unset($data['category']);

            $expense = Expense::create([
                ...$data,
                'expense_category_id' => $category->id,
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

        $redirect = $managementEvent
            ? redirect()->route('events.manage.expenses', $managementEvent)
            : redirect()->route('expenses.index');

        return $redirect->with('status', 'Expense submitted for review. It is not included in totals until approved.');
    }

    public function show(Expense $expense): RedirectResponse
    {
        return redirect()->to(route('events.manage.expenses', $expense->event_id).'#expense-'.$expense->id);
    }

    public function edit(Expense $expense): View
    {
        abort_unless($expense->status === 'pending', 409, 'Only pending expenses can be edited.');

        return view('expenses.edit', [
            'expense' => $expense,
            'events' => Event::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'managementEvent' => null,
        ]);
    }

    public function editForEvent(Event $event, Expense $expense): View
    {
        abort_unless($event->expenses()->whereKey($expense->id)->exists(), 404);
        abort_unless($event->status === 'active' && $expense->status === 'pending', 409, 'Only pending expenses for active events can be edited.');

        return view('expenses.edit', [
            'expense' => $expense,
            'events' => Event::query()->whereKey($event->id)->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'managementEvent' => $event,
        ]);
    }

    public function update(Request $request, Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->updateExpense($request, $expense, $activityLogger);
    }

    public function updateForEvent(Request $request, Event $event, Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($event->expenses()->whereKey($expense->id)->exists(), 404);

        return $this->updateExpense($request, $expense, $activityLogger, $event);
    }

    public function destroy(Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->deleteExpense($expense, $activityLogger);

        return back()->with('status', 'Expense deleted.');
    }

    public function destroyForEvent(Event $event, Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($event->expenses()->whereKey($expense->id)->exists(), 404);
        $this->deleteExpense($expense, $activityLogger);

        return redirect()->route('events.manage.expenses', $event)->with('status', 'Expense deleted.');
    }

    private function deleteExpense(Expense $expense, ActivityLogger $activityLogger): void
    {
        $receiptPath = DB::transaction(function () use ($expense, $activityLogger): ?string {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            $activityLogger->record('expense.deleted', 'Deleted expense: '.$lockedExpense->description, $lockedExpense, [
                'amount' => $lockedExpense->amount,
                'status' => $lockedExpense->status,
                'event_id' => $lockedExpense->event_id,
            ]);
            $receiptPath = $lockedExpense->receipt_path;
            $lockedExpense->delete();

            return $receiptPath;
        });

        if ($receiptPath !== null) {
            Storage::disk()->delete($receiptPath);
        }
    }

    private function updateExpense(Request $request, Expense $expense, ActivityLogger $activityLogger, ?Event $managementEvent = null): RedirectResponse
    {
        $request->merge(['category' => trim((string) $request->input('category'))]);
        if ($managementEvent !== null) {
            $request->merge(['event_id' => $managementEvent->id]);
        }
        $data = $request->validate($this->rules(false));
        $newReceipt = $request->file('receipt')?->store('receipts');
        if ($request->hasFile('receipt') && ! is_string($newReceipt)) {
            return back()->withErrors(['receipt' => 'The receipt could not be stored. Please try again.'])->withInput();
        }
        unset($data['receipt']);
        $oldReceipt = $expense->receipt_path;

        DB::transaction(function () use ($data, $expense, $newReceipt, $activityLogger): void {
            $category = $this->resolveCategory($data['category']);
            unset($data['category']);
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            abort_unless($lockedExpense->status === 'pending', 409, 'Only pending expenses can be edited.');
            $lockedExpense->update([
                ...$data,
                'expense_category_id' => $category->id,
                ...($newReceipt ? ['receipt_path' => $newReceipt] : []),
            ]);
            $activityLogger->record('expense.updated', 'Updated pending expense: '.$lockedExpense->description, $lockedExpense);
        });

        if ($newReceipt && $oldReceipt) {
            Storage::disk()->delete($oldReceipt);
        }

        $redirect = $managementEvent
            ? redirect()->route('events.manage.expenses', $managementEvent)
            : redirect()->route('expenses.show', $expense);

        return $redirect->with('status', 'Expense updated.');
    }

    public function review(Request $request, Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->reviewExpense($request, $expense, $activityLogger);
    }

    public function reviewForEvent(Request $request, Event $event, Expense $expense, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($event->expenses()->whereKey($expense->id)->exists(), 404);

        return $this->reviewExpense($request, $expense, $activityLogger, $event);
    }

    private function reviewExpense(Request $request, Expense $expense, ActivityLogger $activityLogger, ?Event $managementEvent = null): RedirectResponse
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

        $redirect = $managementEvent
            ? redirect()->route('events.manage.expenses', $managementEvent)
            : back();

        return $redirect->with('status', 'Expense status updated.');
    }

    private function resolveCategory(string $name): ExpenseCategory
    {
        $category = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($category !== null) {
            if (! $category->is_active) {
                throw ValidationException::withMessages([
                    'category' => 'This expense category is inactive. Choose another category.',
                ]);
            }

            return $category;
        }

        return ExpenseCategory::create(['name' => $name, 'is_active' => true]);
    }

    /** @return array<string, array<int, string|Rule>> */
    private function rules(bool $requireSubmissionKey): array
    {
        return [
            'submission_key' => $requireSubmissionKey
                ? ['required', 'uuid', Rule::unique('expenses', 'submission_key')]
                : ['prohibited'],
            'event_id' => ['required', 'integer', Rule::exists('events', 'id')->where('status', 'active')],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
