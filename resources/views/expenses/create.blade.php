@extends('layouts.app', ['pageTitle' => 'Add Expense'])

@section('content')
    <section class="form-panel"><h2>New expense</h2>
        @if ($events->isEmpty() || $categories->isEmpty())
            <p>{{ $events->isEmpty() ? 'Create an active event before adding an expense.' : 'No active expense categories are available.' }}</p>@if($events->isEmpty())<a class="button button--primary" href="{{ route('events.create') }}">Add Event</a>@endif
        @else
            @include('expenses.form', ['action' => $managementEvent ? route('events.manage.expenses.store', $managementEvent) : route('expenses.store'), 'method' => 'POST'])
        @endif
    </section>
@endsection
