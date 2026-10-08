<?php

namespace Tests\Feature\Database;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_businesses_branches_and_terminals_get_a_ulid_and_keep_integer_keys(): void
    {
        $terminal = Terminal::factory()->create();

        foreach ([$terminal, $terminal->branch, $terminal->branch->business] as $model) {
            $this->assertIsInt($model->getKey());
            $this->assertTrue(Str::isUlid($model->ulid));
        }
    }

    public function test_branch_codes_are_unique_within_a_business_only(): void
    {
        $business = Business::factory()->create();
        Branch::factory()->for($business)->create(['code' => 'HQ']);

        // The same code in another business is fine.
        Branch::factory()->create(['code' => 'HQ']);

        $this->expectException(UniqueConstraintViolationException::class);
        Branch::factory()->for($business)->create(['code' => 'HQ']);
    }

    public function test_terminal_codes_are_unique_within_a_branch_only(): void
    {
        $branch = Branch::factory()->create();
        Terminal::factory()->for($branch)->create(['code' => 'POS-1']);

        // The same code in another branch is fine.
        Terminal::factory()->create(['code' => 'POS-1']);

        $this->expectException(UniqueConstraintViolationException::class);
        Terminal::factory()->for($branch)->create(['code' => 'POS-1']);
    }

    public function test_a_business_with_branches_cannot_be_hard_deleted(): void
    {
        $branch = Branch::factory()->create();

        $this->expectException(QueryException::class);
        $branch->business->forceDelete();
    }

    public function test_deleting_a_business_soft_deletes_it(): void
    {
        $business = Business::factory()->create();
        $business->delete();

        $this->assertSoftDeleted($business);
    }

    public function test_branch_assignments_are_removed_with_the_user(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['business_id' => $branch->business_id]);
        $user->branches()->attach($branch);

        $user->delete();

        $this->assertDatabaseCount('branch_user', 0);
    }

    public function test_a_user_cannot_be_assigned_to_the_same_branch_twice(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['business_id' => $branch->business_id]);
        $user->branches()->attach($branch);

        $this->expectException(UniqueConstraintViolationException::class);
        $user->branches()->attach($branch);
    }
}
