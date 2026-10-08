<?php

namespace Tests\Feature\Platform;

use App\Enums\Plan;
use App\Enums\Role;
use App\Filament\Platform\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Platform\Resources\Tenants\Pages\ViewTenant;
use App\Models\Branch;
use App\Models\Business;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabase;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Filament::setCurrentPanel(Filament::getPanel('platform'));
    }

    public function test_the_tenant_page_shows_its_business_information_subscription_and_data(): void
    {
        $business = Business::factory()->create(['name' => 'Kopi Kita', 'acronym' => 'KKB', 'currency' => 'MYR', 'timezone' => 'Asia/Kuala_Lumpur']);
        $branch = $this->branchIn($business);
        $this->terminalIn($business, $branch);
        $this->terminalIn($business, $branch);
        $this->userWithRole(Role::Owner, $business);

        // Another tenant's data must not be counted.
        $this->branchIn(Business::factory()->create());

        $this->actingAs($this->platformUser(Role::PlatformSupport));

        Livewire::test(ViewTenant::class, ['record' => $business->id])
            ->assertSee('Kopi Kita')
            ->assertSee('KKB')
            ->assertSee('Asia/Kuala_Lumpur')
            ->assertSee('Starter')
            ->assertSee('Active')
            ->assertSee(app(TenantDatabase::class)->nameFor($business))
            ->assertSchemaStateSet(['branches' => 1, 'terminals' => 2, 'staff' => 1]);

        $this->get("/platform/tenants/{$business->id}")->assertOk();
    }

    public function test_super_impose_can_be_started_from_the_tenant_page(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($this->platformUser(Role::PlatformAdmin));

        Livewire::test(ViewTenant::class, ['record' => $business->id])
            ->callAction('superImpose', ['reason' => 'Support call'])
            ->assertRedirect('/admin');

        $this->assertTrue(app(TenantContext::class)->business()->is($business));
    }

    public function test_platform_support_is_not_offered_super_impose_on_the_tenant_page(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($this->platformUser(Role::PlatformSupport));

        Livewire::test(ViewTenant::class, ['record' => $business->id])->assertActionHidden('superImpose');
    }

    public function test_a_tenant_created_in_the_platform_gets_a_database_and_a_subscription(): void
    {
        $this->actingAs($this->platformUser(Role::PlatformAdmin));

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Nasi House',
                'slug' => 'nasi-house',
                'acronym' => 'NH',
                'currency' => 'MYR',
                'timezone' => 'Asia/Kuala_Lumpur',
                'subscription.plan' => Plan::Growth->value,
                'subscription.starts_at' => '2026-10-01',
                'subscription.ends_at' => '2027-10-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $business = Business::where('acronym', 'NH')->sole();

        $this->assertSame(Plan::Growth, $business->subscription->plan);
        $this->assertSame('2027-10-01', $business->subscription->ends_at->toDateString());
        $this->assertTrue(app(TenantDatabase::class)->exists($business));
        $this->assertSame(0, $business->run(fn (): int => Branch::count()));
    }
}
