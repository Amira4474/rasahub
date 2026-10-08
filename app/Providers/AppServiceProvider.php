<?php

namespace App\Providers;

use App\Audit\Audit;
use App\Auth\SignInUserProvider;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\User;
use App\Tenancy\SuperImpose;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabase;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SuperImpose::class);
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(TenantDatabase::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('sign-in', fn (Application $app, array $config) => new SignInUserProvider($app['hash'], $config['model']));

        $this->pointTheTenantConnectionAtTheCurrentBusiness();
        $this->keepPlatformAndTenantPermissionsApart();
        $this->auditSignInAndSignOut();
    }

    /**
     * The tenant connection has no database of its own in the config. Each
     * time it is opened it is given the database of the business being
     * acted in, and it is closed whenever who is signed in changes.
     */
    private function pointTheTenantConnectionAtTheCurrentBusiness(): void
    {
        DB::extend(TenantDatabase::CONNECTION, function (array $config, string $name) {
            $config['database'] = app(TenantDatabase::class)->databaseForNewConnection();

            return app('db.factory')->make($config, $name);
        });

        Event::listen([Authenticated::class, Login::class, Logout::class], function (): void {
            // Work deliberately being done inside one business is not
            // affected by who is signed in.
            if (! app(TenantContext::class)->isForced()) {
                app(TenantDatabase::class)->release();
            }
        });
    }

    /**
     * The single place every permission check is answered.
     *
     * Platform permissions belong to platform users and tenant permissions to
     * tenant users, whatever happens to be attached to an account. The one
     * crossing point is Super Impose: while it is active, the platform user
     * holds every tenant permission, and policies still confine them to the
     * business they entered.
     */
    private function keepPlatformAndTenantPermissionsApart(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            $permission = Permission::tryFrom($ability);

            // Not a permission (a policy method such as "update"): the
            // policy decides, and it asks for permissions in turn.
            if ($permission === null) {
                return null;
            }

            if ($permission->isPlatform() !== $user->isPlatformUser()) {
                return ! $permission->isPlatform() && app(SuperImpose::class)->businessIdFor($user) !== null;
            }

            return $user->checkPermissionTo($permission->value);
        });
    }

    private function auditSignInAndSignOut(): void
    {
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User && $event->user->isPlatformUser()) {
                app(Audit::class)->record(AuditEvent::PlatformUserSignedIn, actor: $event->user);
            }
        });

        // Signing out ends Super Impose, and that ending is recorded.
        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                app(SuperImpose::class)->end($event->user);
            }
        });
    }
}
