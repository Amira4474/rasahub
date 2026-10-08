<?php

namespace Tests\Feature\Registration;

use App\Enums\AuditEvent;
use App\Enums\InvitationStatus;
use App\Enums\Plan;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Tenancy\TenantDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private TenantInvitation $invitation;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->invitation = TenantInvitation::factory()->create(['email' => 'owner@example.com', 'plan' => Plan::Growth]);
        $this->url = route('register.show', ['token' => $this->invitation->issueToken()]);
        $this->invitation->save();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return [
            'business_name' => 'Kopi Kita Bersama',
            'acronym' => 'kkb',
            'name' => 'Aisyah Owner',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            ...$overrides,
        ];
    }

    public function test_there_is_no_open_sign_up(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/register/not-a-real-token')->assertStatus(410)->assertSee('no longer valid');
        $this->post('/register/not-a-real-token', $this->details())->assertStatus(410);

        $this->assertSame(0, Business::count());
    }

    public function test_the_registration_page_shows_who_and_what_the_invitation_is_for(): void
    {
        $this->get($this->url)
            ->assertOk()
            ->assertSee('owner@example.com')
            ->assertSee('Growth')
            ->assertSee('Company acronym');
    }

    public function test_registering_creates_the_tenant_its_database_its_subscription_and_its_owner(): void
    {
        $this->post($this->url, $this->details())->assertRedirect('/admin');

        $business = Business::sole();
        $owner = User::acrossBusinesses()->where('email', 'owner@example.com')->sole();

        $this->assertSame('Kopi Kita Bersama', $business->name);
        $this->assertSame('KKB', $business->acronym);
        $this->assertSame('kopi-kita-bersama', $business->slug);

        $this->assertTrue(app(TenantDatabase::class)->exists($business));
        $this->assertSame(0, $business->run(fn (): int => Branch::count()));

        $this->assertSame(Plan::Growth, $business->subscription->plan);
        $this->assertSame(SubscriptionStatus::Active, $business->subscription->status());
        $this->assertTrue($business->subscription->ends_at->isSameDay(today()->addMonthNoOverflow()));

        $this->assertSame($business->id, $owner->business_id);
        $this->assertSame('Aisyah Owner', $owner->name);
        $this->assertTrue($owner->hasRole(Role::Owner));
        $this->assertNotNull($owner->email_verified_at);

        $this->invitation->refresh();
        $this->assertSame(InvitationStatus::Accepted, $this->invitation->status());
        $this->assertSame($business->id, $this->invitation->business_id);

        $audit = AuditLog::where('event', AuditEvent::TenantRegistered)->sole();
        $this->assertSame($business->id, $audit->business_id);
        $this->assertSame($owner->id, $audit->user_id);

        // They are signed in and land in their own admin pages.
        $this->assertAuthenticatedAs($owner);
        $this->get('/admin')->assertOk();
        $this->get('/admin/branches')->assertOk();
    }

    public function test_the_link_works_only_once(): void
    {
        $this->post($this->url, $this->details())->assertRedirect('/admin');

        $this->post('/pos/logout');

        $this->get($this->url)->assertStatus(410);
        $this->post($this->url, $this->details(['business_name' => 'Second Go', 'acronym' => 'SG']))->assertStatus(410);

        $this->assertSame(1, Business::count());
    }

    public function test_an_expired_invitation_cannot_be_used(): void
    {
        $this->invitation->update(['expires_at' => now()->subMinute()]);

        $this->get($this->url)->assertStatus(410);
        $this->post($this->url, $this->details())->assertStatus(410);

        $this->assertSame(0, Business::count());
    }

    public function test_the_details_are_validated(): void
    {
        Business::factory()->create(['acronym' => 'TAKEN']);

        $this->post($this->url, $this->details(['acronym' => 'taken']))->assertSessionHasErrors('acronym');
        $this->post($this->url, $this->details(['acronym' => 'A']))->assertSessionHasErrors('acronym');
        $this->post($this->url, $this->details(['acronym' => 'K K-B']))->assertSessionHasErrors('acronym');
        $this->post($this->url, $this->details(['business_name' => '']))->assertSessionHasErrors('business_name');
        $this->post($this->url, $this->details(['password_confirmation' => 'something-else']))->assertSessionHasErrors('password');
        $this->post($this->url, $this->details(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');

        $this->assertSame(1, Business::count());
        $this->assertSame(InvitationStatus::Pending, $this->invitation->refresh()->status());
    }

    public function test_the_email_comes_from_the_invitation_and_cannot_be_chosen(): void
    {
        $this->post($this->url, $this->details(['email' => 'someone-else@example.com']))->assertRedirect('/admin');

        $this->assertSame(['owner@example.com'], User::acrossBusinesses()->whereNotNull('business_id')->pluck('email')->all());
    }

    public function test_an_email_that_has_gained_an_account_since_cannot_register(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'business_id' => Business::factory()->create()->id]);

        $this->post($this->url, $this->details())->assertSessionHasErrors('business_name');

        $this->assertSame(1, Business::count());
        $this->assertSame(InvitationStatus::Pending, $this->invitation->refresh()->status());
    }

    public function test_two_businesses_with_the_same_name_get_different_slugs(): void
    {
        Business::factory()->create(['name' => 'Kopi Kita Bersama', 'slug' => 'kopi-kita-bersama']);

        $this->post($this->url, $this->details())->assertRedirect('/admin');

        $this->assertSame('kopi-kita-bersama-2', Business::where('acronym', 'KKB')->sole()->slug);
    }

    public function test_a_platform_user_trying_the_link_is_signed_out_and_the_owner_signed_in(): void
    {
        $this->actingAs($this->platformUser());

        $this->post($this->url, $this->details())->assertRedirect('/admin');

        $owner = User::acrossBusinesses()->where('email', 'owner@example.com')->sole();

        $this->assertAuthenticatedAs($owner);
        $this->assertSame(Business::sole()->id, $owner->business_id);
    }
}
