<?php

namespace App\Enums;

/**
 * The one global set of roles.
 *
 * A role says what a user may do. It never says which business or which
 * branches: those come from users.business_id and the tenant's branch_user.
 *
 * Platform roles are for platform users (no business). Business roles are for
 * tenant users. A user only ever holds roles of the kind that matches them.
 *
 * Run `php artisan db:seed` after changing a role's permissions.
 */
enum Role: string
{
    case PlatformSuperAdmin = 'Platform Super Admin';
    case PlatformAdmin = 'Platform Admin';
    case PlatformSupport = 'Platform Support';

    case Owner = 'Owner';
    case Manager = 'Manager';
    case Supervisor = 'Supervisor';
    case Cashier = 'Cashier';
    case Waiter = 'Waiter';
    case Kitchen = 'Kitchen';
    case Inventory = 'Inventory';

    public function isPlatform(): bool
    {
        return in_array($this, [self::PlatformSuperAdmin, self::PlatformAdmin, self::PlatformSupport], true);
    }

    /**
     * The names of the platform roles, or of the business roles.
     *
     * @return list<string>
     */
    public static function names(bool $platform): array
    {
        return array_values(array_map(
            fn (self $role): string => $role->value,
            array_filter(self::cases(), fn (self $role): bool => $role->isPlatform() === $platform),
        ));
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::PlatformSuperAdmin => [
                Permission::AccessPlatform,
                Permission::ViewTenants,
                Permission::ManageTenants,
                Permission::StartSuperImpose,
                Permission::ViewAuditLog,
                Permission::ManagePlatformUsers,
                Permission::ManageSubscriptions,
                Permission::ManageInvitations,
                Permission::RunMigrations,
            ],
            self::PlatformAdmin => [
                Permission::AccessPlatform,
                Permission::ViewTenants,
                Permission::ManageTenants,
                Permission::StartSuperImpose,
                Permission::ViewAuditLog,
                Permission::ManageSubscriptions,
                Permission::ManageInvitations,
            ],
            self::PlatformSupport => [
                Permission::AccessPlatform,
                Permission::ViewTenants,
            ],
            self::Owner => [
                Permission::AccessAdminPanel,
                Permission::AccessPos,
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
                Permission::AccessPos,
                Permission::ViewBusinesses,
                Permission::ViewBranches,
                Permission::ViewTerminals,
                Permission::CreateTerminals,
                Permission::UpdateTerminals,
                Permission::ViewUsers,
            ],
            self::Supervisor => [
                Permission::AccessAdminPanel,
                Permission::AccessPos,
                Permission::ViewBranches,
                Permission::ViewTerminals,
            ],
            self::Cashier, self::Waiter => [
                Permission::AccessPos,
            ],
            default => [],
        };
    }
}
