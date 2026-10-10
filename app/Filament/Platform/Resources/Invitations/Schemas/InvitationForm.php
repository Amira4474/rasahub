<?php

namespace App\Filament\Platform\Resources\Invitations\Schemas;

use App\Models\TenantInvitation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('token')
                    ->helperText('What the new tenant types to register, for example OCT_RH. Letters, digits, hyphens and underscores; capitals or not makes no difference. A suggestion is filled in: keep it, or type your own. A short or obvious token is easier to guess.')
                    ->default(fn (): string => TenantInvitation::suggestToken())
                    ->required()
                    ->minLength(TenantInvitation::TOKEN_MIN_LENGTH)
                    ->maxLength(TenantInvitation::TOKEN_MAX_LENGTH)
                    ->regex(TenantInvitation::TOKEN_PATTERN)
                    ->dehydrateStateUsing(fn (string $state): string => TenantInvitation::normalise($state))
                    // Every token is used for one invitation only, including
                    // those already used, so the history stays unambiguous.
                    ->rule(fn (): callable => function (string $attribute, mixed $value, callable $fail): void {
                        if (TenantInvitation::findByToken((string) $value) !== null) {
                            $fail('This token has already been used for an invitation. Please choose another.');
                        }
                    })
                    ->validationMessages(['regex' => 'Use only letters, digits, hyphens and underscores.'])
                    ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: ui-monospace, monospace;'])
                    ->autocomplete(false),
                TextInput::make('note')
                    ->label('Who is it for?')
                    ->helperText('Optional, and only seen here.')
                    ->placeholder('For example: Kopi Kita Bersama, Aisyah')
                    ->maxLength(255),
                DateTimePicker::make('expires_at')
                    ->label('Expires')
                    ->helperText('The token cannot be used after this.')
                    ->default(fn (): string => now()->addDays(TenantInvitation::DEFAULT_DAYS_VALID)->toDateTimeString())
                    ->after('now')
                    ->seconds(false)
                    ->required(),
            ]);
    }
}
