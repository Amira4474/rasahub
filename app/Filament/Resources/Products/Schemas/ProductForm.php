<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductType;
use App\Filament\Resources\Products\ProductResource;
use App\Tenancy\TenantContext;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSet = fn (Get $get): bool => ($get('type') instanceof ProductType ? $get('type') : ProductType::tryFrom((string) $get('type'))) === ProductType::Set;

        return $schema
            ->columns(1)
            ->components([
                Section::make('Product')
                    ->columns(3)
                    ->schema([
                        Radio::make('type')
                            ->options(ProductType::options())
                            ->default(ProductType::Single->value)
                            ->inline()
                            ->live()
                            // Changing a set meal into a single item, or back,
                            // would strand its choices.
                            ->disabledOn('edit')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('price')
                            ->helperText(fn (): string => 'Including SST. Branches use this price unless they have their own.')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(9999999)
                            ->prefix(fn (): string => ProductResource::currency())
                            ->required(),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->helperText('Optional stock-keeping code.')
                            ->maxLength(64)
                            ->unique(ignoreRecord: true),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('An inactive product is hidden from the POS at every branch.')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        // Menu photos are public: QR ordering will show them
                        // to customers. Each tenant's are kept apart.
                        FileUpload::make('image_path')
                            ->label('Photo')
                            ->image()
                            ->disk('public')
                            ->directory(fn (): string => 'tenants/'.strtolower((string) app(TenantContext::class)->business()?->acronym).'/products')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),
                Section::make('Modifiers')
                    ->description('Choices offered when this product is ordered, such as Size or Sugar level. Manage them under Menu → Modifiers.')
                    ->schema([
                        Select::make('modifierGroups')
                            ->hiddenLabel()
                            ->relationship('modifierGroups', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
                Section::make('Set meal choices')
                    ->description('What the customer chooses to make up the set, such as "Choose a main" and "Choose a drink". Each part goes to its own kitchen or bar.')
                    ->visible($isSet)
                    ->schema([
                        Repeater::make('setGroups')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add choice')
                            ->columns(4)
                            ->schema([
                                TextInput::make('name')
                                    ->placeholder('Choose a drink')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('min_select')
                                    ->label('Minimum')
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(20)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('max_select')
                                    ->label('Maximum')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->gte('min_select')
                                    ->default(1)
                                    ->required(),
                                Repeater::make('items')
                                    ->label('Products to choose from')
                                    ->relationship()
                                    ->orderColumn('sort_order')
                                    ->reorderable()
                                    ->minItems(1)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add product')
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->schema([
                                        // A set cannot contain another set.
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->relationship(
                                                'product',
                                                'name',
                                                modifyQueryUsing: fn (Builder $query): Builder => $query->singleItems()->withoutTrashed(),
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        TextInput::make('extra_price')
                                            ->label('Extra charge')
                                            ->numeric()
                                            ->step('0.01')
                                            ->minValue(0)
                                            ->maxValue(99999)
                                            ->default('0.00')
                                            ->prefix(fn (): string => ProductResource::currency())
                                            ->required(),
                                        Toggle::make('is_default')
                                            ->label('Chosen by default')
                                            ->inline(false),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
