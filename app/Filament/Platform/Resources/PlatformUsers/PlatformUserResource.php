<?php

namespace App\Filament\Platform\Resources\PlatformUsers;

use App\Enums\Permission;
use App\Filament\Platform\Resources\PlatformUsers\Pages\CreatePlatformUser;
use App\Filament\Platform\Resources\PlatformUsers\Pages\EditPlatformUser;
use App\Filament\Platform\Resources\PlatformUsers\Pages\ListPlatformUsers;
use App\Filament\Platform\Resources\PlatformUsers\Schemas\PlatformUserForm;
use App\Filament\Platform\Resources\PlatformUsers\Tables\PlatformUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlatformUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'platform-users';

    protected static ?string $modelLabel = 'platform user';

    public static function form(Schema $schema): Schema
    {
        return PlatformUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformUsersTable::configure($table);
    }

    /**
     * Only users who belong to the platform itself. Tenant staff are managed
     * inside their own business, never from here.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->acrossBusinesses()->whereNull('business_id');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can(Permission::ManagePlatformUsers);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformUsers::route('/'),
            'create' => CreatePlatformUser::route('/create'),
            'edit' => EditPlatformUser::route('/{record}/edit'),
        ];
    }
}
