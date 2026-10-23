<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('status', __('Password reset email delivery is not configured. Please try again later.'));
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __('If an account matches that email, a password reset link will be sent.'));
        }

        if (in_array($status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return back()->with('status', __('If an account matches that email, a password reset link will be sent.'));
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
