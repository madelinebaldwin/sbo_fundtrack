<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-finances', fn (User $user): bool => in_array($user->role, [
            'administrator', 'treasurer', 'encoder', 'auditor',
        ], true));

        Gate::define('manage-events', fn (User $user): bool => in_array($user->role, [
            'administrator', 'treasurer', 'encoder',
        ], true));

        Gate::define('manage-finances', fn (User $user): bool => in_array($user->role, [
            'administrator', 'treasurer', 'encoder',
        ], true));

        Gate::define('review-expenses', fn (User $user): bool => in_array($user->role, [
            'administrator', 'treasurer',
        ], true));

        Gate::define('manage-settings', fn (User $user): bool => $user->isAdministrator());
        Gate::define('manage-users', fn (User $user): bool => $user->isAdministrator());
    }
}
