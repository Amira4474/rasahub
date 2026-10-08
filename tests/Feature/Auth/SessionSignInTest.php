<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * These tests sign in the way a browser does: only the user's ID is in the
 * session, and the user is loaded from the database on the next request.
 * actingAs() skips that lookup, so it cannot catch a broken one.
 */
class SessionSignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_user_stays_signed_in_on_the_next_request(): void
    {
        $platformUser = $this->platformUser();

        $this->withSignedInSession($platformUser)->get('/platform')->assertOk();
    }

    public function test_a_platform_user_in_super_impose_stays_signed_in_on_the_next_request(): void
    {
        $business = Business::factory()->create();
        $platformUser = $this->platformUser();

        $this->withSignedInSession($platformUser, [
            'super_impose' => ['business_id' => $business->id, 'started_at' => now()->toIso8601String()],
        ])->get('/admin')->assertOk()->assertSee('SUPER IMPOSE MODE');
    }

    public function test_a_tenant_user_stays_signed_in_on_the_next_request(): void
    {
        $business = Business::factory()->create();
        $owner = $this->userWithRole(Role::Owner, $business);
        $cashier = $this->userWithRole(Role::Cashier, $business);

        $this->withSignedInSession($owner)->get('/admin')->assertOk();

        Auth::forgetGuards();

        $this->withSignedInSession($cashier)->get('/pos')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function withSignedInSession(User $user, array $session = []): static
    {
        return $this->withSession([Auth::guard('web')->getName() => $user->getKey(), ...$session]);
    }
}
