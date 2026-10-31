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
        // The ways a restaurant takes payment. Card and QR payments happen
        // on the restaurant's own terminal or QR code; RasaHub records them.
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            // cash, card, qr or other. Only cash goes into the drawer.
            $table->string('type', 16);
            // For a QR method: the QR code customers scan, shown on the POS.
            $table->string('qr_image_path')->nullable();
            $table->boolean('requires_reference')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // Digits only, so the same number typed two ways is one customer.
            $table->string('phone', 20)->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->date('birthday')->nullable();
            $table->text('notes')->nullable();
            // Malaysia's PDPA: only contact customers who agreed to it.
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('payment_methods');
    }
};
