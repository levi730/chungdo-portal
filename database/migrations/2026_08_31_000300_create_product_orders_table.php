<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A store order. This row IS the pending record: it is written before the card
 * is charged, so the outcome can be completed exactly once from either the
 * synchronous response or the webhook — the same rule as
 * pending_event_registrations (docs/payment-flow-pattern.md).
 *
 * `status` is the payment lifecycle; `fulfillment_status` is the physical
 * hand-over, which happens days later at a school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->uuid('reference')->unique();               // public order number
            $table->string('status')->default('pending');      // pending | paid | failed | cancelled
            $table->string('fulfillment_status')->default('awaiting'); // awaiting | ready | collected

            // Snapshot of the account charged. Every product in one order must
            // share it — a charge lands in exactly one account.
            $table->string('stripe_account', 50);
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable();

            // Null user_id = guest checkout. Email and name are always captured.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email');
            $table->string('name');
            $table->string('phone')->nullable();

            // Pickup only for v1. Shipping later adds fulfillment_method +
            // an address block + a shipping amount; nothing here changes.
            $table->unsignedBigInteger('pickup_school_id')->nullable()->index();

            $table->decimal('subtotal', 8, 2)->default(0);
            // Tax is undecided (a nonprofit may or may not collect it on
            // merchandise) but the column ships now, at zero, because adding it
            // after money has moved means touching financial records. Whatever
            // the answer turns out to be, it becomes configuration rather than a
            // migration. total = subtotal + tax.
            $table->decimal('tax', 8, 2)->default(0);
            $table->decimal('total', 8, 2)->default(0);        // subtotal + tax; shipping later
            $table->decimal('amount_paid', 8, 2)->nullable();  // actual captured
            $table->decimal('refunded_amount', 8, 2)->default(0);
            $table->string('stripe_refund_id')->nullable();

            $table->json('payload');                           // full snapshot needed to fulfill
            $table->text('admin_note')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('collected_at')->nullable();

            // The reconcile sweep looks for stale pending rows.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
