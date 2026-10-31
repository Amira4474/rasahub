<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DiningTable;
use App\Models\User;

class DiningTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTables);
    }

    public function view(User $user, DiningTable $table): bool
    {
        return $user->can(Permission::ViewTables) && $user->canAccessBranch($table->area->branch);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageTables);
    }

    public function update(User $user, DiningTable $table): bool
    {
        return $user->can(Permission::ManageTables) && $user->canAccessBranch($table->area->branch);
    }

    public function delete(User $user, DiningTable $table): bool
    {
        return $this->update($user, $table);
    }
}
