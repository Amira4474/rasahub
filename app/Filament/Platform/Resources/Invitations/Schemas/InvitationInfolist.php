<?php

namespace App\Filament\Platform\Resources\Invitations\Schemas;

use App\Enums\Plan;
use App\Filament\Platform\Resources\Invitations\Pages\ViewInvitation;
use App\Models\TenantInvitation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvitationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The link can only be shown straight after it is made,
                // because only a hash of the token is stored.
                Section::make('Registration link')
                    ->description('Copy this link and send it to the person you are inviting. It is shown only once.')
                    ->visible(fn (ViewInvitation $livewire): bool => $livewire->link !== null)
                    ->schema([
                        TextEntry::make('link')
                            ->hiddenLabel()
                            ->state(fn (ViewInvitation $livewire): ?string => $livewire->link)
                            ->copyable()
                            ->copyMessage('Link copied')
                            ->fontFamily('mono'),
                    ]),
                Section::make('Invitation')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('email')
                            ->label('Email address'),
                        TextEntry::make('plan')
                            ->formatStateUsing(fn (Plan $state): string => $state->label()),
                        TextEntry::make('status')
                            ->state(fn (TenantInvitation $record): string => $record->status()->label())
                            ->badge()
                            ->color(fn (TenantInvitation $record): string => $record->status()->color()),
                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->dateTime(),
                        TextEntry::make('creator.name')
                            ->label('Invited by')
                            ->placeholder('Removed user'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('business.name')
                            ->label('Registered tenant')
                            ->placeholder('Not registered yet'),
                        TextEntry::make('accepted_at')
                            ->label('Registered')
                            ->dateTime()
                            ->placeholder('Not registered yet'),
                    ]),
            ]);
    }
}
