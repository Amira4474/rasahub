<?php

namespace App\Filament\Platform\Resources\Subscriptions;

use App\Filament\Platform\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Platform\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Platform\Resources\Subscriptions\Schemas\SubscriptionForm;
use App\Filament\Platform\Resources\Subscriptions\Tables\SubscriptionsTable;
use App\Models\Subscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Each tenant's plan, status and dates. A subscription is created with its
 * tenant, so there is nothing to create or delete here.
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }
}
