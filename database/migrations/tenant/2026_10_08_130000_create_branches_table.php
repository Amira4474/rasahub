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
        // No business_id: the whole database belongs to one business.
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('code', 32)->unique();
            // Null means "use the business timezone".
            $table->string('timezone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
