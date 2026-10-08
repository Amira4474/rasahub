<?php

namespace App\Enums;

/**
 * Every permission the application knows about.
 *
 * Each permission is either a platform permission or a tenant permission,
 * never both. Platform permissions are held by platform users and say what
 * they may do above the businesses. Tenant permissions say what someone may
 * do inside one business.
 *
 * Run `php artisan db:seed` after changing this list.
 */
enum Permission: string
{
    // Platform
    case AccessPlatform = 'platform.access';
    case ViewTenants = 'tenants.view';
    case ManageTenants = 'tenants.manage';
    case StartSuperImpose = 'super-impose.start';
    case ViewAuditLog = 'audit.view';
    case ManagePlatformUsers = 'platform-users.manage';
    case ManageSubscriptions = 'subscriptions.manage';
    case ManageInvitations = 'invitations.manage';
    case RunMigrations = 'migrations.run';

    // Tenant
    case AccessAdminPanel = 'admin.access';
    case AccessPos = 'pos.access';

    case ViewBusinesses = 'businesses.view';
    case UpdateBusinesses = 'businesses.update';

    // Without this, a user only reaches the branches assigned in branch_user.
    case AccessAllBranches = 'branches.access-all';
    case ViewBranches = 'branches.view';
    case CreateBranches = 'branches.create';
    case UpdateBranches = 'branches.update';
    case DeleteBranches = 'branches.delete';

    case ViewTerminals = 'terminals.view';
    case CreateTerminals = 'terminals.create';
    case UpdateTerminals = 'terminals.update';
    case DeleteTerminals = 'terminals.delete';

    case ViewUsers = 'users.view';
    case CreateUsers = 'users.create';
    case UpdateUsers = 'users.update';
    case DeleteUsers = 'users.delete';

    public function isPlatform(): bool
    {
        return in_array($this, [
            self::AccessPlatform,
            self::ViewTenants,
            self::ManageTenants,
            self::StartSuperImpose,
            self::ViewAuditLog,
            self::ManagePlatformUsers,
            self::ManageSubscriptions,
            self::ManageInvitations,
            self::RunMigrations,
        ], true);
    }
}
