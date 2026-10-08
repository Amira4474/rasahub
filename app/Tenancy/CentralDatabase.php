<?php

namespace App\Tenancy;

use Illuminate\Database\Migrations\Migrator;

/**
 * The central database holds what sits above the tenants. Its migrations
 * are the ones directly inside database/migrations.
 */
class CentralDatabase
{
    public function name(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }

    /**
     * The central migrations that have not been run yet.
     *
     * @return list<string>
     */
    public function pendingMigrations(): array
    {
        $migrator = $this->migrator();

        return $migrator->usingConnection(config('database.default'), function () use ($migrator): array {
            $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));

            return array_values(array_diff($files, $migrator->getRepository()->getRan()));
        });
    }

    /**
     * Run the outstanding central migrations.
     *
     * @return list<string> The migrations that were run.
     */
    public function migrate(): array
    {
        $pending = $this->pendingMigrations();

        if ($pending !== []) {
            $migrator = $this->migrator();

            $migrator->usingConnection(config('database.default'), fn (): array => $migrator->run([database_path('migrations')]));
        }

        return $pending;
    }

    private function migrator(): Migrator
    {
        return app('migrator');
    }
}
