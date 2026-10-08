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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64)->index();
            // Who did it. Kept as null if the user is later removed.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The business it happened in, if any.
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            // True when a platform user did it from inside a tenant.
            $table->boolean('super_impose')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
