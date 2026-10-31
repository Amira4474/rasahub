<?php

namespace App\Filament\Resources\Terminals\Schemas;

use App\Enums\PrinterRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class TerminalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Only branches the signed-in user may reach can be chosen.
                Select::make('branch_id')
                    ->relationship(
                        'branch',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->visibleTo(auth()->user()),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->helperText('Short code, unique within the branch.')
                    ->required()
                    ->alphaDash()
                    ->maxLength(32)
                    ->unique(modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('branch_id', $get('branch_id'))),
                // Receipts print here through the print gateway; without one,
                // the browser prints them.
                Select::make('receipt_printer_id')
                    ->label('Receipt printer')
                    ->helperText('Leave empty to print receipts from the browser.')
                    ->relationship(
                        'receiptPrinter',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('branch_id', $get('branch_id'))
                            ->where('role', PrinterRole::Receipt)
                            ->where('is_active', true),
                    )
                    ->preload(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
