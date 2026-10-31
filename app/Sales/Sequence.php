<?php

namespace App\Sales;

use App\Tenancy\TenantDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Running numbers in the tenant's database, such as order numbers per
 * branch per day. Each call returns the next number, once, even when two
 * terminals ask at the same moment.
 */
class Sequence
{
    public static function next(string $key): int
    {
        app(TenantDatabase::class)->useCurrent();

        $connection = DB::connection(TenantDatabase::CONNECTION);

        // MySQL hands the new value back through LAST_INSERT_ID() on the
        // same connection, in one atomic statement.
        $connection->statement(
            'INSERT INTO sequences (`key`, `value`) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE `value` = LAST_INSERT_ID(`value` + 1)',
            [$key],
        );

        return (int) $connection->selectOne('SELECT LAST_INSERT_ID() AS value')->value;
    }
}
