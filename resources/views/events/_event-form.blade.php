<section class="event-create-panel" aria-labelledby="event-create-title">
    <h2 id="event-create-title">{{ isset($event) ? 'Edit event' : 'Add an event' }}</h2>
    <form class="event-create-form" method="POST" action="{{ isset($event) ? route('events.update', $event) : route('events.store') }}">
        @csrf
        @if (isset($event))
            @method('PUT')
        @endif
        <div class="event-create-fields">
            <label class="event-create-field event-create-field--name" for="event-name">Event name
                <input id="event-name" name="name" value="{{ old('name', $event->name ?? '') }}" maxlength="160" required autocomplete="off" @error('name') aria-invalid="true" aria-describedby="event-name-error" @enderror>
                @error('name')<span class="form-error" id="event-name-error">{{ $message }}</span>@enderror
            </label>

            <label class="event-create-field event-create-field--academic-year" for="event-academic-year">Academic year
                <div class="event-create-year-picker" data-academic-year-picker>
                    <div class="event-create-year-control">
                        <input id="event-academic-year" type="text" name="academic_year" value="{{ old('academic_year', isset($event) ? $event->academicYear->name : '') }}" placeholder="Choose academic year" readonly required aria-controls="event-academic-year-calendar" @error('academic_year') aria-invalid="true" aria-describedby="event-academic-year-error" @enderror>
                        <button class="event-create-year-trigger" type="button" data-year-picker-trigger aria-haspopup="dialog" aria-expanded="false" aria-label="Open academic year calendar" aria-controls="event-academic-year-calendar">
                            <svg aria-hidden="true" viewBox="0 0 16 16" fill="currentColor"><path d="M4 1a.75.75 0 0 1 .75.75V3h6.5V1.75a.75.75 0 0 1 1.5 0V3H14a1 1 0 0 1 1 1v9a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V4a1 1 0 0 1 1-1h1V1.75A.75.75 0 0 1 4 1Zm9.5 5h-11v7a.5.5 0 0 0 .5.5h10a.5.5 0 0 0 .5-.5V6Z"/><path d="M4 8h2v2H4zm3 0h2v2H7zm3 0h2v2h-2zM4 11h2v2H4zm3 0h2v2H7z" fill="#fff"/></svg>
                        </button>
                    </div>
                    <div class="event-create-year-calendar" id="event-academic-year-calendar" data-year-picker-calendar role="dialog" aria-label="Choose an academic year" hidden>
                        <div class="event-create-year-calendar-heading">
                            <button class="event-create-year-nav" type="button" data-year-picker-previous aria-label="Previous decade">‹</button>
                            <strong data-year-picker-range aria-live="polite"></strong>
                            <button class="event-create-year-nav" type="button" data-year-picker-next aria-label="Next decade">›</button>
                        </div>
                        <div class="event-create-year-options" data-year-picker-options></div>
                    </div>
                </div>
                @error('academic_year')<span class="form-error" id="event-academic-year-error">{{ $message }}</span>@enderror
            </label>

            <label class="event-create-field event-create-field--description" for="event-description">Description
                <textarea id="event-description" name="description" maxlength="5000" rows="3" @error('description') aria-invalid="true" aria-describedby="event-description-error" @enderror>{{ old('description', $event->description ?? '') }}</textarea>
                @error('description')<span class="form-error" id="event-description-error">{{ $message }}</span>@enderror
            </label>

            <label class="event-create-field event-create-field--date" for="event-starts-at">Starts at
                <input id="event-starts-at" class="date-input" type="date" name="starts_at" value="{{ old('starts_at', isset($event) ? ($event->starts_at ?? $event->event_date)?->toDateString() : '') }}" @unless(isset($event)) min="{{ today()->toDateString() }}" @endunless data-event-start-date required @error('starts_at') aria-invalid="true" aria-describedby="event-starts-at-error" @enderror>
                @error('starts_at')<span class="form-error" id="event-starts-at-error">{{ $message }}</span>@enderror
            </label>

            <label class="event-create-field event-create-field--date" for="event-ends-at">Ends at
                <input id="event-ends-at" class="date-input" type="date" name="ends_at" value="{{ old('ends_at', isset($event) ? ($event->ends_at ?? $event->starts_at ?? $event->event_date)?->toDateString() : '') }}" @unless(isset($event)) min="{{ today()->toDateString() }}" @endunless data-event-end-date required @error('ends_at') aria-invalid="true" aria-describedby="event-ends-at-error" @enderror>
                @error('ends_at')<span class="form-error" id="event-ends-at-error">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="event-create-actions">
            <a class="button button--outline" href="{{ route('events.index') }}">Cancel</a>
            <button class="button button--primary" type="submit">{{ isset($event) ? 'Save Changes' : 'Save Event' }}</button>
        </div>
    </form>
</section>
