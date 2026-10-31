<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * How the restaurant's QR ordering page looks on customers' phones: the
 * design on the left, and beside it a preview that follows every change
 * before it is saved. Growth and Enterprise.
 *
 * @property-read Schema $form
 */
class QrOrderingPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Menu';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'QR ordering page';

    protected static ?string $slug = 'qr-ordering-page';

    protected string $view = 'filament.pages.qr-ordering-page';

    protected Width|string|null $maxContentWidth = Width::Full;

    private const FIELDS = ['qr_banner_path', 'qr_logo_path', 'qr_heading', 'qr_welcome_text', 'qr_primary_color', 'qr_background_color', 'qr_footer_text', 'qr_show_photos', 'qr_show_descriptions'];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null
            && auth()->user()->can('view', $business)
            && ($business->subscription?->plan->includes('qr_ordering') ?? false);
    }

    public function mount(): void
    {
        $this->form->fill(RestaurantSetting::current()->only(self::FIELDS));
    }

    public function form(Schema $schema): Schema
    {
        $directory = fn (): string => 'tenants/'.strtolower($this->business()->acronym).'/qr';

        return $schema
            ->statePath('data')
            ->disabled(! $this->canSave())
            ->components([
                Section::make('Header')
                    ->schema([
                        FileUpload::make('qr_banner_path')
                            ->label('Banner')
                            ->helperText('A wide photo across the top, about 3 to 1: your dining room or signature dish.')
                            ->image()
                            ->disk('public')
                            ->directory($directory)
                            ->visibility('public')
                            ->maxSize(3072)
                            ->live(),
                        FileUpload::make('qr_logo_path')
                            ->label('Logo')
                            ->helperText('Square works best.')
                            ->image()
                            ->disk('public')
                            ->directory($directory)
                            ->visibility('public')
                            ->maxSize(1024)
                            ->live(),
                        TextInput::make('qr_heading')
                            ->label('Heading')
                            ->helperText('Leave empty to use the restaurant\'s name.')
                            ->maxLength(80)
                            ->live(debounce: 400),
                        Textarea::make('qr_welcome_text')
                            ->label('Welcome text')
                            ->placeholder('Selamat datang! Order here and pay at the counter.')
                            ->rows(2)
                            ->maxLength(300)
                            ->live(debounce: 400),
                    ]),
                Section::make('Colours')
                    ->columns(2)
                    ->schema([
                        ColorPicker::make('qr_primary_color')
                            ->label('Main colour')
                            ->helperText('Buttons and highlights. Dark enough for white text.')
                            ->regex('/^#[0-9A-Fa-f]{6}$/')
                            ->required()
                            ->live(),
                        ColorPicker::make('qr_background_color')
                            ->label('Background colour')
                            ->regex('/^#[0-9A-Fa-f]{6}$/')
                            ->required()
                            ->live(),
                    ]),
                Section::make('Menu and footer')
                    ->schema([
                        Toggle::make('qr_show_photos')
                            ->label('Show product photos')
                            ->live(),
                        Toggle::make('qr_show_descriptions')
                            ->label('Show product descriptions')
                            ->live(),
                        Textarea::make('qr_footer_text')
                            ->label('Footer')
                            ->helperText('For example halal certification, opening hours or Wi-Fi.')
                            ->rows(2)
                            ->maxLength(500)
                            ->live(debounce: 400),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless($this->canSave(), 403);

        RestaurantSetting::current()->update(array_intersect_key($this->form->getState(), array_flip(self::FIELDS)));

        Notification::make()->success()->title('QR ordering page saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Open the real page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->liveUrl() !== null)
                ->url(fn (): ?string => $this->liveUrl(), shouldOpenInNewTab: true),
            Action::make('save')
                ->label('Save changes')
                ->visible(fn (): bool => $this->canSave())
                ->action(fn () => $this->save()),
        ];
    }

    /**
     * What the preview shows: the design as it is in the form now, saved or
     * not, with the restaurant's own menu.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $state = $this->data ?? [];
        $category = Category::where('is_active', true)->whereHas('products', fn ($q) => $q->where('is_active', true))->orderBy('sort_order')->first();

        return [
            'title' => filled($state['qr_heading'] ?? null) ? $state['qr_heading'] : $this->business()->name,
            'welcome' => $state['qr_welcome_text'] ?? null,
            'footer' => $state['qr_footer_text'] ?? null,
            'primary' => $this->colour($state['qr_primary_color'] ?? null, '#DC4A2D'),
            'background' => $this->colour($state['qr_background_color'] ?? null, '#FBF3E7'),
            'banner' => $this->imageUrl($state['qr_banner_path'] ?? null),
            'logo' => $this->imageUrl($state['qr_logo_path'] ?? null),
            'photos' => (bool) ($state['qr_show_photos'] ?? true),
            'descriptions' => (bool) ($state['qr_show_descriptions'] ?? true),
            'currency' => $this->business()->currency,
            'categories' => Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->limit(4)->pluck('name')->all() ?: ['Mains', 'Drinks', 'Desserts'],
            'products' => $category
                ? Product::where('category_id', $category->id)->where('is_active', true)->orderBy('sort_order')->limit(4)->get()
                    ->map(fn (Product $p): array => ['name' => $p->name, 'description' => $p->description, 'price' => $p->price, 'image' => $p->image_path ? Storage::disk('public')->url($p->image_path) : null])->all()
                : [
                    ['name' => 'Nasi Lemak', 'description' => 'Coconut rice, sambal, egg and anchovies.', 'price' => '12.90', 'image' => null],
                    ['name' => 'Teh Tarik', 'description' => 'Pulled milk tea.', 'price' => '4.50', 'image' => null],
                ],
        ];
    }

    private function colour(?string $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $default;
    }

    /**
     * An image in the form: a saved one, or one just chosen and not saved yet.
     */
    private function imageUrl(mixed $state): ?string
    {
        $file = is_array($state) ? (array_values($state)[0] ?? null) : $state;

        if ($file instanceof TemporaryUploadedFile) {
            try {
                return $file->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return is_string($file) && $file !== '' ? Storage::disk('public')->url($file) : null;
    }

    private function liveUrl(): ?string
    {
        return DiningTable::where('is_active', true)->orderBy('id')->first()?->qrUrl($this->business()->acronym);
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
