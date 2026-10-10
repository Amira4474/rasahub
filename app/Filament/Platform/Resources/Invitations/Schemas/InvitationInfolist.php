<?php

namespace App\Filament\Platform\Resources\Invitations\Schemas;

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
                Section::make('Registration token')
                    ->description('Give this token to the person you are inviting. They enter it on the registration page, then fill in their business details.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('token')
                            ->copyable()
                            ->copyMessage('Token copied')
                            ->fontFamily('mono')
                            ->weight('bold'),
                        TextEntry::make('registration_page')
                            ->label('They enter it at')
                            ->state(fn (): string => route('register.show'))
                            ->copyable()
                            ->copyMessage('Address copied')
                            ->fontFamily('mono'),
                    ]),
                Section::make('Invitation')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('note')
                            ->label('For')
                            ->placeholder('Not noted'),
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
