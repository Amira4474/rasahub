<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renaming the row, rather than letting the seeder create a new role,
     * keeps the role on the users who already hold it.
     */
    public function up(): void
    {
        $this->rename('Super Admin', 'Platform Super Admin');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rename('Platform Super Admin', 'Super Admin');
    }

    private function rename(string $from, string $to): void
    {
        if (DB::table('roles')->where('name', $to)->exists()) {
            return;
        }

        DB::table('roles')->where('name', $from)->update(['name' => $to]);
    }
};
