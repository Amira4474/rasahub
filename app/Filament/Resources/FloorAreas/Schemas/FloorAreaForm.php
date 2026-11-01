<?php

namespace App\Filament\Resources\FloorAreas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FloorAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Floor area')
                    ->columns(2)
                    ->schema([
                        // Only branches the signed-in user may reach can be chosen.
                        Select::make('branch_id')
                            ->label('Branch')
                            ->relationship(
                                'branch',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed()->visibleTo(auth()->user()),
                            )
                            ->disabledOn('edit')
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->helperText('For example: Indoor, Patio, Upstairs.')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
