<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A money-out ledger of issued refunds — one row per approved refund, written
 * at the moment it is issued (see App\Services\RefundApprover). Because a refund
 * destroys/reduces the original add-on rows, the per-category `breakdown` is
 * snapshotted here at issue time; it cannot be reconstructed afterward. This
 * feeds the event Financials export's refund line items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('event_id');
            // The registration whose add-ons were reduced, and the person it belongs to.
            $table->foreignId('event_registration_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();      // who the refund is attributed to
            $table->unsignedBigInteger('refunded_to_user_id')->nullable(); // the payor who got the money back
            $table->unsignedBigInteger('addon_change_request_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();     // the original Payment (charge) refunded against
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_refund_id')->nullable();
            $table->decimal('amount', 8, 2);                          // actual amount refunded (may be < computed)
            $table->json('breakdown')->nullable();                    // {type: amount} snapshot of what was reduced
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->text('admin_note')->nullable();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
