<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant-owned model to the current business.
 */
class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->isScoped()) {
            return;
        }

        $businessId = $context->businessId();

        // Someone is signed in but is acting in no business, such as a
        // platform user outside Super Impose: they reach no tenant data.
        if ($businessId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $model->applyBusinessScope($builder, $businessId);
    }
}
