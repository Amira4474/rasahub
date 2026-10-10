<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TenantInvitation;
use App\Models\User;

/**
 * Invitations are kept as a record of who was invited, so they are revoked
 * rather than deleted.
 */
class TenantInvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageInvitations);
    }

    public function view(User $user, TenantInvitation $invitation): bool
    {
        return $user->can(Permission::ManageInvitations);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageInvitations);
    }

    /**
     * The only change to a pending invitation is revoking it.
     */
    public function update(User $user, TenantInvitation $invitation): bool
    {
        return $user->can(Permission::ManageInvitations) && $invitation->isUsable();
    }

    public function delete(User $user, TenantInvitation $invitation): bool
    {
        return false;
    }
}
