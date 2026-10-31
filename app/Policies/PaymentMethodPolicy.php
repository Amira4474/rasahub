<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Payment methods are part of the restaurant's settings: whoever may change
 * the settings manages them.
 */
class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewBusinesses);
    }

    public function view(User $user, PaymentMethod $method): bool
    {
        return $user->can(Permission::ViewBusinesses);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UpdateBusinesses);
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $user->can(Permission::UpdateBusinesses);
    }

    public function delete(User $user, PaymentMethod $method): bool
    {
        return $user->can(Permission::UpdateBusinesses);
    }
}
