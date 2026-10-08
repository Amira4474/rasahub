<?php

namespace App\Enums;

/**
 * Every permission the application checks. Add a case here, grant it to
 * roles in Role::permissions(), then re-run the RolesAndPermissionsSeeder.
 */
enum Permission: string
{
    case AccessAdminPanel = 'admin.access';

    case ViewBusinesses = 'businesses.view';
    case CreateBusinesses = 'businesses.create';
    case UpdateBusinesses = 'businesses.update';
    case DeleteBusinesses = 'businesses.delete';

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
}
