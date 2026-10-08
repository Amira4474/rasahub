<?php

namespace App\Tenancy;

use App\Exceptions\NoTenantException;
use App\Models\Business;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Every tenant keeps its own data (branches, POS terminals and everything
 * built on them) in a database of its own. This class creates those
 * databases, keeps them up to date, and points the "tenant" connection at
 * the right one.
 *
 * The central database holds what sits above the tenants: the businesses
 * themselves, users, roles, subscriptions, invitations and the audit log.
 */
class TenantDatabase
{
    public const CONNECTION = 'tenant';

    /**
     * The business the connection has been pointed at on purpose, null for
     * the server with no database selected, or false to follow whichever
     * business is being acted in.
     */
    private int|false|null $target = false;

    /**
     * What the open connection is actually using: a business, null for no
     * database, or false when no connection is open.
     */
    private int|false|null $connectedTo = false;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * The database a newly opened tenant connection should use. Registered
     * in AppServiceProvider, so that code reaching the connection directly
     * (a validation rule, for one) lands in the current business's database
     * and never in one left over from earlier work.
     */
    public function databaseForNewConnection(): ?string
    {
        $this->connectedTo = $this->target === false ? $this->context->businessId() : $this->target;

        return $this->connectedTo === null ? null : $this->nameFor($this->connectedTo);
    }

    public function nameFor(Business|int $business): string
    {
        $name = config('tenancy.database_prefix').($business instanceof Business ? $business->getKey() : $business);

        // The name is written straight into SQL below, so it is checked here.
        if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $name) !== 1) {
            throw new InvalidArgumentException("[{$name}] is not a usable tenant database name.");
        }

        return $name;
    }

    /**
     * Point the tenant connection at the business being acted in, and say
     * which one that is. Tenant models call this before every query.
     *
     * @throws NoTenantException
     */
    public function useCurrent(): int
    {
        $businessId = $this->context->businessId();

        if ($businessId === null) {
            throw new NoTenantException;
        }

        $this->connect($businessId);

        return $businessId;
    }

    /**
     * Close the tenant connection and go back to following the business
     * being acted in. Called whenever that business may have changed, so
     * that a query built earlier can never run against the wrong database.
     */
    public function release(): void
    {
        $this->target = false;

        if ($this->connectedTo !== false) {
            DB::purge(self::CONNECTION);

            $this->connectedTo = false;
        }
    }

    public function exists(Business|int $business): bool
    {
        $name = $this->nameFor($business);

        return $this->onServer(fn (Connection $server): bool => $server
            ->table('information_schema.schemata')
            ->where('schema_name', $name)
            ->exists());
    }

    /**
     * Create the tenant's database and build its tables. An existing
     * database is never adopted: it may hold another tenant's data.
     */
    public function create(Business|int $business): void
    {
        $name = $this->nameFor($business);

        if ($this->exists($business)) {
            throw new RuntimeException("The tenant database [{$name}] already exists. Remove it before creating this tenant.");
        }

        $this->onServer(fn (Connection $server): bool => $server->statement(
            "CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        ));

        try {
            $this->migrate($business);
        } catch (Throwable $exception) {
            $this->drop($business);

            throw $exception;
        }
    }

    /**
     * Permanently delete the tenant's database and everything in it.
     */
    public function drop(Business|int $business): void
    {
        $name = $this->nameFor($business);

        $this->onServer(fn (Connection $server): bool => $server->statement("DROP DATABASE IF EXISTS `{$name}`"));
    }

    /**
     * The tenant migrations that have not been run for this tenant yet.
     *
     * @return list<string>
     */
    public function pendingMigrations(Business|int $business): array
    {
        return $this->withMigrator($business, fn (Migrator $migrator): array => $this->pending($migrator));
    }

    /**
     * Run the outstanding tenant migrations for this tenant.
     *
     * @return list<string> The migrations that were run.
     */
    public function migrate(Business|int $business): array
    {
        return $this->withMigrator($business, function (Migrator $migrator): array {
            $pending = $this->pending($migrator);

            $migrator->run([config('tenancy.migrations_path')]);

            return $pending;
        });
    }

    /**
     * @return list<string>
     */
    private function pending(Migrator $migrator): array
    {
        $files = array_keys($migrator->getMigrationFiles([config('tenancy.migrations_path')]));

        return array_values(array_diff($files, $migrator->getRepository()->getRan()));
    }

    /**
     * @template TReturn
     *
     * @param  Closure(Migrator): TReturn  $callback
     * @return TReturn
     */
    private function withMigrator(Business|int $business, Closure $callback): mixed
    {
        $this->connect($business instanceof Business ? $business->getKey() : $business);

        $migrator = app('migrator');

        try {
            return $migrator->usingConnection(self::CONNECTION, function () use ($migrator, $callback): mixed {
                if (! $migrator->repositoryExists()) {
                    $migrator->getRepository()->createRepository();
                }

                return $callback($migrator);
            });
        } finally {
            $this->release();
        }
    }

    /**
     * Run a statement against the database server itself, with no database
     * selected. This uses the tenant connection so that creating a database
     * never ends a transaction on the central connection.
     *
     * @template TReturn
     *
     * @param  Closure(Connection): TReturn  $callback
     * @return TReturn
     */
    private function onServer(Closure $callback): mixed
    {
        $this->connect(null);

        try {
            return $callback(DB::connection(self::CONNECTION));
        } finally {
            $this->release();
        }
    }

    private function connect(?int $businessId): void
    {
        $this->target = $businessId;

        if ($this->connectedTo !== $businessId) {
            DB::purge(self::CONNECTION);

            $this->connectedTo = false;
        }
    }
}
