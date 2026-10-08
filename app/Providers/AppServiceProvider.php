<?php

namespace App\Providers;

use App\Enums\Role;
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
        // Super Admins pass every authorization check, so individual
        // permissions never need to be granted to that role.
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin) ? true : null);
    }
}
