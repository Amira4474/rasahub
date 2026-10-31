<?php

namespace App\Filament\Resources\Stations;

use App\Filament\Concerns\ManagesKitchen;
use App\Filament\Resources\Stations\Pages\ManageStations;
use App\Models\Station;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Where food and drink are made. Categories and products send their lines
 * to a station, which has its printer and kitchen screens.
 */
class StationResource extends Resource
{
    use ManagesKitchen;

    protected static ?string $model = Station::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Kitchen';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->helperText('For example: Kitchen, Bar, Dessert.')
                ->required()
                ->maxLength(255),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('printers.name')
                    ->label('Printers')
                    ->badge()
                    ->placeholder('No printer'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Products made here go to no station until another is chosen. Past orders keep its name.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStations::route('/'),
        ];
    }
}
