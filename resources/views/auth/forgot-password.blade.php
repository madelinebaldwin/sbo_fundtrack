@extends('layouts.auth', ['title' => 'Forgot Password'])

@section('content')
    <header class="auth-heading auth-heading--forgot">
        <h2 id="page-title">Forgot Password</h2>
        <p>Enter your email and we'll send you a password reset link.</p>
    </header>

    @if (session('status'))
        <div class="message message--success" role="status">{{ session('status') }}</div>
    @endif

    <form class="auth-form auth-form--forgot" method="POST" action="{{ route('password.email') }}">
        @csrf
        <label class="sr-only" for="email">Email</label>
        <div class="field @error('email') field--error @enderror">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0v1h14v-1Z"/></svg>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Email" autocomplete="email" required autofocus>
        </div>
        @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror

        <div class="button-row">
            <a class="button button--outline" href="{{ route('login') }}">Back</a>
            <button class="button button--primary" type="submit">Reset</button>
        </div>
    </form>
@endsection
