<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Event;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
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
            ->paginate(15)
            ->withQueryString();

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
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date_format:Y-m-d'],
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ]);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $event = DB::transaction(function () use ($data, $request, $activityLogger, $academicYear): Event {
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
        return view('events.edit', [
            'event' => $event,
            'academicYears' => AcademicYear::query()->orderByDesc('start_year')->get(),
        ]);
    }

    public function update(Request $request, Event $event, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_if($event->status === 'archived', 409, 'Archived events cannot be edited.');
        $data = $request->validate($this->rules());
        DB::transaction(function () use ($event, $data, $activityLogger): void {
            $event->update($data);
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

    /** @return array<string, array<int, string|Rule>> */
    private function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:160'],
            'contribution_target' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }
}
