<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Platform users are managed with a platform permission. Everyone else is
 * managed with the tenant permissions, inside their own business only.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewUsers) || $user->can(Permission::ManagePlatformUsers);
    }

    public function view(User $user, User $model): bool
    {
        return $this->allows($user, $model, Permission::ViewUsers);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateUsers) || $user->can(Permission::ManagePlatformUsers);
    }

    public function update(User $user, User $model): bool
    {
        return $this->allows($user, $model, Permission::UpdateUsers);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isNot($model) && $this->allows($user, $model, Permission::DeleteUsers);
    }

    private function allows(User $user, User $model, Permission $tenantPermission): bool
    {
        if ($model->isPlatformUser()) {
            return $user->can(Permission::ManagePlatformUsers);
        }

        return $user->can($tenantPermission) && app(TenantContext::class)->allows($user, $model->business_id);
    }
}
