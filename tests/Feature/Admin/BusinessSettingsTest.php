<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\BusinessSettings;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_change_their_own_business(): void
    {
        $business = Business::factory()->create(['acronym' => 'KKB']);
        $other = Business::factory()->create(['name' => 'Untouched']);
        $this->actingAs($this->userWithRole(Role::Owner, $business));

        Livewire::test(BusinessSettings::class)
            ->assertFormSet(['name' => $business->name, 'acronym' => 'KKB'])
            ->fillForm(['name' => 'Kopi Kita', 'currency' => 'sgd', 'acronym' => 'HACKED'])
            ->call('save')
            ->assertHasNoFormErrors();

        $business->refresh();
        $this->assertSame('Kopi Kita', $business->name);
        $this->assertSame('SGD', $business->currency);
        // The acronym belongs to the platform and cannot be changed here.
        $this->assertSame('KKB', $business->acronym);
        $this->assertSame('Untouched', $other->refresh()->name);
    }

    public function test_a_manager_can_look_but_not_save(): void
    {
        $business = Business::factory()->create(['name' => 'Original']);
        $this->actingAs($this->userWithRole(Role::Manager, $business));

        $this->get('/admin/business-settings')->assertOk();

        Livewire::test(BusinessSettings::class)
            ->set('data.name', 'Changed')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('Original', $business->refresh()->name);
    }

    public function test_a_supervisor_cannot_open_the_page(): void
    {
        $this->actingAs($this->userWithRole(Role::Supervisor, Business::factory()->create()))
            ->get('/admin/business-settings')
            ->assertForbidden();
    }

    public function test_under_super_impose_it_edits_the_entered_business(): void
    {
        $business = Business::factory()->create();
        $this->superImpose($this->platformUser(), $business);

        Livewire::test(BusinessSettings::class)
            ->fillForm(['name' => 'Renamed by platform'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed by platform', $business->refresh()->name);
    }
}
