<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // An invitation is now just a token that a platform user chooses,
        // such as OCT_RH. The person registering enters it, then their own
        // business details, email address and plan.
        Schema::table('tenant_invitations', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['email', 'plan', 'token_hash']);
            // Stored in capitals. Nullable only so that this migration can
            // add it to rows that already exist.
            $table->string('token', 32)->nullable()->after('id');
            // Who the token was made for, as a reminder to platform users.
            $table->string('note')->nullable()->after('token');
        });

        // Earlier invitations were links whose token was never stored, so
        // they cannot carry over. They get a placeholder and are revoked.
        DB::table('tenant_invitations')->update([
            'token' => DB::raw("CONCAT('OLD-', id)"),
            'revoked_at' => DB::raw('COALESCE(accepted_at, revoked_at, NOW())'),
        ]);

        Schema::table('tenant_invitations', function (Blueprint $table) {
            $table->unique('token');
        });

        // A tenant that has just registered has no dates until a platform
        // user activates the subscription.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('starts_at')->nullable()->change();
            $table->date('ends_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('starts_at')->nullable(false)->change();
            $table->date('ends_at')->nullable(false)->change();
        });

        Schema::table('tenant_invitations', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'note']);
            $table->string('email')->default('')->after('id');
            $table->string('plan', 32)->default('starter')->after('email');
            $table->string('token_hash', 64)->nullable()->unique()->after('plan');
            $table->index('email');
        });
    }
};
