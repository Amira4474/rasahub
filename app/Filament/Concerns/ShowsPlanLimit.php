<?php

namespace App\Filament\Concerns;

/**
 * For the list page of a resource that is LimitedByPlan: says why the
 * "New" button has gone once the plan's limit is reached.
 */
trait ShowsPlanLimit
{
    public function getSubheading(): ?string
    {
        return static::getResource()::planLimitReachedMessage();
    }
}
