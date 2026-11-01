<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Customer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->helperText('How the POS finds the customer. Spaces and dashes are ignored.')
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->dehydrateStateUsing(fn (string $state): string => Customer::normalisePhone($state))
                            ->rule('regex:/^[\d\s\-+()]{8,20}$/')
                            // Compared without spaces and dashes, as it is stored.
                            ->rule(fn (?Customer $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                $taken = Customer::withTrashed()
                                    ->where('phone', Customer::normalisePhone((string) $value))
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($taken) {
                                    $fail('Another customer already has this phone number.');
                                }
                            }),
                        TextInput::make('name')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        DatePicker::make('birthday')
                            ->maxDate(today()),
                        Toggle::make('marketing_consent')
                            ->label('Agrees to marketing messages')
                            ->helperText('Under the PDPA, only contact customers who agreed. The date they agreed is kept.')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->helperText('For example allergies or seating preferences.')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
