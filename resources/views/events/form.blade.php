<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    @php($selectedAcademicYearId = old('academic_year_id', $event->academic_year_id ?? request('academic_year_id', $academicYears->firstWhere('is_active', true)?->id)))
    <div class="form-grid">
        <label class="form-field form-field--full">Event name<input name="name" value="{{ old('name', $event->name ?? '') }}" maxlength="160" required>@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field">Academic year<select name="academic_year_id" required><option value="">Choose a year</option>@foreach ($academicYears as $academicYear)<option value="{{ $academicYear->id }}" @selected((string) $selectedAcademicYearId === (string) $academicYear->id)>{{ $academicYear->name }}{{ $academicYear->is_active ? ' · Active' : '' }}</option>@endforeach</select>@error('academic_year_id')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field">Event date<input type="date" name="event_date" value="{{ old('event_date', isset($event) ? $event->event_date?->toDateString() : '') }}">@error('event_date')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="form-field">Venue<input name="venue" value="{{ old('venue', $event->venue ?? '') }}" maxlength="160"></label>
        <label class="form-field">Contribution target (PHP)<input type="number" name="contribution_target" min="0" step="0.01" value="{{ old('contribution_target', $event->contribution_target ?? '0.00') }}"></label>
        <label class="form-field form-field--full">Description<textarea name="description" maxlength="5000">{{ old('description', $event->description ?? '') }}</textarea></label>
    </div>
    <div class="form-actions"><a class="button button--outline" href="{{ route('events.index') }}">Cancel</a><button class="button button--primary" type="submit">Save Event</button></div>
</form>
