<?php

namespace App\Filament\Platform\Resources\PlatformUsers\Schemas;

use App\Enums\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class PlatformUserForm
{
    public static function configure(Schema $schema): Schema
    {
        // Nobody may take away their own access by mistake.
        $isSelf = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : null)
                    // An empty value on edit must not overwrite the password.
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                // Platform users only ever hold platform roles.
                Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('name', Role::names(platform: true)),
                    )
                    ->multiple()
                    ->preload()
                    ->required()
                    ->disabled($isSelf),
                Toggle::make('is_active')
                    ->default(true)
                    ->disabled($isSelf),
            ]);
    }
}
