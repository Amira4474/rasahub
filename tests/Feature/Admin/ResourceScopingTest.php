<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Filament\Resources\Terminals\Pages\CreateTerminal;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class ResourceScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_only_lists_branches_of_their_own_business(): void
    {
        $business = Business::factory()->create();
        $mine = Branch::factory()->for($business)->create();
        $theirs = Branch::factory()->create();

        $this->actingAs($this->userWithRole(Role::Owner, $business));

        Livewire::test(ListBranches::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_manager_only_lists_assigned_branches(): void
    {
        $business = Business::factory()->create();
        $assigned = Branch::factory()->for($business)->create();
        $unassigned = Branch::factory()->for($business)->create();

        $this->actingAs($this->userWithRole(Role::Manager, $business, [$assigned]));

        Livewire::test(ListBranches::class)
            ->assertCanSeeTableRecords([$assigned])
            ->assertCanNotSeeTableRecords([$unassigned]);
    }

    public function test_a_branch_created_by_an_owner_lands_in_their_business(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($this->userWithRole(Role::Owner, $business));

        Livewire::test(CreateBranch::class)
            ->fillForm(['name' => 'Downtown', 'code' => 'DT-01'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('branches', ['code' => 'DT-01', 'business_id' => $business->id]);
    }

    public function test_an_owner_cannot_open_another_business_branch(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, Business::factory()->create()));

        $this->get('/admin/branches/'.Branch::factory()->create()->id.'/edit')->assertNotFound();
    }

    public function test_a_manager_cannot_create_a_terminal_in_an_unassigned_branch(): void
    {
        $business = Business::factory()->create();
        $assigned = Branch::factory()->for($business)->create();
        $unassigned = Branch::factory()->for($business)->create();

        $this->actingAs($this->userWithRole(Role::Manager, $business, [$assigned]));

        Livewire::test(CreateTerminal::class)
            ->fillForm(['branch_id' => $unassigned->id, 'name' => 'Counter 1', 'code' => 'POS-1'])
            ->call('create')
            ->assertHasFormErrors(['branch_id']);

        $this->assertSame(0, Terminal::count());

        Livewire::test(CreateTerminal::class)
            ->fillForm(['branch_id' => $assigned->id, 'name' => 'Counter 1', 'code' => 'POS-1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($assigned->id, Terminal::sole()->branch_id);
    }

    public function test_an_owner_cannot_create_a_terminal_in_another_business(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, Business::factory()->create()));

        Livewire::test(CreateTerminal::class)
            ->fillForm(['branch_id' => Branch::factory()->create()->id, 'name' => 'Counter 1', 'code' => 'POS-1'])
            ->call('create')
            ->assertHasFormErrors(['branch_id']);

        $this->assertSame(0, Terminal::count());
    }

    public function test_an_owner_cannot_grant_the_super_admin_role(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($this->userWithRole(Role::Owner, $business));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Mallory',
                'email' => 'mallory@example.com',
                'password' => 'correct-horse-battery',
                'roles' => [RoleModel::findByName(Role::SuperAdmin->value)->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles.0']);

        $this->assertDatabaseMissing('users', ['email' => 'mallory@example.com']);
    }

    public function test_a_user_created_by_an_owner_lands_in_their_business(): void
    {
        $business = Business::factory()->create();
        $branch = Branch::factory()->for($business)->create();
        $this->actingAs($this->userWithRole(Role::Owner, $business));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Casey Cashier',
                'email' => 'casey@example.com',
                'password' => 'correct-horse-battery',
                'roles' => [RoleModel::findByName(Role::Cashier->value)->id],
                'branches' => [$branch->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'casey@example.com')->sole();

        $this->assertSame($business->id, $created->business_id);
        $this->assertTrue($created->hasRole(Role::Cashier));
        $this->assertTrue($created->canAccessBranch($branch));
    }

    public function test_an_owner_cannot_assign_a_user_to_another_business_branch(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, Business::factory()->create()));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Casey Cashier',
                'email' => 'casey@example.com',
                'password' => 'correct-horse-battery',
                'branches' => [Branch::factory()->create()->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['branches.0']);

        $this->assertDatabaseMissing('users', ['email' => 'casey@example.com']);
    }

    public function test_editing_a_user_without_a_password_keeps_the_current_one(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($this->userWithRole(Role::Owner, $business));
        $staff = $this->userWithRole(Role::Cashier, $business);
        $originalHash = $staff->password;

        Livewire::test(EditUser::class, ['record' => $staff->id])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', $staff->refresh()->name);
        $this->assertSame($originalHash, $staff->password);
    }
}
