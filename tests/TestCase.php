<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles and permissions whenever a test refreshes the database.
     */
    protected bool $seed = true;

    /**
     * Create a user with a role, optionally in a business and assigned to branches.
     *
     * @param  list<Branch>  $branches
     */
    protected function userWithRole(Role $role, ?Business $business = null, array $branches = []): User
    {
        $user = User::factory()->create(['business_id' => $business?->getKey()]);
        $user->assignRole($role);
        $user->branches()->attach(array_map(fn (Branch $branch): int => $branch->getKey(), $branches));

        return $user;
    }
}
