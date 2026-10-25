<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Collection;
use App\Models\Event;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        $collections = Collection::query()
            ->with('event.academicYear')
            ->withCount(['contributions as students_recorded_count' => fn ($query) => $query->where('status', 'posted')])
            ->withCount('contributions')
            ->withSum(['contributions as collected_amount' => fn ($query) => $query->where('status', 'posted')], 'amount')
            ->when($request->filled('search'), fn ($query) => $query->where('year_section', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('academic_year_id'), fn ($query) => $query->whereHas('event', fn ($eventQuery) => $eventQuery->where('academic_year_id', $request->integer('academic_year_id'))))
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->integer('event_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('collections.index', [
            'collections' => $collections,
            'academicYears' => AcademicYear::query()->orderByDesc('start_year')->get(),
            'events' => Event::query()->where('status', '!=', 'archived')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('collections.create', [
            'events' => Event::query()->where('status', 'active')->with('academicYear')->orderBy('name')->get(),
            'selectedEventId' => $request->integer('event_id') ?: null,
            'managementEvent' => null,
        ]);
    }

    public function createForEvent(Event $event): View
    {
        abort_unless($event->status === 'active', 404);

        return view('collections.create', [
            'events' => Event::query()->whereKey($event->id)->with('academicYear')->get(),
            'selectedEventId' => $event->id,
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
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'year_level' => ['required', 'string', 'max:55'],
            'section' => ['required', 'string', 'max:20'],
            'student_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'amount_per_student' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['year_section'] = trim($data['year_level']).' - '.trim($data['section']);
        unset($data['year_level'], $data['section']);

        $collection = DB::transaction(function () use ($data, $request, $activityLogger): Collection {
            $event = Event::query()->lockForUpdate()->findOrFail($data['event_id']);
            abort_unless($event->status === 'active', 422, 'Select an active event.');

            $duplicateClassExists = $event->collections()
                ->whereRaw('LOWER(year_section) = ?', [strtolower($data['year_section'])])
                ->exists();

            if ($duplicateClassExists) {
                throw ValidationException::withMessages([
                    'section' => 'A collection for this year level and section already exists for this event.',
                ]);
            }

            $collection = Collection::create([
                ...$data,
                'created_by' => $request->user()->id,
                'status' => 'active',
            ]);
            $activityLogger->record('collection.created', 'Created collection target for '.$collection->year_section, $collection);

            return $collection;
        });

        $redirect = $managementEvent
            ? redirect()->route('events.manage.collections', $managementEvent)
            : redirect()->route('collections.index');

        return $redirect->with('status', 'Collection target created. Contributions must be recorded separately.');
    }

    public function show(Collection $collection): RedirectResponse
    {
        if (auth()->user()->can('manage-finances')) {
            return redirect()->route('events.manage.collections.payment', [$collection->event_id, $collection]);
        }

        return redirect()->to(route('events.manage.collections', $collection->event_id).'#collection-'.$collection->id);
    }

    public function close(Collection $collection, ActivityLogger $activityLogger): RedirectResponse
    {
        DB::transaction(function () use ($collection, $activityLogger): void {
            $lockedCollection = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            abort_unless($lockedCollection->status === 'active', 409, 'This collection is already closed.');
            $lockedCollection->update(['status' => 'closed']);
            $activityLogger->record('collection.closed', 'Closed collection for '.$lockedCollection->year_section, $lockedCollection);
        });

        return back()->with('status', 'Collection closed. Existing contributions remain in the audit trail.');
    }

    public function destroy(Collection $collection, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->deleteEmptyCollection($collection, $activityLogger);

        return redirect()->route('collections.index')->with('status', 'Collection deleted.');
    }

    public function destroyForEvent(Event $event, Collection $collection, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($event->collections()->whereKey($collection->id)->exists(), 404);
        $this->deleteEmptyCollection($collection, $activityLogger);

        return redirect()->route('events.manage.collections', $event)->with('status', 'Collection deleted.');
    }

    private function deleteEmptyCollection(Collection $collection, ActivityLogger $activityLogger): void
    {
        DB::transaction(function () use ($collection, $activityLogger): void {
            $lockedCollection = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            abort_if($lockedCollection->contributions()->exists(), 409, 'Collections with recorded contributions cannot be deleted.');

            $activityLogger->record('collection.deleted', 'Deleted collection target for '.$lockedCollection->year_section, $lockedCollection, [
                'event_id' => $lockedCollection->event_id,
            ]);
            $lockedCollection->delete();
        });
    }
}
