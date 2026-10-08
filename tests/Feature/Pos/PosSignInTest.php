<?php

namespace Tests\Feature\Pos;

use App\Enums\Role;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSignInTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->business = Business::factory()->create(['name' => 'Kopi Kita', 'acronym' => 'KKB']);
    }

    public function test_guests_are_sent_to_the_pos_sign_in_page(): void
    {
        $this->get('/pos')->assertRedirect('/pos/login');
        $this->get('/pos/login')->assertOk()->assertSee('Company acronym');
    }

    public function test_step_one_accepts_the_company_acronym_in_any_case(): void
    {
        $this->post('/pos/login', ['acronym' => ' kkb '])->assertRedirect('/pos/login/credentials');

        $this->get('/pos/login/credentials')->assertOk()->assertSee('Kopi Kita');
    }

    public function test_step_one_rejects_an_unknown_acronym(): void
    {
        $this->from('/pos/login')
            ->post('/pos/login', ['acronym' => 'NOPE'])
            ->assertRedirect('/pos/login')
            ->assertSessionHasErrors('acronym');
    }

    public function test_step_one_rejects_an_inactive_company(): void
    {
        $this->business->update(['is_active' => false]);

        $this->post('/pos/login', ['acronym' => 'KKB'])->assertSessionHasErrors('acronym');
    }

    public function test_step_two_cannot_be_reached_without_choosing_a_company(): void
    {
        $this->get('/pos/login/credentials')->assertRedirect('/pos/login');
        $this->post('/pos/login/credentials', ['email' => 'a@example.com', 'password' => 'password'])->assertRedirect('/pos/login');
    }

    public function test_a_cashier_signs_in_and_sees_where_they_can_work(): void
    {
        $assigned = $this->branchIn($this->business, ['name' => 'Bangsar']);
        $this->branchIn($this->business, ['name' => 'Cheras']);
        $this->terminalIn($this->business, $assigned, ['name' => 'Counter 1']);
        $cashier = $this->userWithRole(Role::Cashier, $this->business, [$assigned]);

        $this->signIn('KKB', $cashier->email)->assertRedirect('/pos');

        $this->assertAuthenticatedAs($cashier);
        $this->get('/pos')
            ->assertOk()
            ->assertSee($cashier->name)
            ->assertSee('Bangsar')
            ->assertSee('Counter 1')
            ->assertDontSee('Cheras');
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $cashier = $this->userWithRole(Role::Cashier, $this->business);

        $this->signIn('KKB', $cashier->email, 'wrong-password')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_staff_of_another_company_are_rejected_with_the_same_message(): void
    {
        $outsider = $this->userWithRole(Role::Cashier, Business::factory()->create());

        $this->signIn('KKB', $outsider->email)->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_platform_users_are_rejected(): void
    {
        $this->signIn('KKB', $this->platformUser()->email)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_staff_are_rejected(): void
    {
        $cashier = $this->userWithRole(Role::Cashier, $this->business);
        $cashier->update(['is_active' => false]);

        $this->signIn('KKB', $cashier->email)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_roles_without_pos_access_are_rejected(): void
    {
        $kitchen = $this->userWithRole(Role::Kitchen, $this->business);

        $this->signIn('KKB', $kitchen->email)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_someone_already_signed_in_elsewhere_can_still_sign_in_here(): void
    {
        $cashier = $this->userWithRole(Role::Cashier, $this->business);
        $this->actingAs($this->userWithRole(Role::Kitchen, Business::factory()->create()));

        $this->signIn('KKB', $cashier->email)->assertRedirect('/pos');

        $this->assertAuthenticatedAs($cashier);
    }

    public function test_the_pos_is_closed_to_signed_in_users_who_may_not_use_it(): void
    {
        $kitchen = $this->userWithRole(Role::Kitchen, $this->business);
        $platformUser = $this->platformUser();

        $this->actingAs($kitchen)->get('/pos')->assertForbidden();
        $this->actingAs($platformUser)->get('/pos')->assertForbidden();
    }

    public function test_a_platform_user_in_super_impose_still_cannot_enter_the_pos(): void
    {
        $this->superImpose($this->platformUser(), $this->business);

        $this->get('/pos')->assertForbidden();
    }

    public function test_signing_out_returns_to_the_pos_sign_in_page(): void
    {
        $this->actingAs($this->userWithRole(Role::Cashier, $this->business));

        $this->post('/pos/logout')->assertRedirect('/pos/login');

        $this->assertGuest();
    }

    private function signIn(string $acronym, string $email, string $password = 'password')
    {
        $this->post('/pos/login', ['acronym' => $acronym]);

        return $this->post('/pos/login/credentials', ['email' => $email, 'password' => $password]);
    }
}
