<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A registrant's requested add-on change that reduces their total (a refund).
 * Held for admin approval — nothing changes on the registration until an admin
 * approves (apply new state + issue Stripe refund) or denies (discard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_change_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('event_id');
            $table->foreignId('event_registration_id');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            // The desired new per-add-on state to apply on approval.
            $table->json('new_state');
            $table->decimal('refund_amount', 8, 2)->default(0); // computed; admin-editable
            $table->string('stripe_payment_intent_id')->nullable(); // PI to refund against
            $table->string('status')->default('pending'); // pending | approved | denied
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('stripe_refund_id')->nullable();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_change_requests');
    }
};
