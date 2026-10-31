<?php

namespace App\Tenancy;

use Illuminate\Database\Connection;

/**
 * A faster way to build a tenant database, for the test suite only.
 *
 * Running every tenant migration takes several seconds, because MySQL adds
 * each foreign key with a separate table rebuild. The test suite builds a
 * tenant database for almost every test. So the first database is built
 * with the migrations as usual, its finished table definitions are kept,
 * and later databases are created straight from those definitions, with
 * the migrations recorded as run.
 *
 * A database it made is also kept for the next test that wants the same
 * name, and emptied rather than dropped and built again, which is faster
 * still. One is only reused if this process made it and its migrations
 * match the current ones exactly.
 *
 * Switched on by TENANT_SCHEMA_COPY in phpunit.xml. Everywhere else every
 * tenant database is built by its migrations, and an existing database is
 * never reused.
 */
class TenantSchemaCopy
{
    /**
     * Table definitions and migration records, kept for the life of the
     * process, by the set of migration files they were made from.
     *
     * @var array<string, array{tables: list<string>, migrations: list<array<string, mixed>>}>
     */
    private static array $copies = [];

    /**
     * Databases this process built, which it may therefore empty and reuse.
     *
     * @var array<string, true>
     */
    private static array $built = [];

    public static function enabled(): bool
    {
        return (bool) config('tenancy.schema_copy');
    }

    /**
     * Build the tables in the connected, empty database from a copy, if one
     * has been kept for the current migration files.
     */
    public static function restore(Connection $tenant): bool
    {
        $copy = self::$copies[self::key()] ?? null;

        if ($copy === null) {
            return false;
        }

        $tenant->unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($copy['tables'] as $definition) {
                $tenant->unprepared($definition);
            }
        } finally {
            $tenant->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }

        $tenant->table('migrations')->insert($copy['migrations']);

        return true;
    }

    public static function remember(string $database): void
    {
        self::$built[$database] = true;
    }

    public static function built(string $database): bool
    {
        return isset(self::$built[$database]);
    }

    /**
     * Empty the connected database for its next use, if it is one this
     * process built from the current migrations. Rows are deleted rather
     * than tables truncated: MySQL rebuilds a table to truncate it, which is
     * as slow as building it. So record numbers carry on from the last test
     * instead of starting again at 1.
     */
    public static function reuse(Connection $tenant, string $database): bool
    {
        $copy = self::$copies[self::key()] ?? null;

        if ($copy === null || ! self::built($database)) {
            return false;
        }

        $ran = $tenant->table('migrations')->orderBy('id')->pluck('migration')->all();

        if ($ran !== array_column($copy['migrations'], 'migration')) {
            return false;
        }

        $tables = array_map(
            fn (object $row): string => array_values((array) $row)[0],
            $tenant->select('SHOW TABLES'),
        );

        $tenant->unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                if ($table !== 'migrations' && $tenant->table($table)->limit(1)->exists()) {
                    $tenant->table($table)->delete();
                }
            }
        } finally {
            $tenant->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }

        return true;
    }

    /**
     * Keep the definitions of the tables in the connected database, which
     * its migrations have just built.
     */
    public static function keep(Connection $tenant): void
    {
        $tables = array_map(
            fn (object $row): string => array_values((array) $row)[0],
            $tenant->select('SHOW TABLES'),
        );

        self::$copies[self::key()] = [
            'tables' => array_map(
                // The counter of rows already inserted is left out.
                fn (string $table): string => preg_replace(
                    '/ AUTO_INCREMENT=\d+/',
                    '',
                    array_values((array) $tenant->selectOne("SHOW CREATE TABLE `{$table}`"))[1],
                ),
                $tables,
            ),
            'migrations' => array_map(
                fn (object $row): array => (array) $row,
                $tenant->table('migrations')->get()->all(),
            ),
        ];
    }

    /**
     * A copy only serves while the migration files are the same, so a test
     * that adds a migration gets a database built by migrations again.
     */
    private static function key(): string
    {
        $path = config('tenancy.migrations_path');

        return $path.'|'.implode(',', array_map('basename', glob($path.'/*.php') ?: []));
    }
}
