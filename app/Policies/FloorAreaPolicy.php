<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FloorArea;
use App\Models\User;

/**
 * Floor areas belong to a branch, so a user only reaches those of the
 * branches they work in.
 */
class FloorAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTables);
    }

    public function view(User $user, FloorArea $area): bool
    {
        return $user->can(Permission::ViewTables) && $user->canAccessBranch($area->branch);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageTables);
    }

    public function update(User $user, FloorArea $area): bool
    {
        return $user->can(Permission::ManageTables) && $user->canAccessBranch($area->branch);
    }

    public function delete(User $user, FloorArea $area): bool
    {
        return $this->update($user, $area);
    }
}
