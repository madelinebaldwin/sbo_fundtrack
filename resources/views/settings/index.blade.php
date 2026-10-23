@extends('layouts.app', ['pageTitle' => 'Setting'])

@section('content')
    <div class="settings-page">
        <section class="settings-panel password-settings"><h2>Change Password</h2>
            <form method="POST" action="{{ route('settings.password-update') }}">@csrf @method('PUT')
                <div class="form-grid password-form-grid">
                    <label class="form-field">Current Password<input type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label class="form-field">New Password<input type="password" name="password" autocomplete="new-password" required>@error('password')<span class="form-error">{{ $message }}</span>@enderror<span class="form-help">At least 12 characters, including uppercase, lowercase, and a number.</span></label>
                    <label class="form-field">Confirm New Password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
                    <div class="form-field password-otp-field"><div class="inline-otp">
                        <label class="form-field">OTP<input inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="otp" autocomplete="one-time-code" placeholder="6-digit code" required>@error('otp')<span class="form-error">{{ $message }}</span>@enderror</label>
                        <button class="button button--primary button--small" form="send-password-code" type="submit" @disabled(! $mailDeliveryReady) title="{{ $mailDeliveryReady ? 'Send a one-time password code' : 'Configure email delivery to send a code' }}">SEND</button>
                    </div><span class="form-help">@if($mailDeliveryReady)A code will be sent to {{ auth()->user()->email }} and expires after 10 minutes.@else Email delivery is not configured. OTP requests are disabled until a real mail transport is set.@endif</span></div>
                </div>
                <div class="form-actions"><button class="button button--primary" type="submit">Update Password</button></div>
            </form>
            <form id="send-password-code" method="POST" action="{{ route('settings.password-code') }}">@csrf</form>
        </section>

    </div>
@endsection
