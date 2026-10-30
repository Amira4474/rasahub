<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\BranchProduct;
use App\Models\User;

/**
 * A product's settings at a branch. Availability is for anyone who may set
 * it at that branch; a branch price also needs the catalogue permission,
 * which the form checks.
 */
class BranchProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCatalogue);
    }

    public function view(User $user, BranchProduct $setting): bool
    {
        return $user->can(Permission::ViewCatalogue) && $user->canAccessBranch($setting->branch);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SetProductAvailability);
    }

    public function update(User $user, BranchProduct $setting): bool
    {
        return $user->can(Permission::SetProductAvailability) && $user->canAccessBranch($setting->branch);
    }

    public function delete(User $user, BranchProduct $setting): bool
    {
        return $this->update($user, $setting);
    }
}
