@extends('layouts.auth', ['title' => 'Sign In'])

@section('content')
    <header class="auth-heading">
        <h2 id="page-title">Sign In</h2>
        <p>Welcome back. Sign in to continue.</p>
    </header>

    @if (session('status'))
        <div class="message message--success" role="status">{{ session('status') }}</div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('login.store') }}">
        @csrf
        <label class="sr-only" for="email">Email</label>
        <div class="field @error('email') field--error @enderror">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0v1h14v-1Z"/></svg>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Email" autocomplete="username" required autofocus>
        </div>
        @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror

        <label class="sr-only" for="password">Password</label>
        <div class="field @error('password') field--error @enderror">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
            <input id="password" name="password" type="password" placeholder="Password" autocomplete="current-password" required>
            <button class="visibility-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a14 14 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4.2-.9"/></svg>
            </button>
        </div>
        @error('password') <p class="field-error" role="alert">{{ $message }}</p> @enderror

        <div class="forgot-row"><a href="{{ route('password.request') }}">Forgot Password?</a></div>
        <button class="button button--primary button--full" type="submit">Login</button>
    </form>
@endsection
