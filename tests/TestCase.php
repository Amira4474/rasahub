<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Terminal;
use App\Models\User;
use App\Tenancy\SuperImpose;
use App\Tenancy\TenantDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles and permissions are part of the application's foundation, so
     * every test that touches the database starts with them in place.
     */
    protected bool $seed = true;

    private static bool $staleTenantDatabasesRemoved = false;

    /** @var list<int> */
    private array $businessesCreated = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->removeStaleTenantDatabases();

        Business::created(function (Business $business): void {
            $this->businessesCreated[] = $business->getKey();
        });
    }

    /**
     * Every business gets a real database of its own, which the test's
     * transaction on the central database does not undo.
     */
    protected function tearDown(): void
    {
        foreach ($this->businessesCreated as $businessId) {
            app(TenantDatabase::class)->drop($businessId);
        }

        $this->businessesCreated = [];

        DB::disconnect('tenant');

        parent::tearDown();
    }

    /**
     * Tenant databases left behind by a run that was interrupted would
     * collide with the ones this run creates.
     */
    private function removeStaleTenantDatabases(): void
    {
        if (self::$staleTenantDatabasesRemoved) {
            return;
        }

        self::$staleTenantDatabasesRemoved = true;

        $prefix = config('tenancy.database_prefix');

        if (! str_contains($prefix, 'testing')) {
            throw new RuntimeException("Refusing to remove tenant databases with the prefix [{$prefix}]: it is not a testing prefix.");
        }

        config(['database.connections.tenant.database' => null]);
        DB::purge('tenant');

        $pattern = str_replace('_', '\_', $prefix).'%';

        foreach (DB::connection('tenant')->select("SHOW DATABASES LIKE '{$pattern}'") as $row) {
            $name = array_values((array) $row)[0];

            DB::connection('tenant')->statement("DROP DATABASE `{$name}`");
        }

        DB::purge('tenant');
    }

    /**
     * Create a branch in the given business's own database.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function branchIn(Business $business, array $attributes = []): Branch
    {
        return $business->run(fn (): Branch => Branch::factory()->create($attributes));
    }

    /**
     * Create a POS terminal in the given business, in a new branch unless
     * one is given.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function terminalIn(Business $business, ?Branch $branch = null, array $attributes = []): Terminal
    {
        return $business->run(fn (): Terminal => Terminal::factory()->create([
            ...$attributes,
            'branch_id' => $branch?->getKey() ?? Branch::factory(),
        ]));
    }

    /**
     * Create a tenant user with a business role, optionally assigned to branches.
     *
     * @param  list<Branch>  $branches
     */
    protected function userWithRole(Role $role, Business $business, array $branches = []): User
    {
        $user = $business->run(fn (): User => User::factory()->create(['business_id' => $business->getKey()]));
        $user->assignRole($role);

        $business->run(fn () => $user->branches()->attach(array_map(fn (Branch $branch): int => $branch->getKey(), $branches)));

        return $user;
    }

    /**
     * Create a platform user: one who belongs to no business.
     */
    protected function platformUser(Role $role = Role::PlatformSuperAdmin): User
    {
        $user = User::factory()->create(['business_id' => null]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Sign in as the platform user and enter the given business.
     */
    protected function superImpose(User $platformUser, Business $business): void
    {
        $this->actingAs($platformUser);

        app(SuperImpose::class)->start($business);
    }
}
