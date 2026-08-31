<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each event names the Stripe account its registration money lands in. Existing
 * events all transacted on the association's account, so they are backfilled to
 * that and it is the column default.
 *
 * The value is a key in config('services.stripe.accounts').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('stripe_account', 50)->default('association')->after('host_school_id');
        });

        DB::table('events')->update(['stripe_account' => 'association']);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('stripe_account');
        });
    }
};
