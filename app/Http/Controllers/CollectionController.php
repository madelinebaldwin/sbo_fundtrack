<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Collection;
use App\Models\Event;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
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

        abort_unless(Event::query()->whereKey($data['event_id'])->where('status', 'active')->exists(), 422, 'Select an active event.');

        $collection = DB::transaction(function () use ($data, $request, $activityLogger): Collection {
            $collection = Collection::create([
                ...$data,
                'created_by' => $request->user()->id,
                'status' => 'active',
            ]);
            $activityLogger->record('collection.created', 'Created collection target for '.$collection->year_section, $collection);

            return $collection;
        });

        return redirect()->route('collections.show', $collection)->with('status', 'Collection target created. Contributions must be recorded separately.');
    }

    public function show(Collection $collection): View
    {
        $collection->load('event.academicYear');
        $contributions = $collection->contributions()->with('recorder:id,name')->latest('paid_at')->paginate(20);
        $collected = $collection->contributions()->where('status', 'posted')->sum('amount');
        $studentsRecorded = $collection->contributions()->where('status', 'posted')->count();
        $expected = (float) $collection->amount_per_student * $collection->student_count;

        return view('collections.show', [
            'collection' => $collection,
            'contributions' => $contributions,
            'collected' => $collected,
            'expected' => $expected,
            'studentsRecorded' => $studentsRecorded,
            'submissionKey' => (string) Str::uuid(),
        ]);
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
}
