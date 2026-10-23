@extends('layouts.app', ['pageTitle' => 'Edit Expense'])

@section('content')
    <section class="form-panel"><h2>Edit pending expense</h2>@include('expenses.form', ['action' => route('expenses.update', $expense), 'method' => 'PUT'])</section>
@endsection
