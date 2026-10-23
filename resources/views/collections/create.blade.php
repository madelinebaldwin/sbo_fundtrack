@extends('layouts.app', ['pageTitle' => 'Add Collection'])

@section('content')
    <section class="form-panel form-panel--narrow"><h2>New collection target</h2>
        @if ($events->isEmpty())
            <p>No active events are available. Create or reactivate an event first.</p><a class="button button--primary" href="{{ route('events.create') }}">Add Event</a>
        @else
            <form method="POST" action="{{ route('collections.store') }}">@csrf
                <div class="form-grid">
                    <label class="form-field form-field--full">Event<select name="event_id" required><option value="">Choose an event</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) old('event_id', $selectedEventId) === (string) $event->id)>{{ $event->name }} · {{ $event->academicYear->name }}</option>@endforeach</select>@error('event_id')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field">Year level<input name="year_level" value="{{ old('year_level') }}" maxlength="55" placeholder="e.g. 1st year" required>@error('year_level')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field">Section<input name="section" value="{{ old('section') }}" maxlength="20" placeholder="e.g. A" required>@error('section')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field">Student count<input type="number" name="student_count" min="1" step="1" value="{{ old('student_count') }}" required>@error('student_count')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field">Contribution per student (PHP)<input type="number" name="amount_per_student" min="0.01" step="0.01" value="{{ old('amount_per_student') }}" required>@error('amount_per_student')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field form-field--full">Notes<textarea name="notes" maxlength="5000">{{ old('notes') }}</textarea></label>
                </div>
                <p class="form-help">Expected amount is the per-student contribution multiplied by the student count. Creating a target does not count as received money.</p>
                <div class="form-actions"><a class="button button--outline" href="{{ route('collections.index') }}">Cancel</a><button class="button button--primary" type="submit">Save Collection</button></div>
            </form>
        @endif
    </section>
@endsection
