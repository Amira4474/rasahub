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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            // "single" for an item on its own, "set" for a set meal.
            $table->string('type', 16)->default('single');
            $table->string('name');
            $table->string('sku', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'sort_order']);
        });

        Schema::create('modifier_group_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'modifier_group_id']);
        });

        // A product's availability and price at one branch. Without a row,
        // the product is available there at its own price.
        Schema::create('branch_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        Schema::create('product_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Null for the product's own price, set for a branch price.
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2)->nullable();
            // Users live in the central database, so this is not a foreign key.
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at');

            $table->index(['product_id', 'changed_at']);
        });

        // The choices that make up a set meal, such as "Choose a drink".
        Schema::create('set_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('min_select')->default(1);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('set_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('extra_price', 12, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('set_group_items');
        Schema::dropIfExists('set_groups');
        Schema::dropIfExists('product_price_changes');
        Schema::dropIfExists('branch_product');
        Schema::dropIfExists('modifier_group_product');
        Schema::dropIfExists('products');
    }
};
