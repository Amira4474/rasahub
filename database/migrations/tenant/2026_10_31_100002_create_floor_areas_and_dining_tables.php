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
        // A part of a branch's dining room, such as Indoor or Patio.
        Schema::create('floor_areas', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'sort_order']);
        });

        // "tables" would read badly in code, so these are dining tables.
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('floor_area_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32);
            $table->unsignedTinyInteger('seats')->default(4);
            // square, round or rectangle.
            $table->string('shape', 16)->default('square');
            // Position on the floor plan, as a percentage of its width and
            // height, so it fits any screen.
            $table->unsignedTinyInteger('pos_x')->default(0);
            $table->unsignedTinyInteger('pos_y')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['floor_area_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('floor_areas');
    }
};
