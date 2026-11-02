@extends('layouts.auth', ['title' => 'Reset Password'])

@section('content')
    <header class="auth-heading auth-heading--forgot">
        <h2 id="page-title">Reset Password</h2>
        <p>Choose a new password for your account.</p>
    </header>

    <form class="auth-form auth-form--forgot" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label class="sr-only" for="email">Email</label>
        <div class="field @error('email') field--error @enderror">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0v1h14v-1Z"/></svg>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" placeholder="Email" autocomplete="email" required autofocus>
        </div>
        @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror

        <label class="sr-only" for="password">New password</label>
        <div class="field @error('password') field--error @enderror">
            <svg class="field__lock-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 10V8a6 6 0 0 1 12 0v2"/><path d="M5 10h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/></svg>
            <input id="password" name="password" type="password" placeholder="New password" autocomplete="new-password" required>
            <button class="visibility-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a14 14 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4.2-.9"/></svg>
            </button>
        </div>
        <div class="password-strength" data-password-strength>
            <div class="password-strength__track" role="progressbar" aria-label="Password strength" aria-valuemin="0" aria-valuemax="6" aria-valuenow="0" aria-valuetext="No password entered">
                <span data-password-strength-bar></span>
            </div>
            <p class="password-strength__label" data-password-strength-label aria-live="polite">Enter a password to check its strength.</p>
            <p class="password-strength__hint">Use 12 or more characters with uppercase, lowercase, a number, and a symbol for a strong password.</p>
        </div>

        <label class="sr-only" for="password_confirmation">Confirm new password</label>
        <div class="field">
            <svg class="field__lock-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 10V8a6 6 0 0 1 12 0v2"/><path d="M5 10h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/></svg>
            <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Confirm password" autocomplete="new-password" required>
            <button class="visibility-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a14 14 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4.2-.9"/></svg>
            </button>
        </div>
        @error('password') <p class="field-error" role="alert">{{ $message }}</p> @enderror

        <div class="button-row">
            <a class="button button--outline" href="{{ route('login') }}">Back</a>
            <button class="button button--primary" type="submit">Reset</button>
        </div>
    </form>
@endsection
