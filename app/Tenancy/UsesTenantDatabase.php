<?php

namespace App\Tenancy;

use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\NoTenantException;

/**
 * Add this to every model whose table lives in the tenant databases.
 *
 * Each query goes to the database of the business being acted in. With no
 * business there is no database, so the query fails rather than returning
 * someone else's data. A record loaded from one tenant can never be read
 * from, saved to or deleted from another tenant's database.
 */
trait UsesTenantDatabase
{
    private ?int $tenantBusinessId = null;

    public static function bootUsesTenantDatabase(): void
    {
        $remember = function (self $model): void {
            $model->tenantBusinessId = app(TenantContext::class)->businessId();
        };

        static::retrieved($remember);
        static::created($remember);
    }

    public function getConnectionName()
    {
        return TenantDatabase::CONNECTION;
    }

    /**
     * @throws NoTenantException
     * @throws CrossTenantWriteException
     */
    public function getConnection()
    {
        $current = app(TenantDatabase::class)->useCurrent();

        if ($this->tenantBusinessId !== null && $this->tenantBusinessId !== $current) {
            throw CrossTenantWriteException::for(static::class);
        }

        return parent::getConnection();
    }

    /**
     * A relationship is queried through a fresh instance of the related
     * model, which would not know where this record came from. Checking here
     * stops a record from one tenant being used to look things up in another.
     */
    protected function newRelatedInstance($class)
    {
        $this->getConnection();

        return parent::newRelatedInstance($class);
    }

    /**
     * The business whose database this record came from, or null for a
     * record that has not been saved yet.
     */
    public function tenantBusinessId(): ?int
    {
        return $this->tenantBusinessId;
    }
}
