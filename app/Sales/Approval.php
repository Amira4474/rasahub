<?php

namespace App\Sales;

use App\Enums\Permission;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A Supervisor, Manager or Owner approving something at the till with
 * their approval PIN, for a cashier who may not do it alone.
 */
class Approval
{
    private const ATTEMPTS = 5;

    /**
     * Who approves the action: the user themselves if they may do it, or the
     * staff member whose PIN this is and who may. Null when it needs a PIN
     * and none was given.
     *
     * @throws SaleException When the PIN is wrong or too many were tried.
     */
    public function approver(User $user, Permission $permission, ?string $pin): ?User
    {
        if ($user->can($permission)) {
            return $user;
        }

        if (blank($pin)) {
            return null;
        }

        $key = 'approval-pin:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            throw new SaleException('Too many wrong PINs. Try again in a minute.');
        }

        $approver = $this->findByPin((string) $pin, $permission);

        if ($approver === null) {
            RateLimiter::hit($key);

            throw new SaleException('That PIN cannot approve this.');
        }

        RateLimiter::clear($key);

        return $approver;
    }

    /**
     * The active staff member of the current business with this PIN who
     * holds the permission. PINs are hashed, so each candidate is checked.
     */
    private function findByPin(string $pin, Permission $permission): ?User
    {
        $businessId = app(TenantContext::class)->businessId();

        return User::acrossBusinesses()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->whereNotNull('approval_pin')
            ->get()
            ->first(fn (User $staff): bool => $staff->matchesApprovalPin($pin) && $staff->hasPermissionTo($permission->value));
    }
}
