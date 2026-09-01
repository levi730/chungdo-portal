<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The payments table is the portal's money ledger and store orders belong in it
 * alongside registrations, so store and event money reconcile against Stripe in
 * one place.
 *
 * Two changes: guests have no user, so user_id has to be nullable (it is a
 * plain unsigned bigint — there is no foreign key constraint to drop); and an
 * order needs a link back, mirroring how registrations carry payment_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('product_order_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['product_order_id']);
            $table->dropColumn('product_order_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
