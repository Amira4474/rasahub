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
        // A terminal's cash drawer session, from opening float to cash count.
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            // Set while the shift is open and cleared when it closes, so the
            // unique index allows one open shift per terminal.
            $table->unsignedBigInteger('open_on_terminal_id')->nullable()->unique();
            // Users are in the central database, so these are not foreign keys.
            $table->unsignedBigInteger('opened_by');
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 12, 2);
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();

            $table->index(['terminal_id', 'opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
