<?php

namespace Tests\Feature\Platform;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\NoTenantException;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Tenancy\SuperImpose;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class SuperImposeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_puts_the_platform_user_inside_one_business_without_changing_who_they_are(): void
    {
        $business = Business::factory()->create();
        $platformUser = $this->platformUser();

        $this->superImpose($platformUser, $business);

        $this->assertTrue(app(SuperImpose::class)->isActive());
        $this->assertTrue(app(TenantContext::class)->business()->is($business));
        $this->assertAuthenticatedAs($platformUser);
        $this->assertNull($platformUser->refresh()->business_id);
        $this->assertTrue($platformUser->isPlatformUser());
    }

    public function test_platform_support_cannot_start_it(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($this->platformUser(Role::PlatformSupport));

        $this->expectException(AuthorizationException::class);

        app(SuperImpose::class)->start($business);
    }

    public function test_a_tenant_user_cannot_start_it_even_for_their_own_business(): void
    {
        $business = Business::factory()->create();
        $owner = $this->userWithRole(Role::Owner, $business);
        // Even with the permission somehow attached directly.
        $owner->givePermissionTo(Permission::StartSuperImpose);
        $this->actingAs($owner);

        $this->expectException(AuthorizationException::class);

        app(SuperImpose::class)->start(Business::factory()->create());
    }

    public function test_a_deleted_business_cannot_be_entered(): void
    {
        $business = Business::factory()->create();
        $business->delete();
        $this->actingAs($this->platformUser());

        $this->expectException(AuthorizationException::class);

        app(SuperImpose::class)->start($business);
    }

    public function test_the_tenant_panel_opens_with_a_banner_naming_the_business(): void
    {
        $business = Business::factory()->create(['name' => 'Kopi Kita']);

        $this->superImpose($this->platformUser(), $business);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('SUPER IMPOSE MODE')
            ->assertSee('Kopi Kita')
            ->assertSee('Exit Super Impose');
    }

    public function test_the_banner_is_not_shown_to_ordinary_tenant_users(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner, Business::factory()->create()))
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('SUPER IMPOSE MODE');
    }

    public function test_it_gives_full_access_to_every_tenant_admin_page(): void
    {
        $business = Business::factory()->create();
        $branch = $this->branchIn($business);

        $this->superImpose($this->platformUser(Role::PlatformAdmin), $business);

        foreach (['/admin', '/admin/business-settings', '/admin/branches', '/admin/branches/create', "/admin/branches/{$branch->id}/edit", '/admin/terminals', '/admin/terminals/create', '/admin/users', '/admin/users/create'] as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_only_the_entered_business_is_reachable(): void
    {
        $entered = Business::factory()->create();
        $other = Business::factory()->create();
        $mine = $this->branchIn($entered, ['name' => 'Mine']);
        // Each business numbers its own branches from 1, so the other one
        // gets a second branch whose ID the entered business does not have.
        $this->branchIn($other, ['name' => 'Theirs']);
        $theirs = $this->branchIn($other, ['name' => 'Theirs too']);
        $platformUser = $this->platformUser();

        $this->superImpose($platformUser, $entered);

        $this->assertEquals(['Mine'], Branch::pluck('name')->all());
        $this->assertTrue($platformUser->can('update', $mine));
        $this->assertFalse($platformUser->can('update', $theirs));
        $this->get("/admin/branches/{$theirs->id}/edit")->assertNotFound();

        Livewire::test(ListBranches::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCountTableRecords(1)
            ->assertDontSee('Theirs');
    }

    public function test_what_is_created_lands_in_the_entered_business(): void
    {
        $business = Business::factory()->create();
        $other = Business::factory()->create();

        $this->superImpose($this->platformUser(), $business);

        Livewire::test(CreateBranch::class)
            ->fillForm(['name' => 'Downtown', 'code' => 'DT-01'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['DT-01'], Branch::pluck('code')->all());
        $this->assertSame(0, $other->run(fn (): int => Branch::count()));
    }

    public function test_the_platform_panel_is_closed_until_exit(): void
    {
        $this->superImpose($this->platformUser(), Business::factory()->create());

        $this->get('/platform')->assertRedirect('/admin');
        $this->get('/platform/tenants')->assertRedirect('/admin');
    }

    public function test_exiting_returns_to_the_platform_and_closes_the_tenant(): void
    {
        $business = Business::factory()->create();
        $this->branchIn($business);
        $platformUser = $this->platformUser();
        $this->superImpose($platformUser, $business);

        $this->post(route('super-impose.exit'))->assertRedirect('/platform');

        $this->assertFalse(app(SuperImpose::class)->isActive());
        $this->assertNull(app(TenantContext::class)->businessId());
        $this->assertAuthenticatedAs($platformUser);
        $this->assertThrows(fn () => Branch::count(), NoTenantException::class);
        $this->assertFalse($platformUser->can(Permission::ViewBranches));
        $this->get('/admin')->assertRedirect('/platform');
        $this->get('/platform')->assertOk();
    }

    public function test_it_does_not_expire(): void
    {
        $business = Business::factory()->create();
        $this->superImpose($this->platformUser(), $business);

        $this->travel(30)->days();

        $this->assertTrue(app(SuperImpose::class)->isActive());
    }

    public function test_starting_and_exiting_are_audited_with_the_real_identity(): void
    {
        $business = Business::factory()->create();
        $platformUser = $this->platformUser();
        $this->actingAs($platformUser);

        app(SuperImpose::class)->start($business, 'Fixing a broken branch code');
        app(SuperImpose::class)->exit();

        $started = AuditLog::where('event', AuditEvent::SuperImposeStarted)->sole();
        $this->assertSame($platformUser->id, $started->user_id);
        $this->assertSame($business->id, $started->business_id);
        $this->assertTrue($started->super_impose);
        $this->assertSame('Fixing a broken branch code', $started->properties['reason']);

        $exited = AuditLog::where('event', AuditEvent::SuperImposeExited)->sole();
        $this->assertSame($platformUser->id, $exited->user_id);
        $this->assertSame($business->id, $exited->business_id);
    }

    public function test_signing_out_ends_it_and_that_is_audited(): void
    {
        $business = Business::factory()->create();
        $platformUser = $this->platformUser();
        $this->superImpose($platformUser, $business);

        Auth::logout();

        $this->assertSame($platformUser->id, AuditLog::where('event', AuditEvent::SuperImposeExited)->sole()->user_id);

        // Signing back in on the same session does not resume it.
        $this->actingAs($platformUser);
        $this->assertFalse(app(SuperImpose::class)->isActive());
    }

    public function test_it_ends_if_the_right_to_use_it_is_taken_away(): void
    {
        $business = Business::factory()->create();
        $this->branchIn($business);
        $platformUser = $this->platformUser(Role::PlatformAdmin);
        $this->superImpose($platformUser, $business);

        $platformUser->syncRoles(Role::PlatformSupport);

        $this->assertFalse(app(SuperImpose::class)->isActive());
        $this->assertThrows(fn () => Branch::count(), NoTenantException::class);
        $this->get('/admin')->assertRedirect('/platform');
    }

    public function test_it_can_be_started_from_the_tenant_list(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $business = Business::factory()->create();
        $this->actingAs($this->platformUser());

        Livewire::test(ListTenants::class)
            ->callTableAction('superImpose', $business, ['reason' => 'Onboarding'])
            ->assertRedirect('/admin');

        $this->assertTrue(app(TenantContext::class)->business()->is($business));
    }

    public function test_platform_support_is_not_offered_it(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $business = Business::factory()->create();
        $this->actingAs($this->platformUser(Role::PlatformSupport));

        Livewire::test(ListTenants::class)->assertTableActionHidden('superImpose', $business);
    }
}
