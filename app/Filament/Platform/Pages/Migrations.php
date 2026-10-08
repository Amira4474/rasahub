<?php

namespace App\Filament\Platform\Pages;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\Business;
use App\Tenancy\CentralDatabase;
use App\Tenancy\TenantDatabase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Throwable;

/**
 * Brings the databases up to date after an update to RasaHub: the central
 * database, and each tenant's own database.
 */
class Migrations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.platform.migrations';

    public static function canAccess(): bool
    {
        return auth()->user()->can(Permission::RunMigrations);
    }

    public function centralDatabase(): string
    {
        return app(CentralDatabase::class)->name();
    }

    /**
     * @return list<string>
     */
    public function centralPending(): array
    {
        return app(CentralDatabase::class)->pendingMigrations();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('migrateCentral')
                ->label('Run central migrations')
                ->icon(Heroicon::OutlinedPlay)
                ->disabled(fn (): bool => $this->centralPending() === [])
                ->requiresConfirmation()
                ->modalDescription('This changes the central database. Back it up first if it holds anything you cannot afford to lose.')
                ->action(function (): void {
                    $this->report('Central database', fn (): array => app(CentralDatabase::class)->migrate());
                }),
            Action::make('migrateTenants')
                ->label('Migrate all tenants')
                ->icon(Heroicon::OutlinedPlay)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This runs the outstanding tenant migrations in every tenant database, one tenant at a time.')
                ->action(function (): void {
                    foreach (Business::withTrashed()->orderBy('id')->get() as $tenant) {
                        $this->migrateTenant($tenant);
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $databases = app(TenantDatabase::class);

        return $table
            ->query(Business::query()->withTrashed())
            ->heading('Tenant databases')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('acronym'),
                TextColumn::make('database')
                    ->state(fn (Business $record): string => $databases->nameFor($record)),
                TextColumn::make('pending')
                    ->label('Status')
                    ->state(fn (Business $record): string => $this->tenantStatus($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Up to date' => 'success',
                        'Database missing' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->recordActions([
                Action::make('migrate')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (Business $record): bool => str_ends_with($this->tenantStatus($record), 'pending'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Business $record): string => "Migrate {$record->name}")
                    ->action(fn (Business $record) => $this->migrateTenant($record)),
            ]);
    }

    private function tenantStatus(Business $tenant): string
    {
        $databases = app(TenantDatabase::class);

        if (! $databases->exists($tenant)) {
            return 'Database missing';
        }

        $pending = count($databases->pendingMigrations($tenant));

        return $pending === 0 ? 'Up to date' : "{$pending} pending";
    }

    private function migrateTenant(Business $tenant): void
    {
        $this->report($tenant->name, fn (): array => app(TenantDatabase::class)->migrate($tenant), $tenant);
    }

    /**
     * Run the migrations, record what happened and tell the user.
     *
     * @param  callable(): list<string>  $migrate
     */
    private function report(string $database, callable $migrate, ?Business $tenant = null): void
    {
        try {
            $ran = $migrate();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title("{$database}: migration failed")
                ->body($exception->getMessage())
                ->persistent()
                ->send();

            return;
        }

        if ($ran === []) {
            Notification::make()->title("{$database}: nothing to migrate")->send();

            return;
        }

        app(Audit::class)->record(AuditEvent::MigrationsRun, $tenant, [
            'database' => $tenant === null ? 'central' : 'tenant',
            'migrations' => $ran,
        ]);

        Notification::make()
            ->success()
            ->title("{$database}: ".count($ran).' migrated')
            ->body(implode(', ', $ran))
            ->send();
    }
}
