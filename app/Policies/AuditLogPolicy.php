<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;

/**
 * The audit trail can be read by those allowed to, and changed by no one.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewAuditLog);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->can(Permission::ViewAuditLog);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
