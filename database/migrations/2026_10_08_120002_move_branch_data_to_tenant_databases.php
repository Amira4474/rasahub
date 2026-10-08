<?php

use App\Tenancy\TenantDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every existing business a database of its own, copy its branches,
     * POS terminals and branch assignments into it, and then remove those
     * tables from the central database.
     */
    public function up(): void
    {
        $tenants = app(TenantDatabase::class);
        $central = DB::connection()->getDatabaseName();

        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $tenants->create($businessId);

            $tenant = $tenants->nameFor($businessId);

            DB::statement(
                "INSERT INTO `{$tenant}`.`branches` (id, ulid, name, code, timezone, is_active, created_at, updated_at, deleted_at)
                 SELECT id, ulid, name, code, timezone, is_active, created_at, updated_at, deleted_at
                 FROM `{$central}`.`branches` WHERE business_id = ?",
                [$businessId],
            );

            DB::statement(
                "INSERT INTO `{$tenant}`.`terminals` (id, ulid, branch_id, name, code, is_active, last_seen_at, created_at, updated_at, deleted_at)
                 SELECT t.id, t.ulid, t.branch_id, t.name, t.code, t.is_active, t.last_seen_at, t.created_at, t.updated_at, t.deleted_at
                 FROM `{$central}`.`terminals` t
                 JOIN `{$central}`.`branches` b ON b.id = t.branch_id
                 WHERE b.business_id = ?",
                [$businessId],
            );

            DB::statement(
                "INSERT INTO `{$tenant}`.`branch_user` (branch_id, user_id, created_at, updated_at)
                 SELECT bu.branch_id, bu.user_id, bu.created_at, bu.updated_at
                 FROM `{$central}`.`branch_user` bu
                 JOIN `{$central}`.`branches` b ON b.id = bu.branch_id
                 WHERE b.business_id = ?",
                [$businessId],
            );
        }

        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('branches');
    }

    /**
     * The data now lives in one database per tenant and cannot be merged
     * back automatically.
     */
    public function down(): void
    {
        throw new RuntimeException('Moving tenant data back into the central database is not supported.');
    }
};
