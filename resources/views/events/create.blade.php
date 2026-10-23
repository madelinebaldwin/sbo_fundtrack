@extends('layouts.app', ['pageTitle' => 'Add Event'])

@section('content')
    <section class="event-create-panel" aria-labelledby="event-create-title">
        <h2 id="event-create-title">Add an event</h2>
        <form class="event-create-form" method="POST" action="{{ route('events.store') }}">
            @csrf
            <div class="event-create-fields">
                <label class="event-create-field event-create-field--name" for="event-name">Event name
                    <input id="event-name" name="name" value="{{ old('name') }}" maxlength="160" required autocomplete="off" @error('name') aria-invalid="true" aria-describedby="event-name-error" @enderror>
                    @error('name')<span class="form-error" id="event-name-error">{{ $message }}</span>@enderror
                </label>

                <label class="event-create-field event-create-field--description" for="event-description">Description
                    <textarea id="event-description" name="description" maxlength="5000" rows="3" @error('description') aria-invalid="true" aria-describedby="event-description-error" @enderror>{{ old('description') }}</textarea>
                    @error('description')<span class="form-error" id="event-description-error">{{ $message }}</span>@enderror
                </label>

                <label class="event-create-field event-create-field--date" for="event-starts-at">Starts at
                    <input id="event-starts-at" type="date" name="starts_at" value="{{ old('starts_at') }}" required @error('starts_at') aria-invalid="true" aria-describedby="event-starts-at-error" @enderror>
                    @error('starts_at')<span class="form-error" id="event-starts-at-error">{{ $message }}</span>@enderror
                </label>

                <label class="event-create-field event-create-field--date" for="event-ends-at">Ends at
                    <input id="event-ends-at" type="date" name="ends_at" value="{{ old('ends_at') }}" required @error('ends_at') aria-invalid="true" aria-describedby="event-ends-at-error" @enderror>
                    @error('ends_at')<span class="form-error" id="event-ends-at-error">{{ $message }}</span>@enderror
                </label>

                <label class="event-create-field event-create-field--status" for="event-status">Status
                    <select id="event-status" name="status" required @error('status') aria-invalid="true" aria-describedby="event-status-error" @enderror>
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="archived" @selected(old('status') === 'archived')>Archived</option>
                    </select>
                    @error('status')<span class="form-error" id="event-status-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <div class="event-create-actions">
                <a class="button button--outline" href="{{ route('events.index') }}">Cancel</a>
                <button class="button button--primary" type="submit">Save Event</button>
            </div>
        </form>
    </section>
@endsection
