<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_login_page_loads(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_a_super_admin_can_open_the_panel(): void
    {
        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->get('/admin')
            ->assertOk();
    }

    public function test_an_owner_can_open_the_panel(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, Business::factory()->create()))
            ->get('/admin')
            ->assertOk();
    }

    public function test_a_cashier_cannot_open_the_panel(): void
    {
        $this->actingAs($this->userWithRole(Role::Cashier, Business::factory()->create()))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_an_inactive_user_cannot_open_the_panel(): void
    {
        $owner = $this->userWithRole(Role::Owner, Business::factory()->create());
        $owner->update(['is_active' => false]);

        $this->actingAs($owner)->get('/admin')->assertForbidden();
    }

    public function test_a_user_without_a_role_cannot_open_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
