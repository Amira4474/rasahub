<?php

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_only_access_branches_they_are_assigned_to(): void
    {
        $business = Business::factory()->create();
        $assigned = Branch::factory()->for($business)->create();
        $unassigned = Branch::factory()->for($business)->create();

        $cashier = $this->userWithRole(Role::Cashier, $business, [$assigned]);

        $this->assertTrue($cashier->canAccessBranch($assigned));
        $this->assertFalse($cashier->canAccessBranch($unassigned));
    }

    public function test_an_owner_can_access_every_branch_of_their_business_without_assignment(): void
    {
        $business = Business::factory()->create();
        $branch = Branch::factory()->for($business)->create();

        $this->assertTrue($this->userWithRole(Role::Owner, $business)->canAccessBranch($branch));
    }

    public function test_nobody_can_access_a_branch_of_another_business(): void
    {
        $otherBranch = Branch::factory()->create();
        $business = Business::factory()->create();

        $this->assertFalse($this->userWithRole(Role::Owner, $business)->canAccessBranch($otherBranch));
        $this->assertFalse($this->userWithRole(Role::Manager, $business)->canAccessBranch($otherBranch));
    }

    public function test_a_super_admin_can_access_any_branch(): void
    {
        $this->assertTrue($this->userWithRole(Role::SuperAdmin)->canAccessBranch(Branch::factory()->create()));
    }

    public function test_the_same_role_does_not_grant_access_to_an_unassigned_branch(): void
    {
        $business = Business::factory()->create();
        $assigned = Branch::factory()->for($business)->create();
        $unassigned = Branch::factory()->for($business)->create();
        $manager = $this->userWithRole(Role::Manager, $business, [$assigned]);

        $this->assertTrue($manager->can('view', $assigned));
        $this->assertFalse($manager->can('view', $unassigned));
    }

    public function test_terminal_access_follows_branch_access(): void
    {
        $business = Business::factory()->create();
        $assigned = Branch::factory()->for($business)->create();
        $unassigned = Branch::factory()->for($business)->create();
        $manager = $this->userWithRole(Role::Manager, $business, [$assigned]);

        $reachable = Terminal::factory()->for($assigned)->create();
        $unreachable = Terminal::factory()->for($unassigned)->create();

        $this->assertTrue($manager->can('update', $reachable));
        $this->assertFalse($manager->can('update', $unreachable));
        $this->assertEquals([$reachable->id], Terminal::query()->visibleTo($manager)->pluck('id')->all());
    }

    public function test_visible_branches_are_scoped_by_business_and_assignment(): void
    {
        $business = Business::factory()->create();
        [$first, $second] = Branch::factory()->count(2)->for($business)->create()->all();
        Branch::factory()->create();

        $owner = $this->userWithRole(Role::Owner, $business);
        $supervisor = $this->userWithRole(Role::Supervisor, $business, [$second]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], Branch::query()->visibleTo($owner)->pluck('id')->all());
        $this->assertEquals([$second->id], Branch::query()->visibleTo($supervisor)->pluck('id')->all());
        $this->assertSame(3, Branch::query()->visibleTo($this->userWithRole(Role::SuperAdmin))->count());
    }
}
