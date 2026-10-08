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
        $assigned = $this->branchIn($business);
        $unassigned = $this->branchIn($business);

        $cashier = $this->userWithRole(Role::Cashier, $business, [$assigned]);
        $this->actingAs($cashier);

        $this->assertTrue($cashier->canAccessBranch($assigned));
        $this->assertFalse($cashier->canAccessBranch($unassigned));
    }

    public function test_an_owner_can_access_every_branch_of_their_business_without_assignment(): void
    {
        $business = Business::factory()->create();
        $branch = $this->branchIn($business);
        $owner = $this->userWithRole(Role::Owner, $business);

        $this->actingAs($owner);

        $this->assertTrue($owner->canAccessBranch($branch));
    }

    public function test_nobody_can_access_a_branch_of_another_business(): void
    {
        $otherBranch = $this->branchIn(Business::factory()->create());
        $business = Business::factory()->create();
        // Same ID as the other business's branch, in this business's database.
        $this->branchIn($business);

        foreach ([Role::Owner, Role::Manager] as $role) {
            $user = $this->userWithRole($role, $business);
            $this->actingAs($user);

            $this->assertFalse($user->canAccessBranch($otherBranch));
            $this->assertFalse($user->can('view', $otherBranch));
        }
    }

    public function test_a_platform_user_cannot_access_a_branch_from_the_platform(): void
    {
        $branch = $this->branchIn(Business::factory()->create());
        $platformUser = $this->platformUser();
        $this->actingAs($platformUser);

        $this->assertFalse($platformUser->canAccessBranch($branch));
    }

    public function test_the_same_role_does_not_grant_access_to_an_unassigned_branch(): void
    {
        $business = Business::factory()->create();
        $assigned = $this->branchIn($business);
        $unassigned = $this->branchIn($business);
        $manager = $this->userWithRole(Role::Manager, $business, [$assigned]);

        $this->actingAs($manager);

        $this->assertTrue($manager->can('view', $assigned));
        $this->assertFalse($manager->can('view', $unassigned));
    }

    public function test_terminal_access_follows_branch_access(): void
    {
        $business = Business::factory()->create();
        $assigned = $this->branchIn($business);
        $unassigned = $this->branchIn($business);
        $manager = $this->userWithRole(Role::Manager, $business, [$assigned]);

        $reachable = $this->terminalIn($business, $assigned);
        $unreachable = $this->terminalIn($business, $unassigned);

        $this->actingAs($manager);

        $this->assertTrue($manager->can('update', $reachable->fresh()));
        $this->assertFalse($manager->can('update', $unreachable->fresh()));
        $this->assertEquals([$reachable->id], Terminal::query()->visibleTo($manager)->pluck('id')->all());
    }

    public function test_visible_branches_are_limited_by_assignment(): void
    {
        $business = Business::factory()->create();
        $first = $this->branchIn($business);
        $second = $this->branchIn($business);

        $owner = $this->userWithRole(Role::Owner, $business);
        $supervisor = $this->userWithRole(Role::Supervisor, $business, [$second]);

        $this->actingAs($owner);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], Branch::query()->visibleTo($owner)->pluck('id')->all());

        $this->actingAs($supervisor);
        $this->assertEquals([$second->id], Branch::query()->visibleTo($supervisor)->pluck('id')->all());
    }

    public function test_visible_branches_are_empty_for_someone_acting_in_another_business(): void
    {
        $business = Business::factory()->create();
        $this->branchIn($business);

        $outsider = $this->userWithRole(Role::Owner, Business::factory()->create());
        $platformUser = $this->platformUser();

        $this->actingAs($this->userWithRole(Role::Owner, $business));

        $this->assertSame(0, Branch::query()->visibleTo($outsider)->count());
        $this->assertSame(0, Branch::query()->visibleTo($platformUser)->count());
    }
}
