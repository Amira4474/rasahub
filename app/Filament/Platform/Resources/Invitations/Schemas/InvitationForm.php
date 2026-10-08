<?php

namespace App\Filament\Platform\Resources\Invitations\Schemas;

use App\Enums\Plan;
use App\Models\TenantInvitation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class InvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->helperText('Only this address can register with the invitation. It becomes the Owner\'s sign-in.')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (string $state): string => strtolower(trim($state)))
                    // Someone who already has an account cannot register again.
                    ->rule(Rule::unique('users', 'email'))
                    ->validationMessages(['unique' => 'Someone with this email address already has an account.']),
                Select::make('plan')
                    ->helperText('The plan the new tenant starts on.')
                    ->options(Plan::options())
                    ->default(Plan::Starter->value)
                    ->selectablePlaceholder(false)
                    ->required(),
                DateTimePicker::make('expires_at')
                    ->label('Expires')
                    ->default(fn (): string => now()->addDays(TenantInvitation::DEFAULT_DAYS_VALID)->toDateTimeString())
                    ->after('now')
                    ->seconds(false)
                    ->required(),
            ]);
    }
}
