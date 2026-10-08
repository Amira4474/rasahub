<?php

namespace App\Filament\Platform\Resources\Invitations;

use App\Filament\Platform\Resources\Invitations\Pages\CreateInvitation;
use App\Filament\Platform\Resources\Invitations\Pages\ListInvitations;
use App\Filament\Platform\Resources\Invitations\Pages\ViewInvitation;
use App\Filament\Platform\Resources\Invitations\Schemas\InvitationForm;
use App\Filament\Platform\Resources\Invitations\Schemas\InvitationInfolist;
use App\Filament\Platform\Resources\Invitations\Tables\InvitationsTable;
use App\Models\TenantInvitation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Invitations to register a new tenant. Each one is for a single email
 * address and its link works once.
 */
class InvitationResource extends Resource
{
    protected static ?string $model = TenantInvitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'invitations';

    protected static ?string $modelLabel = 'invitation';

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return InvitationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvitationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvitationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
            'create' => CreateInvitation::route('/create'),
            'view' => ViewInvitation::route('/{record}'),
        ];
    }
}
