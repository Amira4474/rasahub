<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewBranches);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ViewBranches) && $user->canAccessBranch($branch);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateBranches);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can(Permission::UpdateBranches) && $user->canAccessBranch($branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can(Permission::DeleteBranches) && $user->canAccessBranch($branch);
    }
}
