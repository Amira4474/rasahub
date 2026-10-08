<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Terminal;
use App\Models\User;

class TerminalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTerminals);
    }

    public function view(User $user, Terminal $terminal): bool
    {
        return $user->can(Permission::ViewTerminals) && $user->canAccessBranch($terminal->branch);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateTerminals);
    }

    public function update(User $user, Terminal $terminal): bool
    {
        return $user->can(Permission::UpdateTerminals) && $user->canAccessBranch($terminal->branch);
    }

    public function delete(User $user, Terminal $terminal): bool
    {
        return $user->can(Permission::DeleteTerminals) && $user->canAccessBranch($terminal->branch);
    }
}
