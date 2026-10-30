<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\Permission;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The product at each branch. A branch with no row here sells the product
 * at its own price. Each user sees only the branches they work in.
 */
class BranchSettingsRelationManager extends RelationManager
{
    protected static string $relationship = 'branchSettings';

    protected static ?string $title = 'Branches';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can(Permission::ViewCatalogue);
    }

    /**
     * Availability can be set from the product's view page too, by someone
     * who may not edit the product itself.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    private static function mayPriceBranches(): bool
    {
        return ProductResource::allowsBranchPrices() && auth()->user()->can(Permission::ManageCatalogue);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('branch_id')
                    ->label('Branch')
                    ->options(function (?BranchProduct $record): array {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        return Branch::visibleTo(auth()->user())
                            ->when($record === null, fn (Builder $query) => $query->whereNotIn('id', $product->branchSettings()->select('branch_id')))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->disabledOn('edit')
                    ->required(),
                Toggle::make('is_available')
                    ->label('Available')
                    ->default(true)
                    ->inline(false),
                TextInput::make('price')
                    ->label('Branch price')
                    ->helperText('Leave empty to use the product price.')
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0)
                    ->maxValue(9999999)
                    ->prefix(fn (): string => ProductResource::currency())
                    ->visible(fn (): bool => self::mayPriceBranches()),
            ]);
    }

    public function table(Table $table): Table
    {
        $branchIds = Branch::visibleTo(auth()->user())->select('id');

        return $table
            ->description(ProductResource::allowsBranchPrices()
                ? 'A branch not listed here sells the product at its own price. Add a branch to mark the product sold out there, or to give it a branch price.'
                : 'A branch not listed here sells the product. Add a branch to mark the product sold out there. Branch prices are available on the Growth and Enterprise plans.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('branch_id', $branchIds)->with('branch'))
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Branch'),
                IconColumn::make('is_available')
                    ->label('Available')
                    ->boolean(),
                TextColumn::make('price')
                    ->label('Branch price')
                    ->money(fn (): string => ProductResource::currency())
                    ->placeholder('Product price'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add branch'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Reset')
                    ->modalHeading('Reset to the product defaults')
                    ->modalDescription('The branch sells the product again, at the product price.'),
            ]);
    }
}
