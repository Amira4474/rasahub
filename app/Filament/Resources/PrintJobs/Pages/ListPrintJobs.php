<?php

namespace App\Filament\Resources\PrintJobs\Pages;

use App\Filament\Resources\PrintJobs\PrintJobResource;
use Filament\Resources\Pages\ListRecords;

class ListPrintJobs extends ListRecords
{
    protected static string $resource = PrintJobResource::class;

    protected ?string $subheading = 'Queued jobs print when the branch\'s print gateway collects them, every few seconds. A failed job shows why when you point at it.';
}
