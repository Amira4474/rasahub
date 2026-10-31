<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

/**
 * Customers belong to the whole restaurant, not to one branch. The POS adds
 * them at the till; the admin looks after their details.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCustomers);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permission::ViewCustomers);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCustomers);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permission::ManageCustomers);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::ManageCustomers);
    }
}
