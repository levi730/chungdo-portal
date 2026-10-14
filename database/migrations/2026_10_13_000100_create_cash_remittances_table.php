<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registrations paid in cash or check, collected by an instructor.
 *
 * One remittance is one envelope: the instructor registers a batch of students,
 * the registrations exist immediately, and the remittance records what the
 * instructor owes the event for them. Marking it received is what creates the
 * Payment row — until then the registrations carry amount_paid = 0 and point
 * here through event_registrations.cash_remittance_id.
 *
 * payments.method separates this money from Stripe's, so the financials export
 * can still be reconciled against the Stripe dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_remittances', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('event_id')->constrained();
            // Assigned after insert from the id, so it is never reused.
            $table->string('reference', 20)->nullable()->unique();
            // The instructor on the hook for turning the money in.
            $table->foreignId('responsible_user_id')->constrained('users');
            // Who typed it in — the same person unless an admin entered it for them.
            $table->foreignId('created_by_user_id')->constrained('users');
            // Snapshots, so moving the event to another account later cannot
            // change who an envelope already in someone's hand is payable to.
            $table->string('stripe_account', 50);
            $table->string('payee');
            $table->decimal('amount_due', 10, 2);
            $table->string('status', 20)->default('outstanding')->index();
            $table->text('note')->nullable();
            // What was registered, as submitted: the slip and the audit trail.
            $table->json('payload');

            $table->decimal('amount_received', 10, 2)->nullable();
            $table->string('received_method', 10)->nullable();
            $table->string('check_numbers')->nullable();
            $table->text('deposit_note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users');
            $table->foreignId('payment_id')->nullable()->constrained('payments');

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users');
            $table->text('void_reason')->nullable();
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('cash_remittance_id')->nullable()->after('payment_id')->index();
        });

        Schema::table('event_registrations_deleted', function (Blueprint $table) {
            $table->foreignId('cash_remittance_id')->nullable()->after('payment_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            // stripe | cash | check | mixed. Every existing row came from Stripe.
            $table->string('method', 10)->default('stripe')->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('method'));
        Schema::table('event_registrations_deleted', fn (Blueprint $table) => $table->dropColumn('cash_remittance_id'));
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn('cash_remittance_id'));
        Schema::dropIfExists('cash_remittances');
    }
};
