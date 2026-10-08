<?php

namespace Tests\Feature\Authorization;

use App\Enums\Permission;
use App\Enums\Role;
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

    public function test_the_seeder_can_be_run_repeatedly(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(Role::cases()), RoleModel::count());
        $this->assertSame(count(Permission::cases()), PermissionModel::count());
    }

    public function test_a_super_admin_passes_every_check_without_explicit_permissions(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin);

        $this->assertCount(0, $superAdmin->getAllPermissions());
        $this->assertTrue($superAdmin->can(Permission::DeleteBusinesses));
        $this->assertTrue($superAdmin->can('delete', Business::factory()->create()));
    }

    public function test_an_owner_manages_users_only_inside_their_business(): void
    {
        $business = Business::factory()->create();
        $owner = $this->userWithRole(Role::Owner, $business);

        $colleague = User::factory()->create(['business_id' => $business->id]);
        $outsider = User::factory()->create(['business_id' => Business::factory()->create()->id]);
        $superAdmin = $this->userWithRole(Role::SuperAdmin);

        $this->assertTrue($owner->can('update', $colleague));
        $this->assertFalse($owner->can('update', $outsider));
        $this->assertFalse($owner->can('update', $superAdmin));
        $this->assertFalse($owner->can('delete', $owner));
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

    public function test_front_of_house_roles_have_no_back_office_permissions_yet(): void
    {
        foreach ([Role::Cashier, Role::Waiter, Role::Kitchen, Role::Inventory] as $role) {
            $this->assertFalse($this->userWithRole($role, Business::factory()->create())->can(Permission::AccessAdminPanel));
        }
    }
}
