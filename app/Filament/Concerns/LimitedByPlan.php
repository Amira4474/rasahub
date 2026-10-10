<?php

namespace App\Filament\Concerns;

use App\Models\Business;
use App\Tenancy\TenantContext;

/**
 * For a tenant resource whose records count against the subscription plan.
 * Once the business has as many as its plan allows, nobody can add another,
 * whatever their role, and that includes a platform user in Super Impose.
 */
trait LimitedByPlan
{
    /**
     * The limit in config/plans.php that this resource counts against.
     */
    abstract public static function planLimit(): string;

    public static function canCreate(): bool
    {
        return parent::canCreate() && static::planLimitReachedMessage() === null;
    }

    public static function planLimitReachedMessage(): ?string
    {
        $business = app(TenantContext::class)->business();

        if (! $business instanceof Business || $business->hasRoomFor(static::planLimit())) {
            return null;
        }

        return $business->limitReachedMessage(static::planLimit());
    }
}
