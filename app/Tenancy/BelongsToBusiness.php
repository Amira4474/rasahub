<?php

namespace App\Tenancy;

use App\Exceptions\CrossTenantWriteException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Add this to every model that holds one business's data.
 *
 * It limits all reads to the current business, fills in the business when a
 * record is created, and refuses to create or move a record into another
 * business. A model without a business_id column of its own (one that
 * belongs to a business through a parent) overrides the three methods below.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function (self $model): void {
            $context = app(TenantContext::class);

            if ($context->isScoped()) {
                $model->enforceBusinessOnCreate($context->businessId());
            }
        });

        static::updating(function (self $model): void {
            $context = app(TenantContext::class);

            if ($context->isScoped()) {
                $model->enforceBusinessOnUpdate($context->businessId());
            }
        });
    }

    public function applyBusinessScope(Builder $builder, int $businessId): void
    {
        $builder->where($this->qualifyColumn('business_id'), $businessId);
    }

    protected function enforceBusinessOnCreate(?int $businessId): void
    {
        $this->business_id ??= $businessId;

        $own = $this->business_id === null ? null : (int) $this->business_id;

        if ($own !== $businessId) {
            throw CrossTenantWriteException::for(static::class);
        }
    }

    protected function enforceBusinessOnUpdate(?int $businessId): void
    {
        if ($this->isDirty('business_id')) {
            throw CrossTenantWriteException::for(static::class);
        }
    }

    /**
     * Deliberately read across every business. For platform code only.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function acrossBusinesses(Builder $query): void
    {
        $query->withoutGlobalScope(BusinessScope::class);
    }
}
