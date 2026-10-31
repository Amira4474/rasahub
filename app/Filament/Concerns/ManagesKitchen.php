<?php

namespace App\Filament\Concerns;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Model;

/**
 * For the kitchen set-up pages: stations, printers, kitchen screens and the
 * print log. Owners and Managers look after them.
 */
trait ManagesKitchen
{
    public static function canViewAny(): bool
    {
        return auth()->user()->can(Permission::ManageKitchen);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can(Permission::ManageKitchen);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can(Permission::ManageKitchen);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can(Permission::ManageKitchen);
    }
}
