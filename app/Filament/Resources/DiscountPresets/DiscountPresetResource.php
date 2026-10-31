<?php

namespace App\Filament\Resources\DiscountPresets;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Enums\Permission;
use App\Filament\Resources\DiscountPresets\Pages\ManageDiscountPresets;
use App\Models\DiscountPreset;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The discounts cashiers can give with one tap, such as "Staff 20%".
 */
class DiscountPresetResource extends Resource
{
    protected static ?string $model = DiscountPreset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Discounts';

    protected static ?string $modelLabel = 'discount';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permission::ManageDiscounts);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can(Permission::ManageDiscounts);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can(Permission::ManageDiscounts);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can(Permission::ManageDiscounts);
    }

    public static function form(Schema $schema): Schema
    {
        $percent = fn (Get $get): bool => ($get('type') instanceof DiscountType ? $get('type') : DiscountType::tryFrom((string) $get('type'))) === DiscountType::Percent;

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->helperText('As cashiers see it, for example Staff or Birthday.')
                    ->required()
                    ->maxLength(255),
                Select::make('scope')
                    ->label('Given on')
                    ->options(DiscountScope::options())
                    ->default(DiscountScope::Order->value)
                    ->selectablePlaceholder(false)
                    ->required(),
                Select::make('type')
                    ->options(DiscountType::options())
                    ->default(DiscountType::Percent->value)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->required(),
                TextInput::make('value')
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0.01)
                    ->maxValue(fn (Get $get): int => $percent($get) ? 100 : 9999999)
                    ->suffix(fn (Get $get): ?string => $percent($get) ? '%' : null)
                    ->prefix(fn (Get $get): ?string => $percent($get) ? null : app(TenantContext::class)->business()?->currency)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = app(TenantContext::class)->business()?->currency ?? 'MYR';

        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('value')
                    ->label('Discount')
                    ->state(fn (DiscountPreset $record): string => $record->valueLabel($currency)),
                TextColumn::make('scope')
                    ->label('Given on')
                    ->formatStateUsing(fn (DiscountScope $state): string => $state->label()),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Cashiers can no longer give it. Orders that had it keep it.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDiscountPresets::route('/'),
        ];
    }
}
