<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;

/**
 * The menu belongs to the whole business, so there is no branch check here.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCatalogue);
    }

    public function view(User $user, Category $record): bool
    {
        return $user->can(Permission::ViewCatalogue);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }

    public function update(User $user, Category $record): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }

    /**
     * Only once nothing is filed under it; until then it can be deactivated.
     */
    public function delete(User $user, Category $record): bool
    {
        return $user->can(Permission::ManageCatalogue) && $record->isEmpty();
    }
}
