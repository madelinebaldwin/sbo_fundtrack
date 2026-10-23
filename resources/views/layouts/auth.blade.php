<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign In' }} | FundTrack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="brand" aria-label="FundTrack, CCJE Student Body Organization">
            <img class="brand__seal" src="{{ asset('images/isabela-state-university-logo.png') }}" alt="Isabela State University criminology emblem">
            <h1 class="brand__name">FundTrack</h1>
            <p class="brand__subtitle">CCJE STUDENT BODY ORGANIZATION</p>
        </section>

        <section class="auth-card" aria-labelledby="page-title">
            @yield('content')
        </section>
    </main>
</body>
</html>
