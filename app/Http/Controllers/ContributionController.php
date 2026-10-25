<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Contribution;
use App\Models\Event;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContributionController extends Controller
{
    public function store(Request $request, Collection $collection, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->persist($request, $collection, $activityLogger);
    }

    public function storeForEvent(Request $request, Event $event, Collection $collection, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($event->collections()->whereKey($collection->id)->exists(), 404);

        return $this->persist($request, $collection, $activityLogger, $event);
    }

    private function persist(Request $request, Collection $collection, ActivityLogger $activityLogger, ?Event $managementEvent = null): RedirectResponse
    {
        abort_unless($collection->status === 'active' && $collection->event()->where('status', 'active')->exists(), 409, 'This collection is not accepting contributions.');

        $data = $request->validate([
            'submission_key' => ['required', 'uuid', Rule::unique('contributions', 'submission_key')],
            'student_reference' => [$managementEvent === null ? 'required' : 'nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(['cash', 'bank transfer', 'e-wallet', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $contribution = DB::transaction(function () use ($data, $collection, $request, $activityLogger): Contribution {
            $lockedCollection = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            abort_unless($lockedCollection->status === 'active', 409, 'This collection is closed.');
            $expected = $lockedCollection->student_count * (float) $lockedCollection->amount_per_student;
            $posted = (float) $lockedCollection->contributions()->where('status', 'posted')->sum('amount');
            if ($posted + (float) $data['amount'] > $expected) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment exceeds the remaining amount for this collection.',
                ]);
            }

            $contribution = $lockedCollection->contributions()->create([
                ...$data,
                'student_reference' => ($data['student_reference'] ?? null) ?: 'Group payment: '.$lockedCollection->year_section,
                'recorded_by' => $request->user()->id,
                'status' => 'posted',
            ]);

            $activityLogger->record('contribution.recorded', 'Recorded a student contribution.', $contribution, [
                'amount' => $contribution->amount,
                'collection_id' => $collection->id,
            ]);

            return $contribution;
        });

        $redirect = redirect()->to(route('events.manage.collections', $managementEvent ?? $collection->event_id).'#collection-'.$collection->id);

        return $redirect->with('status', 'Contribution recorded.');
    }

    public function void(Request $request, Contribution $contribution, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($contribution, $data, $activityLogger): void {
            $lockedContribution = Contribution::query()->lockForUpdate()->findOrFail($contribution->id);
            abort_unless($lockedContribution->status === 'posted', 409, 'Only posted contributions can be voided.');
            $lockedContribution->update(['status' => 'voided']);
            $activityLogger->record('contribution.voided', 'Voided contribution: '.$data['reason'], $lockedContribution, [
                'amount' => $lockedContribution->amount,
            ]);
        });

        return back()->with('status', 'Contribution voided. The audit record was retained.');
    }
}
