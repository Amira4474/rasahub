<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Tenancy\TenantDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tenants:migrate {acronym? : Only migrate the tenant with this company acronym}')]
#[Description('Run the outstanding tenant migrations in every tenant database')]
class MigrateTenants extends Command
{
    /**
     * Central migrations are run with the usual `php artisan migrate`.
     */
    public function handle(TenantDatabase $databases): int
    {
        $tenants = Business::withTrashed()
            ->when($this->argument('acronym'), fn ($query, string $acronym) => $query->where('acronym', strtoupper($acronym)))
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->components->warn('No tenant found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $ran = $databases->migrate($tenant);

            $this->components->twoColumnDetail(
                "{$tenant->name} ({$databases->nameFor($tenant)})",
                $ran === [] ? 'Nothing to migrate' : count($ran).' migrated',
            );
        }

        return self::SUCCESS;
    }
}
