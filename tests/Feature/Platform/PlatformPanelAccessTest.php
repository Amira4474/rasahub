<?php

namespace Tests\Feature\Platform;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Filament\Platform\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Models\AuditLog;
use App\Models\Business;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_platform_login_page(): void
    {
        $this->get('/platform')->assertRedirect('/platform/login');
        $this->get('/platform/login')->assertOk();
    }

    public function test_every_platform_role_can_open_the_panel(): void
    {
        foreach ([Role::PlatformSuperAdmin, Role::PlatformAdmin, Role::PlatformSupport] as $role) {
            $this->actingAs($this->platformUser($role))->get('/platform')->assertOk();
        }
    }

    public function test_tenant_users_cannot_open_the_panel_whatever_their_role(): void
    {
        $business = Business::factory()->create();

        foreach ([Role::Owner, Role::Manager, Role::Cashier] as $role) {
            $this->actingAs($this->userWithRole($role, $business))->get('/platform')->assertForbidden();
        }
    }

    public function test_an_inactive_platform_user_cannot_open_the_panel(): void
    {
        $platformUser = $this->platformUser();
        $platformUser->update(['is_active' => false]);

        $this->actingAs($platformUser)->get('/platform')->assertForbidden();
    }

    public function test_a_platform_user_can_sign_in_at_the_platform_door_and_it_is_audited(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $platformUser = $this->platformUser();

        Livewire::test(Login::class)
            ->fillForm(['email' => $platformUser->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($platformUser);
        $this->assertSame($platformUser->id, AuditLog::where('event', AuditEvent::PlatformUserSignedIn)->sole()->user_id);
    }

    public function test_an_owner_cannot_sign_in_at_the_platform_door(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $owner = $this->userWithRole(Role::Owner, Business::factory()->create());

        Livewire::test(Login::class)
            ->fillForm(['email' => $owner->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_the_tenant_list_shows_every_business_with_its_subscription_and_users(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $first = Business::factory()->create();
        $second = Business::factory()->suspended()->create();
        $this->userWithRole(Role::Owner, $first);
        $this->userWithRole(Role::Cashier, $first);

        $this->actingAs($this->platformUser(Role::PlatformSupport));

        Livewire::test(ListTenants::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->assertTableColumnStateSet('users_count', 2, $first)
            ->assertTableColumnStateSet('users_count', 0, $second)
            ->assertTableColumnStateSet('subscription_status', 'Active', $first)
            ->assertTableColumnStateSet('subscription_status', 'Suspended', $second);
    }

    public function test_only_some_platform_roles_may_create_tenants_or_manage_platform_users(): void
    {
        $this->actingAs($this->platformUser(Role::PlatformSupport));
        $this->get('/platform/tenants')->assertOk();
        $this->get('/platform/tenants/create')->assertForbidden();
        $this->get('/platform/audit-log')->assertForbidden();
        $this->get('/platform/platform-users')->assertForbidden();

        $this->actingAs($this->platformUser(Role::PlatformAdmin));
        $this->get('/platform/tenants/create')->assertOk();
        $this->get('/platform/audit-log')->assertOk();
        $this->get('/platform/platform-users')->assertForbidden();

        $this->actingAs($this->platformUser(Role::PlatformSuperAdmin));
        $this->get('/platform/platform-users')->assertOk();
    }

    public function test_a_new_tenant_gets_its_acronym_in_capitals(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($this->platformUser(Role::PlatformAdmin));

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Kopi Kita Bersama',
                'slug' => 'kopi-kita-bersama',
                'acronym' => 'kkb',
                'currency' => 'myr',
                'timezone' => 'Asia/Kuala_Lumpur',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('businesses', ['acronym' => 'KKB', 'currency' => 'MYR']);
    }
}
