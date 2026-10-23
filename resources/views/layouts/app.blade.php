<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} | FundTrack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
    <div class="app-shell">
        <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Close navigation"></button>
        <aside class="app-sidebar" id="app-sidebar" aria-label="Main navigation">
            <a class="app-brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('images/isabela-state-university-logo.png') }}" alt="Isabela State University criminology emblem">
                <span class="app-brand__text"><strong>FundTrack</strong><small>CCJE SBO</small></span>
            </a>

            <p class="nav-label">WORKSPACE</p>
            <nav class="primary-nav">
                @foreach ([
                    ['Dashboard', 'dashboard', 'dashboard'],
                    ['Events', 'events.index', 'events.*'],
                    ['Collections', 'collections.index', 'collections.*'],
                    ['Expenses', 'expenses.index', 'expenses.*'],
                    ['Reports', 'reports.index', 'reports.*'],
                    ['Analytics', 'analytics.index', 'analytics.*'],
                    ['Setting', 'settings.index', 'settings.*'],
                ] as [$label, $routeName, $activePattern])
                    <a href="{{ route($routeName) }}" class="nav-link {{ request()->routeIs($activePattern) ? 'is-active' : '' }}" @if (request()->routeIs($activePattern)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <form class="sidebar-logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button button--primary button--full" type="submit">Logout</button>
            </form>
        </aside>

        <div class="app-main">
            <header class="app-header">
                <button class="menu-toggle" type="button" data-sidebar-open aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1>{{ $pageTitle }}</h1>
                <time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, F j, Y') }}</time>
            </header>

            <main class="app-content">
                @if (session('status'))
                    <div class="notice notice--success" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="notice notice--error" role="alert">
                        <strong>Please check the form:</strong>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
