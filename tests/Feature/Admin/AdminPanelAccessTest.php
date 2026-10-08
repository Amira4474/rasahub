<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Auth\Login;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_nobody_in_an_inactive_business_can_open_the_panel(): void
    {
        $owner = $this->userWithRole(Role::Owner, Business::factory()->create(['is_active' => false]));

        // They are signed out and sent to the sign-in page, where they are
        // told why.
        $this->actingAs($owner)->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_a_user_without_a_role_cannot_open_the_panel(): void
    {
        $user = User::factory()->create(['business_id' => Business::factory()->create()->id]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_a_platform_user_is_sent_to_the_platform_panel_instead(): void
    {
        $this->actingAs($this->platformUser())
            ->get('/admin')
            ->assertRedirect('/platform');
    }

    public function test_an_owner_can_sign_in_at_the_admin_door(): void
    {
        $owner = $this->userWithRole(Role::Owner, Business::factory()->create());

        Livewire::test(Login::class)
            ->fillForm(['email' => $owner->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($owner);
    }

    public function test_a_platform_user_cannot_sign_in_at_the_admin_door(): void
    {
        $platformUser = $this->platformUser();

        // The same error as a wrong password, so the door reveals nothing.
        Livewire::test(Login::class)
            ->fillForm(['email' => $platformUser->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }
}
