<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // What staff type first when signing in to the POS.
            $table->string('acronym', 10)->nullable()->unique()->after('slug');
        });

        // Existing businesses need a value before the column becomes required.
        DB::table('businesses')->whereNull('acronym')->update(['acronym' => DB::raw("CONCAT('B', id)")]);

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('acronym', 10)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['acronym']);
            $table->dropColumn('acronym');
        });
    }
};
