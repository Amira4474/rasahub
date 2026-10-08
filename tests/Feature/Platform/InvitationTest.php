<?php

namespace Tests\Feature\Platform;

use App\Enums\AuditEvent;
use App\Enums\InvitationStatus;
use App\Enums\Plan;
use App\Enums\Role;
use App\Filament\Platform\Resources\Invitations\Pages\CreateInvitation;
use App\Filament\Platform\Resources\Invitations\Pages\ViewInvitation;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\TenantInvitation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Filament::setCurrentPanel(Filament::getPanel('platform'));
    }

    public function test_only_some_platform_roles_may_manage_invitations(): void
    {
        $this->actingAs($this->platformUser(Role::PlatformSupport));
        $this->get('/platform/invitations')->assertForbidden();
        $this->get('/platform/invitations/create')->assertForbidden();

        $this->actingAs($this->platformUser(Role::PlatformAdmin));
        $this->get('/platform/invitations')->assertOk();
        $this->get('/platform/invitations/create')->assertOk();
    }

    public function test_creating_an_invitation_shows_a_working_link_once(): void
    {
        $platformUser = $this->platformUser(Role::PlatformAdmin);
        $this->actingAs($platformUser);

        Livewire::test(CreateInvitation::class)
            ->fillForm(['email' => 'Owner@Example.com', 'plan' => Plan::Growth->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $invitation = TenantInvitation::sole();

        $this->assertSame('owner@example.com', $invitation->email);
        $this->assertSame(Plan::Growth, $invitation->plan);
        $this->assertSame($platformUser->id, $invitation->created_by);
        $this->assertSame(InvitationStatus::Pending, $invitation->status());
        $this->assertTrue($invitation->expires_at->isSameDay(now()->addDays(7)));
        $this->assertSame('owner@example.com', AuditLog::where('event', AuditEvent::InvitationCreated)->sole()->properties['email']);

        // The link is handed to the next page, and only its hash is kept.
        $link = session(ViewInvitation::LINK_SESSION_KEY);
        $token = basename($link);

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertTrue(TenantInvitation::findByToken($token)->is($invitation));

        Livewire::test(ViewInvitation::class, ['record' => $invitation->id])->assertSee($link);

        session()->forget(ViewInvitation::LINK_SESSION_KEY);

        Livewire::test(ViewInvitation::class, ['record' => $invitation->id])->assertDontSee($token);
    }

    public function test_someone_who_already_has_an_account_cannot_be_invited(): void
    {
        $existing = $this->userWithRole(Role::Owner, Business::factory()->create());
        $this->actingAs($this->platformUser());

        Livewire::test(CreateInvitation::class)
            ->fillForm(['email' => $existing->email, 'plan' => Plan::Starter->value])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame(0, TenantInvitation::count());
    }

    public function test_an_invitation_can_be_revoked(): void
    {
        $invitation = TenantInvitation::factory()->create();
        $token = $invitation->issueToken();
        $invitation->save();

        $this->actingAs($this->platformUser(Role::PlatformAdmin));

        Livewire::test(ViewInvitation::class, ['record' => $invitation->id])->callAction('revoke');

        $this->assertSame(InvitationStatus::Revoked, $invitation->refresh()->status());
        $this->assertSame(1, AuditLog::where('event', AuditEvent::InvitationRevoked)->count());
        $this->get(route('register.show', ['token' => $token]))->assertStatus(410);

        // Nothing more can be done with it.
        Livewire::test(ViewInvitation::class, ['record' => $invitation->id])
            ->assertActionHidden('revoke')
            ->assertActionHidden('reissue');
    }

    public function test_a_new_link_replaces_the_old_one_and_revives_an_expired_invitation(): void
    {
        $invitation = TenantInvitation::factory()->create(['expires_at' => now()->subDay()]);
        $oldToken = $invitation->issueToken();
        $invitation->save();

        $this->assertSame(InvitationStatus::Expired, $invitation->status());

        $this->actingAs($this->platformUser());

        $page = Livewire::test(ViewInvitation::class, ['record' => $invitation->id])
            ->assertActionHidden('revoke')
            ->callAction('reissue');

        $newToken = basename($page->get('link'));

        $this->assertSame(InvitationStatus::Pending, $invitation->refresh()->status());
        $this->assertNull(TenantInvitation::findByToken($oldToken));
        $this->assertTrue(TenantInvitation::findByToken($newToken)->is($invitation));
    }
}
