<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewUsers);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::ViewUsers) && $user->belongsToBusiness($model->business_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateUsers);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UpdateUsers) && $user->belongsToBusiness($model->business_id);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::DeleteUsers)
            && $user->belongsToBusiness($model->business_id)
            && $user->isNot($model);
    }
}
