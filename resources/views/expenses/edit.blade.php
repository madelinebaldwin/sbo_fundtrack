@extends('layouts.app', ['pageTitle' => 'Edit Expense'])

@section('content')
    <section class="form-panel"><h2>Edit pending expense</h2>@include('expenses.form', ['action' => $managementEvent ? route('events.manage.expenses.update', [$managementEvent, $expense]) : route('expenses.update', $expense), 'method' => 'PUT'])</section>
@endsection
