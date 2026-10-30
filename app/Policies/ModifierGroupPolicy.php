<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ModifierGroup;
use App\Models\User;

/**
 * The menu belongs to the whole business, so there is no branch check here.
 */
class ModifierGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCatalogue);
    }

    public function view(User $user, ModifierGroup $record): bool
    {
        return $user->can(Permission::ViewCatalogue);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }

    public function update(User $user, ModifierGroup $record): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }

    public function delete(User $user, ModifierGroup $record): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }
}
