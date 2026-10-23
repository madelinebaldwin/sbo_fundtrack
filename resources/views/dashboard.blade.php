<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | FundTrack</title>
    @vite(['resources/css/app.css'])
</head>
<body class="dashboard-body">
    <main class="dashboard-card">
        <img src="{{ asset('images/isabela-state-university-logo.png') }}" alt="Isabela State University criminology emblem">
        <div>
            <p class="dashboard-eyebrow">CCJE STUDENT BODY ORGANIZATION</p>
            <h1>Welcome to FundTrack, {{ auth()->user()->name }}.</h1>
            <p>You are signed in successfully.</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="button button--primary" type="submit">Log out</button>
        </form>
    </main>
</body>
</html>
