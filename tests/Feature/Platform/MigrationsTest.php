<?php

namespace Tests\Feature\Platform;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Filament\Platform\Pages\Migrations;
use App\Models\AuditLog;
use App\Models\Business;
use App\Tenancy\CentralDatabase;
use App\Tenancy\TenantDatabase;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // A copy of the tenant migrations that a new one can be added to,
        // to stand in for an update to RasaHub.
        $this->path = storage_path('framework/testing/tenant-migrations-'.uniqid());

        File::copyDirectory(config('tenancy.migrations_path'), $this->path);

        config(['tenancy.migrations_path' => $this->path]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function releaseAnUpdate(): void
    {
        File::put($this->path.'/2030_01_01_000000_create_menus_table.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('menus', function (Blueprint $table) {
                        $table->id();
                    });
                }
            };
            PHP);
    }

    private function hasMenusTable(Business $business): bool
    {
        return $business->run(fn (): bool => Schema::connection(TenantDatabase::CONNECTION)->hasTable('menus'));
    }

    public function test_a_new_tenant_starts_fully_migrated(): void
    {
        $business = Business::factory()->create();

        $this->assertSame([], app(TenantDatabase::class)->pendingMigrations($business));
        $this->assertSame([], app(CentralDatabase::class)->pendingMigrations());
    }

    public function test_an_update_is_pending_for_each_tenant_until_it_is_migrated(): void
    {
        $databases = app(TenantDatabase::class);
        $first = Business::factory()->create();
        $second = Business::factory()->create();

        $this->releaseAnUpdate();

        $this->assertSame(['2030_01_01_000000_create_menus_table'], $databases->pendingMigrations($first));

        $this->assertSame(['2030_01_01_000000_create_menus_table'], $databases->migrate($first));

        $this->assertSame([], $databases->pendingMigrations($first));
        $this->assertTrue($this->hasMenusTable($first));

        // The other tenant is untouched until it is migrated too.
        $this->assertCount(1, $databases->pendingMigrations($second));
        $this->assertFalse($this->hasMenusTable($second));
    }

    public function test_the_command_migrates_every_tenant_or_just_one(): void
    {
        $first = Business::factory()->create(['acronym' => 'ONE']);
        $second = Business::factory()->create(['acronym' => 'TWO']);

        $this->releaseAnUpdate();

        $this->artisan('tenants:migrate', ['acronym' => 'one'])->assertSuccessful();

        $this->assertTrue($this->hasMenusTable($first));
        $this->assertFalse($this->hasMenusTable($second));

        $this->artisan('tenants:migrate')->assertSuccessful();

        $this->assertTrue($this->hasMenusTable($second));

        $this->artisan('tenants:migrate', ['acronym' => 'NOPE'])->assertFailed();
    }

    public function test_only_a_platform_super_admin_can_open_the_migrations_page(): void
    {
        Business::factory()->create();

        $this->actingAs($this->platformUser(Role::PlatformAdmin));
        $this->get('/platform/migrations')->assertForbidden();

        $this->actingAs($this->platformUser(Role::PlatformSuperAdmin));
        $this->get('/platform/migrations')->assertOk()->assertSee('Central database')->assertSee('Up to date');
    }

    public function test_the_migrations_page_shows_what_is_pending_and_migrates_a_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $first = Business::factory()->create();
        $second = Business::factory()->create();

        $this->releaseAnUpdate();

        $this->actingAs($this->platformUser());

        Livewire::test(Migrations::class)
            ->assertTableColumnStateSet('pending', '1 pending', $first)
            ->callTableAction('migrate', $first)
            ->assertTableColumnStateSet('pending', 'Up to date', $first)
            ->assertTableActionHidden('migrate', $first)
            ->assertTableColumnStateSet('pending', '1 pending', $second);

        $this->assertTrue($this->hasMenusTable($first));
        $this->assertFalse($this->hasMenusTable($second));

        $audit = AuditLog::where('event', AuditEvent::MigrationsRun)->sole();
        $this->assertSame($first->id, $audit->business_id);
        $this->assertSame(['2030_01_01_000000_create_menus_table'], $audit->properties['migrations']);
    }

    public function test_the_migrations_page_can_migrate_every_tenant_at_once(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $first = Business::factory()->create();
        $second = Business::factory()->create();

        $this->releaseAnUpdate();

        $this->actingAs($this->platformUser());

        Livewire::test(Migrations::class)->callAction('migrateTenants');

        $this->assertTrue($this->hasMenusTable($first));
        $this->assertTrue($this->hasMenusTable($second));
        $this->assertSame(2, AuditLog::where('event', AuditEvent::MigrationsRun)->count());
    }
}
