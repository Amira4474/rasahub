<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

/**
 * Orders are made at the POS, never in the admin, where they are only read.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrders);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can(Permission::ViewOrders) && $user->canAccessBranch($order->branch);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
