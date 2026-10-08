<?php

namespace App\Enums;

/**
 * The global set of roles. Roles say what a user may do; which branches
 * they may do it in is a separate concern, held in the branch_user table.
 */
enum Role: string
{
    case SuperAdmin = 'Super Admin';
    case Owner = 'Owner';
    case Manager = 'Manager';
    case Supervisor = 'Supervisor';
    case Cashier = 'Cashier';
    case Waiter = 'Waiter';
    case Kitchen = 'Kitchen';
    case Inventory = 'Inventory';

    /**
     * The permissions granted to the role.
     *
     * Super Admin is granted everything through Gate::before rather than
     * through individual permissions, so it is intentionally empty here.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => [
                Permission::AccessAdminPanel,
                Permission::ViewBusinesses,
                Permission::UpdateBusinesses,
                Permission::AccessAllBranches,
                Permission::ViewBranches,
                Permission::CreateBranches,
                Permission::UpdateBranches,
                Permission::DeleteBranches,
                Permission::ViewTerminals,
                Permission::CreateTerminals,
                Permission::UpdateTerminals,
                Permission::DeleteTerminals,
                Permission::ViewUsers,
                Permission::CreateUsers,
                Permission::UpdateUsers,
                Permission::DeleteUsers,
            ],
            self::Manager => [
                Permission::AccessAdminPanel,
                Permission::ViewBranches,
                Permission::ViewTerminals,
                Permission::CreateTerminals,
                Permission::UpdateTerminals,
                Permission::ViewUsers,
            ],
            self::Supervisor => [
                Permission::AccessAdminPanel,
                Permission::ViewBranches,
                Permission::ViewTerminals,
            ],
            default => [],
        };
    }
}
