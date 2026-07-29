<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the intended fulfillment of a paid event registration BEFORE the card
 * is charged, so the outcome can be completed exactly once from either the
 * synchronous response or the payment_intent.succeeded webhook. This closes the
 * "charged but not registered" gap when the synchronous response is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('reference')->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->foreignId('event_id');
            $table->unsignedBigInteger('registering_user_id')->nullable();
            $table->decimal('amount', 8, 2)->default(0);       // expected total
            $table->decimal('amount_paid', 8, 2)->nullable();  // actual captured
            $table->string('status')->default('pending');      // pending | fulfilled | failed
            $table->json('payload');                           // everything needed to fulfill
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_event_registrations');
    }
};
