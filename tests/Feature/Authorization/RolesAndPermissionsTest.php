<?php

namespace Tests\Feature\Authorization;

use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\RoleScopeMismatchException;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_and_permission_is_seeded(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(Role::cases(), 'value'),
            RoleModel::pluck('name')->all(),
        );

        $this->assertEqualsCanonicalizing(
            array_column(Permission::cases(), 'value'),
            PermissionModel::pluck('name')->all(),
        );
    }

    public function test_the_seeder_can_be_run_repeatedly_and_removes_what_is_no_longer_defined(): void
    {
        RoleModel::create(['name' => 'Retired role']);
        PermissionModel::create(['name' => 'retired.permission']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(Role::cases()), RoleModel::count());
        $this->assertSame(count(Permission::cases()), PermissionModel::count());
    }

    public function test_platform_roles_hold_only_platform_permissions_and_business_roles_only_tenant_ones(): void
    {
        foreach (Role::cases() as $role) {
            foreach ($role->permissions() as $permission) {
                $this->assertSame($role->isPlatform(), $permission->isPlatform(), "{$role->value} holds {$permission->value}");
            }
        }
    }

    public function test_a_platform_role_cannot_be_given_to_a_tenant_user(): void
    {
        $user = User::factory()->create(['business_id' => Business::factory()->create()->id]);

        $this->expectException(RoleScopeMismatchException::class);

        $user->assignRole(Role::PlatformSuperAdmin);
    }

    public function test_a_business_role_cannot_be_given_to_a_platform_user(): void
    {
        $user = User::factory()->create(['business_id' => null]);

        $this->expectException(RoleScopeMismatchException::class);

        $user->syncRoles([Role::Owner->value]);
    }

    public function test_a_platform_super_admin_holds_no_tenant_permission_outside_super_impose(): void
    {
        $business = Business::factory()->create();
        $superAdmin = $this->platformUser();
        $this->actingAs($superAdmin);

        $this->assertTrue($superAdmin->can(Permission::ManageTenants));
        $this->assertFalse($superAdmin->can(Permission::ViewBranches));
        $this->assertFalse($superAdmin->can(Permission::AccessAdminPanel));

        $this->superImpose($superAdmin, $business);

        $this->assertTrue($superAdmin->can(Permission::ViewBranches));
        $this->assertTrue($superAdmin->can(Permission::DeleteUsers));
    }

    public function test_a_tenant_user_never_passes_a_platform_check(): void
    {
        $owner = $this->userWithRole(Role::Owner, Business::factory()->create());
        // Even with the permission somehow attached directly.
        $owner->givePermissionTo(Permission::AccessPlatform);

        $this->assertFalse($owner->can(Permission::AccessPlatform));
        $this->assertFalse($owner->can('create', Business::class));
    }

    public function test_an_owner_manages_users_only_inside_their_business(): void
    {
        $business = Business::factory()->create();
        $owner = $this->userWithRole(Role::Owner, $business);
        $colleague = User::factory()->create(['business_id' => $business->id]);
        $outsider = User::factory()->create(['business_id' => Business::factory()->create()->id]);
        $platformUser = $this->platformUser();

        $this->assertTrue($owner->can('update', $colleague));
        $this->assertFalse($owner->can('update', $outsider));
        $this->assertFalse($owner->can('update', $platformUser));
        $this->assertFalse($owner->can('delete', $owner));
    }

    public function test_only_a_platform_super_admin_manages_platform_users(): void
    {
        $target = $this->platformUser(Role::PlatformSupport);

        $this->assertTrue($this->platformUser(Role::PlatformSuperAdmin)->can('update', $target));
        $this->assertFalse($this->platformUser(Role::PlatformAdmin)->can('update', $target));
    }

    public function test_an_owner_can_update_but_not_create_or_delete_businesses(): void
    {
        $business = Business::factory()->create();
        $owner = $this->userWithRole(Role::Owner, $business);

        $this->assertTrue($owner->can('update', $business));
        $this->assertFalse($owner->can('update', Business::factory()->create()));
        $this->assertFalse($owner->can('create', Business::class));
        $this->assertFalse($owner->can('delete', $business));
    }

    public function test_platform_admins_manage_tenants_and_support_only_views_them(): void
    {
        $business = Business::factory()->create();
        $admin = $this->platformUser(Role::PlatformAdmin);
        $support = $this->platformUser(Role::PlatformSupport);

        $this->assertTrue($admin->can('create', Business::class));
        $this->assertTrue($admin->can('update', $business));
        $this->assertTrue($support->can('view', $business));
        $this->assertFalse($support->can('update', $business));
    }

    public function test_front_of_house_roles_reach_the_pos_but_not_the_back_office(): void
    {
        $business = Business::factory()->create();

        foreach ([Role::Cashier, Role::Waiter] as $role) {
            $user = $this->userWithRole($role, $business);

            $this->assertTrue($user->can(Permission::AccessPos));
            $this->assertFalse($user->can(Permission::AccessAdminPanel));
        }

        foreach ([Role::Kitchen, Role::Inventory] as $role) {
            $user = $this->userWithRole($role, $business);

            $this->assertFalse($user->can(Permission::AccessPos));
            $this->assertFalse($user->can(Permission::AccessAdminPanel));
        }
    }
}
