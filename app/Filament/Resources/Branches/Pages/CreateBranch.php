<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * The new branch is placed in the current business by the Branch model
 * itself, so there is nothing to choose and nothing to tamper with here.
 */
class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;
}
