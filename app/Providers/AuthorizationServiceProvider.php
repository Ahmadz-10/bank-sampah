<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Turns config/permissions.php into Gates, so abilities can be used as
 * @can('manage-users') in Blade and ->middleware('can:manage-users') in routes.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Super Admin can do everything; null = "no opinion", fall through to the Gates.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        foreach (config('permissions.abilities', []) as $ability => $roles) {
            Gate::define($ability, fn (User $user) => $user->hasRole(...$roles));
        }
    }
}
