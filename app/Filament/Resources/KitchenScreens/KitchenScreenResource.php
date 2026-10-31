<?php

namespace App\Filament\Resources\KitchenScreens;

use App\Filament\Concerns\ManagesKitchen;
use App\Filament\Resources\KitchenScreens\Pages\ManageKitchenScreens;
use App\Models\Branch;
use App\Models\KitchenScreen;
use App\Models\Station;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Screens in the kitchen and bar, each showing chosen stations' orders.
 * Kitchen staff choose one when they sign in to the POS. Growth and
 * Enterprise.
 */
class KitchenScreenResource extends Resource
{
    use ManagesKitchen {
        canViewAny as private canManage;
    }

    protected static ?string $model = KitchenScreen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTv;

    protected static string|UnitEnum|null $navigationGroup = 'Kitchen';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return self::canManage()
            && (app(TenantContext::class)->business()?->subscription?->plan->includes('kitchen_display') ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', Branch::visibleTo(auth()->user())->select('id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name', fn (Builder $query): Builder => $query->withoutTrashed()->visibleTo(auth()->user()))
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->helperText('For example: Hot kitchen, Bar.')
                    ->required()
                    ->maxLength(255),
                Select::make('station_ids')
                    ->label('Shows')
                    ->helperText('Leave empty to show every station.')
                    ->options(fn (): array => Station::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->multiple(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('branch'))
            ->columns([
                TextColumn::make('branch.name')->label('Branch'),
                TextColumn::make('name'),
                TextColumn::make('shows')
                    ->state(fn (KitchenScreen $record): string => $record->stationIds() === []
                        ? 'All stations'
                        : Station::whereKey($record->stationIds())->pluck('name')->implode(', ')),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKitchenScreens::route('/'),
        ];
    }
}
