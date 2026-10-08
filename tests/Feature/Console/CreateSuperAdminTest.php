<?php

namespace Tests\Feature\Console;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_platform_user_with_the_super_admin_role(): void
    {
        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Name', 'Root')
            ->expectsQuestion('Email address', 'root@example.com')
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertSuccessful();

        $user = User::where('email', 'root@example.com')->sole();

        $this->assertTrue($user->hasRole(Role::PlatformSuperAdmin));
        $this->assertTrue($user->isPlatformUser());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }
}
