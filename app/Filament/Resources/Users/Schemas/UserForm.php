<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Only platform users pick a business; for everyone else the
                // create page sets it to their own. It cannot change later.
                Select::make('business_id')
                    ->relationship('business', 'name')
                    ->helperText('Leave empty for a platform user who belongs to no business.')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (): bool => auth()->user()->isPlatformUser())
                    ->disabledOn('edit'),
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
                // Only a Super Admin may hand out the Super Admin role.
                Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->unless(
                            auth()->user()->hasRole(Role::SuperAdmin),
                            fn (Builder $query): Builder => $query->where('name', '!=', Role::SuperAdmin->value),
                        ),
                    )
                    ->multiple()
                    ->preload(),
                // Branch assignments stay inside the user's business and
                // inside what the signed-in user may reach.
                Select::make('branches')
                    ->relationship(
                        'branches',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get, ?object $record): Builder => $query
                            ->visibleTo(auth()->user())
                            ->where('branches.business_id', $record?->business_id ?? $get('business_id') ?? auth()->user()->business_id),
                    )
                    ->helperText('The branches this user may work in.')
                    ->multiple()
                    ->preload(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
