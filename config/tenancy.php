<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant database prefix
    |--------------------------------------------------------------------------
    |
    | Every tenant has its own database, named with this prefix followed by
    | the company acronym in lower case: the acronym DC gives rasahub_dc.
    | The database user needs full rights on databases that match it: see
    | docker-local/mysql/init/02-tenant-databases.sh.
    |
    */

    'database_prefix' => env('TENANT_DATABASE_PREFIX', env('DB_DATABASE', 'rasahub').'_'),

    /*
    |--------------------------------------------------------------------------
    | Reserved acronyms
    |--------------------------------------------------------------------------
    |
    | Acronyms no tenant may have, because the database named after them is
    | used for something else: rasahub_testing is the test suite's.
    |
    */

    'reserved_acronyms' => ['TESTING'],

    /*
    |--------------------------------------------------------------------------
    | Tenant migrations
    |--------------------------------------------------------------------------
    |
    | Migrations in database/migrations build the central database. Those in
    | this directory build each tenant's database.
    |
    */

    'migrations_path' => database_path('migrations/tenant'),

    /*
    |--------------------------------------------------------------------------
    | Registration defaults
    |--------------------------------------------------------------------------
    |
    | A tenant that registers with an invitation starts with these. Its
    | Owner can change them afterwards under Business settings.
    |
    */

    'registration' => [
        'currency' => 'MYR',
        'timezone' => 'Asia/Kuala_Lumpur',
    ],

];
