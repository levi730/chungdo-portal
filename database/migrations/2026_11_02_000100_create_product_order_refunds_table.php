<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per refund issued on a store order.
 *
 * docs/store-design.md ruled out extending the event-scoped `refunds` table
 * (event_id is non-nullable and the table is wired to AddonChangeRequest; a
 * nullable column there would put an untested branch under live event money).
 * The order itself keeps `refunded_amount` as the running total and
 * `stripe_refund_id` as the latest id, but an order can be refunded more than
 * once — one shirt now, the other later — and the financials export needs each
 * refund as its own dated row, so the history lives here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_order_refunds', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('product_order_id')->index();
            $table->unsignedBigInteger('payment_id')->nullable();      // the Payment (charge) refunded against
            $table->string('stripe_account', 50);                      // the account the refund was issued on
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_refund_id')->nullable();
            $table->decimal('amount', 8, 2);
            $table->unsignedBigInteger('refunded_by_user_id')->nullable();
            $table->text('admin_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_refunds');
    }
};
