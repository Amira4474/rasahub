<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
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
                // Supervisors, Managers and Owners approve voids, discounts,
                // cancellations and refunds at the till with it.
                TextInput::make('approval_pin')
                    ->label('Approval PIN')
                    ->password()
                    ->revealable()
                    ->helperText(fn (?User $record): string => 'Four to six digits. A Supervisor, Manager or Owner types it at the POS to approve voids, discounts, cancellations and refunds.'
                        .($record?->hasApprovalPin() ? ' A PIN is set; leave empty to keep it.' : ''))
                    ->regex('/^\d{4,6}$/')
                    ->validationMessages(['regex' => 'Use four to six digits.'])
                    // Each PIN must point to one person in the business.
                    ->rule(fn (?User $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                        $taken = User::acrossBusinesses()
                            ->where('business_id', app(TenantContext::class)->businessId())
                            ->whereNotNull('approval_pin')
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->get()
                            ->contains(fn (User $staff): bool => $staff->matchesApprovalPin((string) $value));

                        if ($taken) {
                            $fail('Someone else already uses this PIN. Choose another.');
                        }
                    })
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                // Staff only ever hold business roles, never platform ones.
                Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('name', Role::names(platform: false)),
                    )
                    ->multiple()
                    ->preload(),
                // Only branches of this business that the signed-in user may
                // reach can be handed out.
                Select::make('branches')
                    ->relationship(
                        'branches',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->visibleTo(auth()->user()),
                    )
                    ->helperText('The branches this user may work in.')
                    ->multiple()
                    ->preload(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
