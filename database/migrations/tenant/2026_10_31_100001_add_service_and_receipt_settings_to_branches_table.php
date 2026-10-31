<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Printed on the branch's receipts.
            $table->text('address')->nullable()->after('timezone');
            $table->string('phone', 32)->nullable()->after('address');
            // 0 means no service charge.
            $table->decimal('service_charge_rate', 5, 2)->default(0)->after('phone');
            $table->boolean('service_charge_dine_in_only')->default(true)->after('service_charge_rate');
            // A sale before this time belongs to the previous business day.
            $table->time('business_day_starts_at')->default('04:00:00')->after('service_charge_dine_in_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone', 'service_charge_rate', 'service_charge_dine_in_only', 'business_day_starts_at']);
        });
    }
};
