<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::query()->orderBy('name')->paginate(20)]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['administrator', 'treasurer', 'encoder', 'auditor'])],
        ]);
        $temporaryPassword = Str::random(64);
        $user = DB::transaction(function () use ($data, $temporaryPassword, $activityLogger): User {
            $user = User::create([
                ...$data,
                'password' => Hash::make($temporaryPassword),
            ]);
            $activityLogger->record('user.created', 'Created account for '.$user->email, $user, ['role' => $user->role]);

            return $user;
        });

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('status', 'Account created. Configure real email delivery, then have the user request a password reset.');
        }

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('status', 'Account created, but the password setup email could not be delivered. The user can request a reset after mail is fixed.');
        }

        $message = $status === Password::RESET_LINK_SENT
            ? 'Account created and password setup email sent.'
            : 'Account created, but the password setup email could not be sent. The user can request a reset later.';

        return back()->with('status', $message);
    }

    public function update(Request $request, User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['administrator', 'treasurer', 'encoder', 'auditor'])],
        ]);

        DB::transaction(function () use ($data, $user, $activityLogger): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->isAdministrator() && $data['role'] !== 'administrator') {
                $administratorCount = User::query()->where('role', 'administrator')->lockForUpdate()->get(['id'])->count();
                abort_if($administratorCount <= 1, 409, 'The last administrator cannot be demoted.');
            }

            $oldRole = $lockedUser->role;
            $lockedUser->update($data);
            $activityLogger->record('user.role_changed', 'Changed role for '.$lockedUser->email.' from '.$oldRole.' to '.$lockedUser->role, $lockedUser, [
                'previous_role' => $oldRole,
                'role' => $lockedUser->role,
            ]);
        });

        return back()->with('status', 'User role updated.');
    }

    public function destroy(User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 409, 'You cannot delete your own account.');

        DB::transaction(function () use ($user, $activityLogger): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->isAdministrator()) {
                $administratorCount = User::query()->where('role', 'administrator')->lockForUpdate()->get(['id'])->count();
                abort_if($administratorCount <= 1, 409, 'The last administrator cannot be deleted.');
            }

            $activityLogger->record('user.deleted', 'Deleted account for '.$lockedUser->email, $lockedUser, [
                'role' => $lockedUser->role,
            ]);
            $lockedUser->delete();
        });

        return back()->with('status', 'User account deleted.');
    }
}
