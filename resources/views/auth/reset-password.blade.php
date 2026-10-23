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
        </div>

        <label class="sr-only" for="password_confirmation">Confirm new password</label>
        <div class="field">
            <svg class="field__lock-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 10V8a6 6 0 0 1 12 0v2"/><path d="M5 10h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/></svg>
            <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Confirm password" autocomplete="new-password" required>
        </div>
        @error('password') <p class="field-error" role="alert">{{ $message }}</p> @enderror

        <div class="button-row">
            <a class="button button--outline" href="{{ route('login') }}">Back</a>
            <button class="button button--primary" type="submit">Reset</button>
        </div>
    </form>
@endsection
