<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewBusinesses);
    }

    public function view(User $user, Business $business): bool
    {
        return $user->can(Permission::ViewBusinesses) && $user->belongsToBusiness($business);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateBusinesses);
    }

    public function update(User $user, Business $business): bool
    {
        return $user->can(Permission::UpdateBusinesses) && $user->belongsToBusiness($business);
    }

    public function delete(User $user, Business $business): bool
    {
        return $user->can(Permission::DeleteBusinesses) && $user->belongsToBusiness($business);
    }
}
