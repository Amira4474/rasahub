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
        // Where food and drink are made: Kitchen, Bar, Dessert.
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // A category sends its products to a station; a product can choose
        // another.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('parent_id')->constrained()->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });

        // The station each line went to, kept with the line.
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('station_name')->nullable()->after('station_id');
            $table->timestamp('ready_at')->nullable()->after('sent_at');
        });

        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // receipt, kitchen or bar.
            $table->string('role', 16);
            // A network thermal printer at a fixed address.
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(9100);
            $table->unsignedTinyInteger('paper_width')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Which printer prints each station's tickets.
        Schema::create('printer_station', function (Blueprint $table) {
            $table->foreignId('printer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();

            $table->primary(['printer_id', 'station_id']);
        });

        Schema::table('terminals', function (Blueprint $table) {
            $table->foreignId('receipt_printer_id')->nullable()->after('branch_id')->constrained('printers')->nullOnDelete();
        });

        // A screen in the kitchen showing chosen stations' orders. A device
        // of its own, apart from POS terminals.
        Schema::create('kitchen_screens', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Station ids; empty for every station.
            $table->json('station_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Something to print, described without depending on a printer
        // brand, waiting for the branch's print gateway.
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_id')->constrained()->cascadeOnDelete();
            // receipt, kitchen_ticket, void_ticket, refund_slip, shift_report or test.
            $table->string('type', 24);
            $table->string('reference')->nullable();
            $table->json('document');
            // queued, printing, printed or failed.
            $table->string('status', 16)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::table('branches', function (Blueprint $table) {
            // Kitchen screens show an order amber, then red, after this long.
            $table->unsignedSmallInteger('kitchen_amber_minutes')->default(10)->after('business_day_starts_at');
            $table->unsignedSmallInteger('kitchen_red_minutes')->default(15)->after('kitchen_amber_minutes');
            // The print gateway's key, stored hashed, and when it last called.
            $table->string('gateway_key')->nullable()->after('kitchen_red_minutes');
            $table->timestamp('gateway_seen_at')->nullable()->after('gateway_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['kitchen_amber_minutes', 'kitchen_red_minutes', 'gateway_key', 'gateway_seen_at']);
        });
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('kitchen_screens');
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_printer_id');
        });
        Schema::dropIfExists('printer_station');
        Schema::dropIfExists('printers');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
            $table->dropColumn(['station_name', 'ready_at']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
        Schema::dropIfExists('stations');
    }
};
