<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\RestaurantSetting;
use App\Support\Sst;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * The settings of the restaurant currently being acted in: its business
 * details, tax and rounding, and receipts. There is no record to pick: it
 * is always the current business and never another one. Settings that
 * differ per branch (service charge, business day) are on each branch.
 *
 * @property-read Schema $form
 */
class RestaurantSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Restaurant settings';

    protected static ?string $slug = 'restaurant-settings';

    private const BUSINESS_FIELDS = ['name', 'currency', 'timezone'];

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
        $this->form->fill([
            ...$this->business()->only([...self::BUSINESS_FIELDS, 'acronym']),
            ...RestaurantSetting::current()->only((new RestaurantSetting)->getFillable()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $sstOn = fn (Get $get): bool => (bool) $get('sst_enabled');

        return $schema
            ->statePath('data')
            ->disabled(! $this->canSave())
            ->columns(1)
            ->components([
                Callout::make('Add your SST registration number')
                    ->description('SST is charged, but there is no registration number to print on receipts yet. Add it under Tax and charges.')
                    ->warning()
                    ->visible(fn (Get $get): bool => $sstOn($get) && blank($get('sst_registration_number'))),
                Section::make('Business details')
                    ->columns(2)
                    ->schema([
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
                            ->helperText('Branches use this unless they have their own.')
                            ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->searchable()
                            ->required(),
                    ]),
                Section::make('Tax and charges')
                    ->description('A change applies to new orders only. Every order keeps the tax settings it was sold under.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('sst_enabled')
                            ->label('Charge SST')
                            ->helperText('Switch off if the business is not registered for SST.')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('sst_rate')
                            ->label('SST rate')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->visible($sstOn)
                            ->required($sstOn),
                        TextInput::make('sst_registration_number')
                            ->label('SST registration number')
                            ->helperText('Printed on receipts.')
                            ->maxLength(32)
                            ->live(onBlur: true)
                            ->visible($sstOn),
                        Toggle::make('prices_include_sst')
                            ->label('Menu prices include SST')
                            ->live()
                            ->visible($sstOn),
                        Text::make(fn (Get $get): string => Sst::example((bool) $get('prices_include_sst'), (string) $get('sst_rate'), $this->business()->currency))
                            ->visible(fn (Get $get): bool => $sstOn($get) && is_numeric($get('sst_rate')) && (float) $get('sst_rate') > 0),
                        Toggle::make('round_to_five_sen')
                            ->label('Round bill totals to the nearest 5 sen')
                            ->helperText('For every payment method. RM 11.66 becomes RM 11.65, and RM 11.68 becomes RM 11.70. The difference is shown on the receipt as Rounding.')
                            ->columnSpanFull(),
                        Text::make('Service charge and the time each business day starts are set on each branch.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Receipts')
                    ->description('Each branch prints its own address and phone number under this.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('receipt_logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory(fn (): string => 'tenants/'.strtolower($this->business()->acronym).'/receipts')
                            ->visibility('public')
                            ->maxSize(1024)
                            ->columnSpanFull(),
                        Textarea::make('receipt_header')
                            ->label('Header text')
                            ->helperText('Printed under the logo, for example the company registration number.')
                            ->rows(3)
                            ->maxLength(500),
                        Textarea::make('receipt_footer')
                            ->label('Footer text')
                            ->helperText('For example: Thank you, please come again!')
                            ->rows(3)
                            ->maxLength(500),
                    ]),
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

        $state = $this->form->getState();

        // Business details are central; the rest is in the tenant's own
        // database.
        $business->update(array_intersect_key($state, array_flip(self::BUSINESS_FIELDS)));

        $settings = RestaurantSetting::current();
        $settings->update(array_intersect_key($state, array_flip($settings->getFillable())));

        Notification::make()
            ->success()
            ->title('Restaurant settings saved')
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
