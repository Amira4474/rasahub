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
        // Running numbers: order numbers per branch per business day, and
        // receipt numbers per branch.
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('value');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Also the idempotency key, so a retried request never makes a
            // second order.
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('number');
            // The number as shown, with the branch code: BGR-0042.
            $table->string('number_label', 48);
            $table->unsignedBigInteger('receipt_number')->nullable();
            $table->string('type', 16);
            $table->string('source', 16)->default('pos');
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('table_label', 32)->nullable();
            $table->unsignedSmallInteger('guests')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // Order and payment status are kept apart.
            $table->string('status', 16);
            $table->string('payment_status', 24);
            $table->text('note')->nullable();

            // The tax settings the order is sold under, kept with it so that
            // later changes never alter it.
            $table->boolean('sst_enabled');
            $table->decimal('sst_rate', 5, 2);
            $table->boolean('prices_include_sst');
            $table->decimal('service_charge_rate', 5, 2);
            $table->boolean('round_to_five_sen');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('sst', 12, 2)->default(0);
            $table->decimal('rounding', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);

            $table->unsignedBigInteger('opened_by');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'business_date', 'number']);
            $table->unique(['branch_id', 'receipt_number']);
            $table->index(['branch_id', 'status']);
            $table->index('business_date');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // A part of a set meal points to the set's line.
            $table->foreignId('parent_id')->nullable()->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Copies taken at the moment of sale.
            $table->string('name');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('modifiers_total', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->text('note')->nullable();
            // pending until sent to the kitchen, then sent; the kitchen
            // display takes it further in v0.5.0.
            $table->string('kitchen_status', 16)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name');
            $table->string('name');
            $table->decimal('price_delta', 12, 2);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Also the idempotency key: a payment sent twice is recorded once.
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method_name');
            $table->string('method_type', 16);
            $table->decimal('amount', 12, 2);
            // For cash: what the customer handed over, and the change.
            $table->decimal('tendered', 12, 2)->nullable();
            $table->decimal('change', 12, 2)->nullable();
            $table->string('reference', 64)->nullable();
            $table->unsignedBigInteger('received_by');
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('sequences');
    }
};
