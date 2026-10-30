<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;

/**
 * The menu belongs to the whole business, so there is no branch check here.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCatalogue);
    }

    public function view(User $user, Product $record): bool
    {
        return $user->can(Permission::ViewCatalogue);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }

    public function update(User $user, Product $record): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }

    public function delete(User $user, Product $record): bool
    {
        return $user->can(Permission::ManageCatalogue);
    }
}
