@extends('layouts.app', ['pageTitle' => 'Edit Event'])

@section('content')
    <section class="form-panel"><h2>Edit {{ $event->name }}</h2>@include('events.form', ['action' => route('events.update', $event), 'method' => 'PUT'])</section>
@endsection
