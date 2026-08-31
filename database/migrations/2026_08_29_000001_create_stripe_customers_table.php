<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Stripe customer id is scoped to one Stripe account, so users.stripe_id
 * (written by Cashier for the association account) cannot be reused on any
 * other account. This maps a portal user to their customer id per account.
 *
 * The association row is seeded lazily from users.stripe_id the first time a
 * user is resolved, so existing customers carry over untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_customers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('user_id');
            $table->string('account', 50);              // key in config services.stripe.accounts
            $table->string('stripe_customer_id');

            $table->unique(['user_id', 'account']);
            $table->index('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_customers');
    }
};
