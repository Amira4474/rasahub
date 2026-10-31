<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Shift;
use App\Models\User;

/**
 * Shifts are opened and closed at the POS, and only read in the admin.
 */
class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrders);
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->can(Permission::ViewOrders) && $user->canAccessBranch($shift->branch);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Shift $shift): bool
    {
        return false;
    }

    public function delete(User $user, Shift $shift): bool
    {
        return false;
    }
}
