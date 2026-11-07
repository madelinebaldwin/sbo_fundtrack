<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Event;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
        ]);

        $events = Event::query()
            ->with('academicYear')
            ->withCount(['collections', 'expenses'])
            ->addSelect([
                'total_collected' => DB::table('contributions')
                    ->join('collections', 'collections.id', '=', 'contributions.collection_id')
                    ->selectRaw('COALESCE(SUM(contributions.amount), 0)')
                    ->whereColumn('collections.event_id', 'events.id')
                    ->where('contributions.status', 'posted'),
                'total_expenses' => DB::table('expenses')
                    ->selectRaw('COALESCE(SUM(expenses.amount), 0)')
                    ->whereColumn('expenses.event_id', 'events.id')
                    ->where('expenses.status', 'approved'),
            ])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->latest('event_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        if ($redirect = $this->redirectIfPaginationPageIsUnavailable($events)) {
            return $redirect;
        }

        return view('events.index', compact('events'));
    }

    public function create(): View
    {
        return view('events.create');
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'academic_year' => [
                'sometimes',
                'required',
                'string',
                'max:32',
                'regex:/^[0-9]{4}-[0-9]{4}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || preg_match('/^[0-9]{4}-[0-9]{4}$/', $value) !== 1) {
                        return;
                    }

                    [$startYear, $endYear] = array_map('intval', explode('-', $value));
                    if ($startYear < 2000 || $startYear > 2200 || $endYear !== $startYear + 1) {
                        $fail('Enter consecutive academic years between 2000-2001 and 2200-2201.');
                    }
                },
            ],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'after_or_equal:starts_at'],
        ]);
        $academicYearName = $data['academic_year'] ?? null;
        $academicYearId = $data['academic_year_id'] ?? null;
        unset($data['academic_year'], $data['academic_year_id']);

        $event = DB::transaction(function () use ($data, $request, $activityLogger, $academicYearName, $academicYearId): Event {
            if (is_string($academicYearName)) {
                [$startYear, $endYear] = array_map('intval', explode('-', $academicYearName));
                $academicYear = AcademicYear::query()->firstOrCreate(
                    ['name' => $academicYearName],
                    [
                        'start_year' => $startYear,
                        'end_year' => $endYear,
                        'starts_at' => $startYear.'-07-01',
                        'ends_at' => $endYear.'-06-30',
                        'is_active' => false,
                    ],
                );
            } elseif ($academicYearId !== null) {
                $academicYear = AcademicYear::query()->findOrFail($academicYearId);
            } else {
                $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
            }

            $event = Event::create([
                ...$data,
                'academic_year_id' => $academicYear->id,
                'created_by' => $request->user()->id,
                'event_date' => $data['starts_at'],
                'status' => 'active',
            ]);
            $activityLogger->record('event.created', 'Created event: '.$event->name, $event);

            return $event;
        });

        return redirect()->route('events.manage.overview', $event)->with('status', 'Event created.');
    }

    public function show(Event $event): View
    {
        $event->load(['academicYear', 'creator:id,name']);
        $event->loadCount(['collections', 'expenses']);
        $event->loadSum(['expenses as approved_expenses' => fn ($query) => $query->where('status', 'approved')], 'amount');
        $event->load(['collections' => fn ($query) => $query->withSum(['contributions as collected_amount' => fn ($contributionQuery) => $contributionQuery->where('status', 'posted')], 'amount')]);

        return view('events.show', compact('event'));
    }

    public function edit(Event $event): View
    {
        $event->loadMissing('academicYear');

        return view('events.edit', compact('event'));
    }

    public function update(Request $request, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_if($event->status === 'archived', 409, 'Archived events cannot be edited.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'academic_year' => [
                'required',
                'string',
                'max:32',
                'regex:/^[0-9]{4}-[0-9]{4}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || preg_match('/^[0-9]{4}-[0-9]{4}$/', $value) !== 1) {
                        return;
                    }

                    [$startYear, $endYear] = array_map('intval', explode('-', $value));
                    if ($startYear < 2000 || $startYear > 2200 || $endYear !== $startYear + 1) {
                        $fail('Enter consecutive academic years between 2000-2001 and 2200-2201.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date_format:Y-m-d'],
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ]);

        DB::transaction(function () use ($event, $data, $activityLogger): void {
            [$startYear, $endYear] = array_map('intval', explode('-', $data['academic_year']));
            $academicYear = AcademicYear::query()->firstOrCreate(
                ['name' => $data['academic_year']],
                [
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                    'starts_at' => $startYear.'-07-01',
                    'ends_at' => $endYear.'-06-30',
                    'is_active' => false,
                ],
            );

            $event->update([
                'academic_year_id' => $academicYear->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'event_date' => $data['starts_at'],
            ]);
            $activityLogger->record('event.updated', 'Updated event: '.$event->name, $event);
        });

        return redirect()->route('events.manage.overview', $event)->with('status', 'Event updated.');
    }

    public function destroy(Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        DB::transaction(function () use ($event, $activityLogger): void {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_if($lockedEvent->collections()->exists() || $lockedEvent->expenses()->exists(), 409, 'Events with financial records cannot be deleted.');

            $activityLogger->record('event.deleted', 'Deleted event: '.$lockedEvent->name, $lockedEvent, [
                'academic_year_id' => $lockedEvent->academic_year_id,
            ]);
            $lockedEvent->delete();
        });

        return redirect()->route('events.index')->with('status', 'Event deleted.');
    }
}
