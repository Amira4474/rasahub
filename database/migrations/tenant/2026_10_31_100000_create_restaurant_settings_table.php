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
        // One row: the restaurant's tax, rounding and receipt settings. The
        // row is created with these defaults the first time it is read.
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('sst_enabled')->default(true);
            $table->decimal('sst_rate', 5, 2)->default(6.00);
            $table->boolean('prices_include_sst')->default(true);
            $table->string('sst_registration_number', 32)->nullable();
            // Bill totals to the nearest 5 sen, whatever the payment method.
            $table->boolean('round_to_five_sen')->default(true);
            $table->string('receipt_logo_path')->nullable();
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
