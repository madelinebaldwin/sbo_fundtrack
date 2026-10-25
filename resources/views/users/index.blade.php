@extends('layouts.app', ['pageTitle' => 'User Access'])

@section('content')
    <section class="form-panel"><h2>Add a user</h2>
        <form class="users-form" method="POST" action="{{ route('users.store') }}">@csrf
            <div class="form-field"><label for="new-name">Name</label><input id="new-name" name="name" value="{{ old('name') }}" required>@error('name')<span class="form-error">{{ $message }}</span>@enderror</div>
            <div class="form-field"><label for="new-email">Email</label><input id="new-email" type="email" name="email" value="{{ old('email') }}" required>@error('email')<span class="form-error">{{ $message }}</span>@enderror</div>
            <div class="form-field"><label for="new-role">Role</label><select class="form-control" name="role" id="new-role" required>@foreach (['treasurer', 'encoder', 'auditor', 'administrator'] as $role)<option value="{{ $role }}">{{ ucfirst($role) }}</option>@endforeach</select>@error('role')<span class="form-error">{{ $message }}</span>@enderror</div>
            <button class="button button--primary" type="submit">Create Account</button>
        </form>
        <p class="form-help">A strong random password is set automatically. The user must use Forgot Password to set their own password when email delivery is configured.</p>
    </section>

    <section class="panel section-gap"><div class="panel-heading"><h2>Accounts and roles</h2><a class="text-link" href="{{ route('settings.index') }}">Back to settings</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Current role</th><th>Update role</th><th>Action</th></tr></thead><tbody>
            @foreach ($users as $user)
                <tr><td>{{ $user->name }} @if($user->is(auth()->user()))<span class="form-help">(you)</span>@endif</td><td>{{ $user->email }}</td><td>{{ ucfirst($user->role) }}</td><td><form class="row-actions" method="POST" action="{{ route('users.update', $user) }}">@csrf @method('PATCH')<select class="form-control role-select" name="role" aria-label="Role for {{ $user->name }}">@foreach (['administrator', 'treasurer', 'encoder', 'auditor'] as $role)<option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>@endforeach</select><button class="button button--outline button--small" type="submit">Save</button></form></td><td>@if($user->is(auth()->user()))<button class="button button--danger button--small" type="button" disabled title="You cannot delete your own account.">Delete</button>@else<form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="Delete this user account? This cannot be undone.">@csrf @method('DELETE')<button class="button button--danger button--small" type="submit">Delete</button></form>@endif</td></tr>
            @endforeach
        </tbody></table></div>
        <div class="pagination">{{ $users->links() }}</div>
    </section>
@endsection
