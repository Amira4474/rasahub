<?php

namespace App\Tenancy;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\Business;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Lets a platform user work inside one tenant without becoming a tenant user.
 *
 * The platform user stays signed in as themselves and users.business_id is
 * never touched. The only thing that changes is a note in their session
 * saying which business they have entered. It ends when they exit or sign
 * out; there is no time limit.
 */
class SuperImpose
{
    private const SESSION_KEY = 'super_impose';

    // Where the platform user went in: the tenant's admin pages or its POS.
    public const INTO_ADMIN = 'admin';

    public const INTO_POS = 'pos';

    /**
     * @throws AuthorizationException
     */
    public function start(Business $business, ?string $reason = null, string $into = self::INTO_ADMIN): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isPlatformUser() || ! $user->can(Permission::StartSuperImpose)) {
            throw new AuthorizationException('You are not allowed to use Super Impose.');
        }

        if (! $business->exists || $business->trashed()) {
            throw new AuthorizationException('That business cannot be entered.');
        }

        $this->exit();

        $into = $into === self::INTO_POS ? self::INTO_POS : self::INTO_ADMIN;

        Session::put(self::SESSION_KEY, [
            'business_id' => $business->getKey(),
            'into' => $into,
            'started_at' => now()->toIso8601String(),
        ]);

        // The business being acted in has just changed.
        app(TenantDatabase::class)->release();

        app(Audit::class)->record(AuditEvent::SuperImposeStarted, $business, array_filter(['into' => $into, 'reason' => $reason]));
    }

    /**
     * Where the platform user went in, and is sent back to while Super
     * Impose lasts.
     */
    public function entryUrl(): string
    {
        return Session::get(self::SESSION_KEY.'.into') === self::INTO_POS
            ? route('pos.terminal')
            : Filament::getPanel('admin')->getUrl();
    }

    public function exit(): void
    {
        if (Auth::hasUser()) {
            $this->end(Auth::user());
        }
    }

    /**
     * End Super Impose for a user who is signing out. By the time the sign-out
     * is announced the user is no longer the signed-in one, so this reads the
     * session directly.
     */
    public function end(User $user): void
    {
        $businessId = Session::get(self::SESSION_KEY.'.business_id');

        if ($businessId === null) {
            return;
        }

        app(Audit::class)->record(
            AuditEvent::SuperImposeExited,
            Business::withTrashed()->find($businessId),
            actor: $user,
            superImpose: true,
        );

        Session::forget(self::SESSION_KEY);

        app(TenantDatabase::class)->release();
    }

    public function isActive(): bool
    {
        return Auth::hasUser() && $this->businessIdFor(Auth::user()) !== null;
    }

    /**
     * The business the given user has entered, or null. Only ever non-null
     * for the signed-in platform user who is still allowed to Super Impose.
     */
    public function businessIdFor(User $user): ?int
    {
        if (! Auth::hasUser() || ! $user->is(Auth::user()) || ! $user->isPlatformUser()) {
            return null;
        }

        $businessId = Session::get(self::SESSION_KEY.'.business_id');

        if ($businessId === null || ! $user->can(Permission::StartSuperImpose)) {
            return null;
        }

        return (int) $businessId;
    }
}
