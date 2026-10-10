<?php

namespace App\Enums;

/**
 * Where a subscription stands today. Only Active lets a tenant's own staff
 * sign in.
 */
enum SubscriptionStatus: string
{
    // Registered, but not yet given its dates by a platform user.
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending activation',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Active => 'success',
            self::Expired => 'warning',
            self::Suspended => 'danger',
        };
    }
}
