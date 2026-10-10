<?php

use App\Tenancy\TenantDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tenant databases used to be named after the business ID, for example
     * rasahub_tenant_1. They are now named after the company acronym, for
     * example rasahub_dc. MySQL cannot rename a database, so each tenant's
     * tables are moved into a new one and the empty old one is removed.
     */
    public function up(): void
    {
        $central = DB::connection()->getDatabaseName();

        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $old = "{$central}_tenant_{$businessId}";
            $new = app(TenantDatabase::class)->nameFor($businessId);

            if (! $this->databaseExists($old)) {
                continue;
            }

            if ($this->databaseExists($new)) {
                throw new RuntimeException("Cannot move [{$old}] to [{$new}]: that database already exists.");
            }

            DB::statement("CREATE DATABASE `{$new}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $tables = DB::table('information_schema.tables')->where('table_schema', $old)->pluck('TABLE_NAME');

            if ($tables->isNotEmpty()) {
                // One statement, so the tables move together or not at all.
                DB::statement('RENAME TABLE '.$tables->map(
                    fn (string $table): string => "`{$old}`.`{$table}` TO `{$new}`.`{$table}`",
                )->implode(', '));
            }

            DB::statement("DROP DATABASE `{$old}`");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Tenant databases cannot be renamed back automatically.');
    }

    private function databaseExists(string $name): bool
    {
        return DB::table('information_schema.schemata')->where('schema_name', $name)->exists();
    }
};
