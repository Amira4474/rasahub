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
        // The random part of each table's QR code link. A new one makes the
        // printed code stop working.
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('ulid');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('qr_ordering_enabled')->default(true)->after('kitchen_red_minutes');
            $table->boolean('qr_auto_confirm')->default(false)->after('qr_ordering_enabled');
        });

        // A QR order has no terminal and no member of staff until it is
        // confirmed at the POS.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->change();
            $table->unsignedBigInteger('opened_by')->nullable()->change();
            $table->string('reject_reason')->nullable()->after('cancel_reason');
        });

        // A customer at a table calling staff or asking for the bill.
        Schema::create('table_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained()->cascadeOnDelete();
            // call_staff or bill.
            $table->string('type', 16);
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'resolved_at']);
        });

        // How the restaurant's QR ordering page looks.
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->string('qr_logo_path')->nullable();
            $table->string('qr_banner_path')->nullable();
            $table->string('qr_heading')->nullable();
            $table->text('qr_welcome_text')->nullable();
            $table->string('qr_primary_color', 7)->default('#DC4A2D');
            $table->string('qr_background_color', 7)->default('#FBF3E7');
            $table->text('qr_footer_text')->nullable();
            $table->boolean('qr_show_descriptions')->default(true);
            $table->boolean('qr_show_photos')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn(['qr_logo_path', 'qr_banner_path', 'qr_heading', 'qr_welcome_text', 'qr_primary_color', 'qr_background_color', 'qr_footer_text', 'qr_show_descriptions', 'qr_show_photos']);
        });
        Schema::dropIfExists('table_requests');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['qr_ordering_enabled', 'qr_auto_confirm']);
        });
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
