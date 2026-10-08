<?php

namespace Tests\Feature\Platform;

use App\Enums\AuditEvent;
use App\Enums\Plan;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Filament\Pages\Auth\Login;
use App\Filament\Platform\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Platform\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\AuditLog;
use App\Models\Business;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_status_follows_the_dates_and_suspension(): void
    {
        $subscription = Business::factory()->create()->subscription;

        $this->assertSame(SubscriptionStatus::Active, $subscription->status());

        // The end date is the last valid day.
        $subscription->update(['ends_at' => today()]);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status());

        $subscription->update(['ends_at' => today()->subDay()]);
        $this->assertSame(SubscriptionStatus::Expired, $subscription->status());

        $subscription->suspend('Unpaid');
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->status());

        $subscription->resume();
        $this->assertSame(SubscriptionStatus::Expired, $subscription->status());
    }

    public function test_extending_adds_a_month_to_a_running_subscription_and_restarts_an_ended_one(): void
    {
        $running = Business::factory()->create()->subscription;
        $running->update(['ends_at' => today()->addDays(10)]);
        $running->extendByOneMonth();

        $this->assertTrue($running->ends_at->isSameDay(today()->addDays(10)->addMonthNoOverflow()));

        $ended = Business::factory()->expired()->create()->subscription;
        $ended->extendByOneMonth();

        $this->assertTrue($ended->ends_at->isSameDay(today()->addMonthNoOverflow()));
        $this->assertSame(SubscriptionStatus::Active, $ended->status());
    }

    public function test_the_subscriptions_page_lists_every_tenant_with_plan_status_and_dates(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $active = Business::factory()->create()->subscription;
        $expired = Business::factory()->expired()->create()->subscription;
        $suspended = Business::factory()->suspended()->create()->subscription;

        $this->actingAs($this->platformUser(Role::PlatformSupport));

        Livewire::test(ListSubscriptions::class)
            ->assertCanSeeTableRecords([$active, $expired, $suspended])
            ->assertTableColumnStateSet('status', 'Active', $active)
            ->assertTableColumnStateSet('status', 'Expired', $expired)
            ->assertTableColumnStateSet('status', 'Suspended', $suspended)
            ->assertTableColumnExists('plan')
            ->assertTableColumnExists('starts_at')
            ->assertTableColumnExists('ends_at')
            ->filterTable('status', SubscriptionStatus::Expired->value)
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$active, $suspended]);
    }

    public function test_platform_support_can_look_but_not_change(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $subscription = Business::factory()->create()->subscription;

        $this->actingAs($this->platformUser(Role::PlatformSupport));

        $this->get('/platform/subscriptions')->assertOk();
        $this->get("/platform/subscriptions/{$subscription->id}/edit")->assertForbidden();

        Livewire::test(ListSubscriptions::class)
            ->assertTableActionHidden('extend', $subscription)
            ->assertTableActionHidden('suspend', $subscription);
    }

    public function test_a_subscription_can_be_suspended_and_resumed(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $business = Business::factory()->create();
        $subscription = $business->subscription;

        $this->actingAs($this->platformUser(Role::PlatformAdmin));

        Livewire::test(ListSubscriptions::class)
            ->assertTableActionHidden('resume', $subscription)
            ->callTableAction('suspend', $subscription, ['reason' => 'Unpaid invoice']);

        $this->assertSame(SubscriptionStatus::Suspended, $subscription->refresh()->status());
        $this->assertSame('Unpaid invoice', $subscription->suspension_reason);
        $this->assertSame($business->id, AuditLog::where('event', AuditEvent::SubscriptionSuspended)->sole()->business_id);

        Livewire::test(ListSubscriptions::class)
            ->assertTableActionHidden('suspend', $subscription)
            ->callTableAction('resume', $subscription);

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status());
        $this->assertNull($subscription->suspension_reason);
        $this->assertSame(1, AuditLog::where('event', AuditEvent::SubscriptionResumed)->count());
    }

    public function test_a_subscription_can_be_extended_by_one_month(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $subscription = Business::factory()->create()->subscription;
        $endedOn = $subscription->ends_at->copy();

        $this->actingAs($this->platformUser());

        Livewire::test(ListSubscriptions::class)->callTableAction('extend', $subscription);

        $this->assertTrue($subscription->refresh()->ends_at->isSameDay($endedOn->addMonthNoOverflow()));

        $audit = AuditLog::where('event', AuditEvent::SubscriptionExtended)->sole();
        $this->assertSame($subscription->ends_at->toDateString(), $audit->properties['to']);
    }

    public function test_a_subscription_can_be_edited(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $subscription = Business::factory()->create()->subscription;

        $this->actingAs($this->platformUser(Role::PlatformAdmin));

        Livewire::test(EditSubscription::class, ['record' => $subscription->id])
            ->fillForm(['plan' => Plan::Enterprise->value, 'starts_at' => '2026-01-01', 'ends_at' => '2027-01-01'])
            ->call('save')
            ->assertHasNoFormErrors();

        $subscription->refresh();

        $this->assertSame(Plan::Enterprise, $subscription->plan);
        $this->assertSame('2027-01-01', $subscription->ends_at->toDateString());
        $this->assertSame(1, AuditLog::where('event', AuditEvent::SubscriptionUpdated)->count());

        Livewire::test(EditSubscription::class, ['record' => $subscription->id])
            ->fillForm(['starts_at' => '2026-06-01', 'ends_at' => '2026-05-01'])
            ->call('save')
            ->assertHasFormErrors(['ends_at']);
    }

    public function test_staff_of_a_suspended_or_expired_tenant_cannot_open_the_admin_pages_or_the_pos(): void
    {
        foreach (['suspended', 'expired', 'withoutSubscription'] as $state) {
            $business = Business::factory()->{$state}()->create();
            $owner = $this->userWithRole(Role::Owner, $business);

            $this->assertFalse($owner->canAccessPanel(Filament::getPanel('admin')), $state);
            $this->assertFalse($owner->canAccessPos(), $state);

            // Someone already signed in is signed out and sent to sign in.
            $this->actingAs($owner)->get('/admin')->assertRedirect('/admin/login');
            $this->assertGuest();

            $this->actingAs($owner)->get('/pos')->assertRedirect('/pos/login')->assertSessionHasErrors('acronym');
            $this->assertGuest();
        }
    }

    public function test_the_admin_sign_in_page_says_why_a_suspended_tenant_cannot_come_in(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $owner = $this->userWithRole(Role::Owner, Business::factory()->suspended()->create());

        Livewire::test(Login::class)
            ->fillForm(['email' => $owner->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => 'This company\'s subscription is suspended. Please contact RasaHub support.']);

        $this->assertGuest();

        // A wrong password reveals nothing about the subscription.
        Livewire::test(Login::class)
            ->fillForm(['email' => $owner->email, 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => __('filament-panels::auth/pages/login.messages.failed')]);
    }

    public function test_the_pos_sign_in_says_why_an_expired_tenant_cannot_come_in(): void
    {
        $business = Business::factory()->expired()->create(['acronym' => 'KKB']);
        $cashier = $this->userWithRole(Role::Cashier, $business);
        $kitchen = $this->userWithRole(Role::Kitchen, $business);

        $this->post('/pos/login', ['acronym' => 'KKB']);

        $this->post('/pos/login/credentials', ['email' => $cashier->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => $business->accessProblem()]);
        $this->assertStringContainsString('subscription ended on', $business->accessProblem());
        $this->assertGuest();

        // Wrong password, or someone who could not use the POS anyway:
        // the usual message, with nothing about the subscription.
        $this->post('/pos/login/credentials', ['email' => $cashier->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->post('/pos/login/credentials', ['email' => $kitchen->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_extending_an_expired_subscription_lets_the_tenant_back_in(): void
    {
        $business = Business::factory()->expired()->create();
        $owner = $this->userWithRole(Role::Owner, $business);

        $business->subscription->extendByOneMonth();

        $this->actingAs($owner->fresh())->get('/admin')->assertOk();
    }

    public function test_super_impose_still_works_for_a_suspended_tenant(): void
    {
        $business = Business::factory()->suspended()->create();

        $this->superImpose($this->platformUser(), $business);

        $this->get('/admin')->assertOk()->assertSee('SUPER IMPOSE MODE');
    }
}
