<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The settings of the business currently being acted in. There is no record
 * to pick: it is always the current business and never another one.
 *
 * @property-read Schema $form
 */
class BusinessSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Business settings';

    protected static ?string $slug = 'business-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null && auth()->user()->can('view', $business);
    }

    public function mount(): void
    {
        $this->form->fill($this->business()->only(['name', 'acronym', 'currency', 'timezone']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(! $this->canSave())
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                // Set by RasaHub when the business is created.
                TextInput::make('acronym')
                    ->helperText('Staff type this to find your company when signing in to the POS.')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('currency')
                    ->helperText('Three-letter ISO 4217 code, for example MYR.')
                    ->required()
                    ->alpha()
                    ->length(3)
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper($state)),
                Select::make('timezone')
                    ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save changes')
                                ->submit('save')
                                ->visible($this->canSave()),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $business = $this->business();

        abort_unless(auth()->user()->can('update', $business), 403);

        $business->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Business settings saved')
            ->send();
    }

    private function canSave(): bool
    {
        return auth()->user()->can('update', $this->business());
    }

    private function business(): Business
    {
        return app(TenantContext::class)->business() ?? abort(404);
    }
}
