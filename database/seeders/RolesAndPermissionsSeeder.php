<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Sync roles and permissions with the App\Enums definitions.
     * Safe to run repeatedly.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value);
        }

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value)->syncPermissions($role->permissions());
        }

        // Remove whatever is no longer defined in the enums.
        PermissionModel::whereNotIn('name', array_column(Permission::cases(), 'value'))->delete();
        RoleModel::whereNotIn('name', array_column(Role::cases(), 'value'))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
