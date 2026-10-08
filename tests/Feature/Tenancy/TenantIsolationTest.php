<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\NoTenantException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Terminal;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * These tests query the models directly, with no visibleTo() and no policy,
 * to prove the business boundary holds even when nobody remembers to ask.
 *
 * Branches and terminals are kept apart by living in separate databases.
 * Users are in the central database and are kept apart by business_id.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Business $mine;

    private Business $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = Business::factory()->create();
        $this->theirs = Business::factory()->create();

        $this->terminalIn($this->mine, $this->branchIn($this->mine, ['name' => 'Mine']));
        $this->terminalIn($this->theirs, $this->branchIn($this->theirs, ['name' => 'Theirs']));
        $this->branchIn($this->theirs, ['name' => 'Theirs too']);

        foreach ([$this->mine, $this->theirs] as $business) {
            User::factory()->count(2)->create(['business_id' => $business->id]);
        }
    }

    public function test_every_business_has_a_database_of_its_own(): void
    {
        $databases = app(TenantDatabase::class);

        $this->assertNotSame($databases->nameFor($this->mine), $databases->nameFor($this->theirs));
        $this->assertTrue($databases->exists($this->mine));
        $this->assertTrue($databases->exists($this->theirs));

        // Nothing of a tenant's is left in the central database.
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('branches'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('terminals'));
    }

    public function test_a_tenant_user_only_ever_reads_their_own_business(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, $this->mine));

        $this->assertEquals(['Mine'], Branch::pluck('name')->all());
        $this->assertSame(1, Terminal::count());
        $this->assertEquals([$this->mine->id], User::pluck('business_id')->unique()->all());

        // The other business has a branch 2. This one does not.
        $this->assertNull(Branch::find(2));
    }

    public function test_a_platform_user_reaches_no_tenant_data_from_the_platform(): void
    {
        $this->actingAs($this->platformUser());

        $this->assertSame(0, User::count());
        $this->assertSame(4, User::acrossBusinesses()->whereNotNull('business_id')->count());

        $this->expectException(NoTenantException::class);

        Branch::count();
    }

    public function test_code_with_nobody_signed_in_reaches_no_tenant_database(): void
    {
        $this->expectException(NoTenantException::class);

        Branch::count();
    }

    public function test_code_can_deliberately_act_in_one_business(): void
    {
        $context = app(TenantContext::class);

        $this->assertEquals(['Theirs', 'Theirs too'], $context->for($this->theirs, fn () => Branch::orderBy('id')->pluck('name')->all()));
        $this->assertEquals(['Mine'], $this->mine->run(fn () => Branch::pluck('name')->all()));
        $this->assertFalse($context->isScoped());
    }

    public function test_a_query_built_inside_a_business_cannot_run_after_leaving_it(): void
    {
        $query = $this->theirs->run(fn () => Branch::query());

        $this->expectException(\Throwable::class);

        $query->count();
    }

    public function test_new_records_are_placed_in_the_current_business(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, $this->mine));

        $branch = Branch::create(['name' => 'Uptown', 'code' => 'UP']);
        $user = User::create(['name' => 'New', 'email' => 'new@example.com', 'password' => 'correct-horse-battery']);

        $this->assertSame($this->mine->id, $branch->tenantBusinessId());
        $this->assertSame($this->mine->id, $user->business_id);
        $this->assertEquals(['Theirs', 'Theirs too'], $this->theirs->run(fn () => Branch::orderBy('id')->pluck('name')->all()));
    }

    public function test_a_user_cannot_be_created_in_another_business(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, $this->mine));

        $this->expectException(CrossTenantWriteException::class);

        User::create(['business_id' => $this->theirs->id, 'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'correct-horse-battery']);
    }

    public function test_a_user_cannot_be_moved_to_another_business(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, $this->mine));

        $this->expectException(CrossTenantWriteException::class);

        User::firstOrFail()->update(['business_id' => $this->theirs->id]);
    }

    public function test_a_record_loaded_in_one_business_cannot_be_read_in_another(): void
    {
        $theirBranch = $this->theirs->run(fn () => Branch::firstOrFail());

        $this->actingAs($this->userWithRole(Role::Owner, $this->mine));

        $this->expectException(CrossTenantWriteException::class);

        $theirBranch->terminals()->count();
    }

    public function test_a_record_loaded_in_one_business_cannot_be_saved_in_another(): void
    {
        $theirBranch = $this->theirs->run(fn () => Branch::firstOrFail());

        $this->actingAs($this->userWithRole(Role::Owner, $this->mine));

        try {
            $theirBranch->update(['name' => 'Taken over']);
            $this->fail('The branch was saved into another business.');
        } catch (CrossTenantWriteException) {
            $this->assertEquals(['Mine'], Branch::pluck('name')->all());
            $this->assertContains('Theirs', $this->theirs->run(fn () => Branch::pluck('name')->all()));
        }
    }

    public function test_a_record_loaded_in_one_business_cannot_be_deleted_in_another(): void
    {
        $theirBranch = $this->theirs->run(fn () => Branch::where('name', 'Theirs too')->firstOrFail());

        $this->actingAs($this->userWithRole(Role::Owner, $this->mine));

        $this->expectException(CrossTenantWriteException::class);

        $theirBranch->delete();
    }
}
