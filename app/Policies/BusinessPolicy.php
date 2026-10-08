<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Business;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * A business is reached two ways: by platform users managing tenants, and by
 * the people inside it looking at their own settings.
 */
class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTenants);
    }

    public function view(User $user, Business $business): bool
    {
        return $user->can(Permission::ViewTenants)
            || ($user->can(Permission::ViewBusinesses) && $this->isCurrent($user, $business));
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageTenants);
    }

    public function update(User $user, Business $business): bool
    {
        return $user->can(Permission::ManageTenants)
            || ($user->can(Permission::UpdateBusinesses) && $this->isCurrent($user, $business));
    }

    public function delete(User $user, Business $business): bool
    {
        return $user->can(Permission::ManageTenants);
    }

    private function isCurrent(User $user, Business $business): bool
    {
        return app(TenantContext::class)->allows($user, $business);
    }
}
