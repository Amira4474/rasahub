<?php

namespace App\Filament\Resources\FloorAreas;

use App\Filament\Resources\FloorAreas\Pages\CreateFloorArea;
use App\Filament\Resources\FloorAreas\Pages\EditFloorArea;
use App\Filament\Resources\FloorAreas\Pages\FloorPlan;
use App\Filament\Resources\FloorAreas\Pages\ListFloorAreas;
use App\Filament\Resources\FloorAreas\RelationManagers\TablesRelationManager;
use App\Filament\Resources\FloorAreas\Schemas\FloorAreaForm;
use App\Filament\Resources\FloorAreas\Tables\FloorAreasTable;
use App\Models\FloorArea;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Floor areas and their tables, per branch. Available on every plan.
 */
class FloorAreaResource extends Resource
{
    protected static ?string $model = FloorArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Floor plans';

    protected static ?string $modelLabel = 'floor area';

    protected static ?string $recordTitleAttribute = 'name';

    public static function qrOrderingIncluded(): bool
    {
        return app(TenantContext::class)->business()?->subscription?->plan->includes('qr_ordering') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return FloorAreaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FloorAreasTable::configure($table);
    }

    /**
     * Only the areas of the branches the signed-in user works in.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getRelations(): array
    {
        return [
            TablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFloorAreas::route('/'),
            'create' => CreateFloorArea::route('/create'),
            'edit' => EditFloorArea::route('/{record}/edit'),
            'plan' => FloorPlan::route('/{record}/plan'),
        ];
    }
}
