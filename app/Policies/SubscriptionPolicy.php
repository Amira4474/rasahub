<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Subscription;
use App\Models\User;

/**
 * Subscriptions are platform data. A subscription is created with its
 * tenant and lasts as long as the tenant does.
 */
class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTenants);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->can(Permission::ViewTenants);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->can(Permission::ManageSubscriptions);
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return false;
    }
}
