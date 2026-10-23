<?php

namespace App\Http\Controllers;

use App\Models\PasswordChangeChallenge;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordChangeOtpController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['otp' => 'Email delivery is not configured. Set a real mail transport before requesting a code.']);
        }

        $user = $request->user();
        $code = (string) random_int(100000, 999999);
        PasswordChangeChallenge::query()->where('user_id', $user->id)->delete();
        $challenge = PasswordChangeChallenge::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        try {
            Mail::raw('Your FundTrack password change code is '.$code.'. It expires in 10 minutes.', function ($message) use ($user): void {
                $message->to($user->email)->subject('FundTrack password change code');
            });
        } catch (Throwable $exception) {
            $challenge->delete();
            report($exception);

            return back()->withErrors(['otp' => 'The code could not be delivered. Check the mail configuration and try again.']);
        }

        return back()->with('status', 'A one-time code was sent to your account email.');
    }

    public function update(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'otp' => ['required', 'digits:6'],
        ]);
        $user = $request->user();

        $result = DB::transaction(function () use ($data, $user): string {
            $challenge = PasswordChangeChallenge::query()
                ->where('user_id', $user->id)
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($challenge === null || $challenge->expires_at->isPast()) {
                $challenge?->delete();

                return 'expired';
            }

            if ($challenge->attempts >= 5) {
                $challenge->delete();

                return 'locked';
            }

            if (! Hash::check($data['otp'], $challenge->code_hash)) {
                $challenge->increment('attempts');

                if ($challenge->fresh()->attempts >= 5) {
                    $challenge->delete();
                }

                return 'invalid';
            }

            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->update(['password' => Hash::make($data['password'])]);
            $challenge->delete();

            return 'updated';
        });

        if ($result !== 'updated') {
            throw ValidationException::withMessages([
                'otp' => match ($result) {
                    'expired' => 'The code has expired. Request a new one.',
                    'locked' => 'Too many incorrect codes. Request a new one.',
                    default => 'The one-time code is incorrect.',
                },
            ]);
        }

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        }

        $request->session()->regenerate();
        $activityLogger->record('user.password_changed', 'Changed account password.', $user);
        Auth::login($user->fresh());

        return back()->with('status', 'Password updated successfully.');
    }
}
