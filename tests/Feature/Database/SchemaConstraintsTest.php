<?php

namespace Tests\Feature\Database;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_businesses_branches_and_terminals_get_a_ulid_and_keep_integer_keys(): void
    {
        $business = Business::factory()->create();
        $terminal = $this->terminalIn($business);

        foreach ([$business, $terminal, $business->run(fn () => $terminal->branch)] as $model) {
            $this->assertIsInt($model->getKey());
            $this->assertTrue(Str::isUlid($model->ulid));
        }
    }

    public function test_branch_codes_are_unique_within_a_business_only(): void
    {
        $business = Business::factory()->create();
        $this->branchIn($business, ['code' => 'HQ']);

        // The same code in another business is fine.
        $this->branchIn(Business::factory()->create(), ['code' => 'HQ']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->branchIn($business, ['code' => 'HQ']);
    }

    public function test_terminal_codes_are_unique_within_a_branch_only(): void
    {
        $business = Business::factory()->create();
        $branch = $this->branchIn($business);
        $this->terminalIn($business, $branch, ['code' => 'POS-1']);

        // The same code in another branch is fine.
        $this->terminalIn($business, attributes: ['code' => 'POS-1']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->terminalIn($business, $branch, ['code' => 'POS-1']);
    }

    public function test_a_business_cannot_be_hard_deleted_while_its_database_exists(): void
    {
        $business = Business::factory()->create();

        $this->expectException(LogicException::class);
        $business->forceDelete();
    }

    public function test_deleting_a_business_soft_deletes_it_and_keeps_its_data(): void
    {
        $business = Business::factory()->create();
        $this->branchIn($business);

        $business->delete();

        $this->assertSoftDeleted($business);
        $this->assertSame(1, $business->run(fn (): int => Branch::count()));
    }

    public function test_branch_assignments_are_removed_with_the_user(): void
    {
        $business = Business::factory()->create();
        $branch = $this->branchIn($business);
        $user = User::factory()->create(['business_id' => $business->id]);
        $business->run(fn () => $user->branches()->attach($branch));

        $user->delete();

        $this->assertSame(0, $business->run(fn (): int => $branch->newQuery()->getConnection()->table('branch_user')->count()));
    }

    public function test_a_user_cannot_be_assigned_to_the_same_branch_twice(): void
    {
        $business = Business::factory()->create();
        $branch = $this->branchIn($business);
        $user = User::factory()->create(['business_id' => $business->id]);
        $business->run(fn () => $user->branches()->attach($branch));

        $this->expectException(UniqueConstraintViolationException::class);
        $business->run(fn () => $user->branches()->attach($branch));
    }

    public function test_a_terminal_belongs_to_a_branch_in_the_same_database(): void
    {
        $business = Business::factory()->create();
        $terminal = $this->terminalIn($business);

        $this->assertSame(1, $business->run(fn (): int => Terminal::whereKey($terminal->id)->whereHas('branch')->count()));
    }
}
