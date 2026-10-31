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
        Schema::table('restaurant_settings', function (Blueprint $table) {
            // How long after payment an order can be refunded.
            $table->unsignedSmallInteger('refund_days')->default(30)->after('round_to_five_sen');
        });

        // Discounts the restaurant offers, such as "Staff 20%".
        Schema::create('discount_presets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            // percent or amount.
            $table->string('type', 16);
            $table->decimal('value', 12, 2);
            // item or order: what it can be given on.
            $table->string('scope', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_total', 12, 2)->default(0)->after('subtotal');
            $table->decimal('refunded', 12, 2)->default(0)->after('paid');
            $table->text('cancel_reason')->nullable()->after('note');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancel_reason');
            $table->unsignedBigInteger('cancel_approved_by')->nullable()->after('cancelled_by');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_approved_by');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('discount_total', 12, 2)->default(0)->after('line_total');
            $table->unsignedSmallInteger('refunded_quantity')->default(0)->after('quantity');
            $table->timestamp('voided_at')->nullable()->after('sent_at');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->unsignedBigInteger('voided_by')->nullable()->after('void_reason');
            $table->unsignedBigInteger('void_approved_by')->nullable()->after('voided_by');
        });

        // A discount given on an order, or on one of its lines.
        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('discount_preset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 16);
            $table->decimal('value', 12, 2);
            // Worked out again whenever the order changes.
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('reason')->nullable();
            // Users are in the central database, so these are not foreign keys.
            $table->unsignedBigInteger('applied_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        // Money given back to a customer, always in cash for now.
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            // As printed on the refund slip: BGR-RF000001.
            $table->string('number_label', 48);
            $table->decimal('amount', 12, 2);
            $table->string('method_name');
            $table->text('reason');
            // The lines and quantities given back, if any.
            $table->json('items')->nullable();
            $table->unsignedBigInteger('refunded_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('order_discounts');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['discount_total', 'refunded_quantity', 'voided_at', 'void_reason', 'voided_by', 'void_approved_by']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_total', 'refunded', 'cancel_reason', 'cancelled_by', 'cancel_approved_by', 'cancelled_at']);
        });

        Schema::dropIfExists('discount_presets');

        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn('refund_days');
        });
    }
};
