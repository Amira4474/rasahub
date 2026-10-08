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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            // One subscription per business.
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 32);
            $table->date('starts_at');
            // The last day the subscription is valid.
            $table->date('ends_at');
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamps();
        });

        // Businesses that existed before subscriptions get one month on the
        // Starter plan, so nobody is locked out by this migration.
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            DB::table('subscriptions')->insert([
                'business_id' => $businessId,
                'plan' => 'starter',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonthNoOverflow()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
