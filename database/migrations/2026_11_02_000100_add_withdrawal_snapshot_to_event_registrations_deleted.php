<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An admin can now withdraw a paid registrant (App\Services\RegistrationWithdrawal).
 * Deleting the registration row cascades its event_registration_addons away,
 * and those rows are the itemization of money that really moved — the
 * financials export builds its charge rows from them. So the archive keeps a
 * snapshot of the lines, and the original id they hung off, instead of letting
 * the charge row vanish from the ledger while its refund row stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations_deleted', function (Blueprint $table) {
            // The event_registrations.id this row was, for anything that still
            // points at it (refunds, addon_change_requests, division versions).
            $table->unsignedBigInteger('registration_id')->nullable()->after('id');
            // The event_registration_addons rows at the moment of removal:
            // [{type, selected, quantity, value, amount, payment_id, data}, ...]
            $table->json('addon_answers')->nullable()->after('docs_printed');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->after('addon_answers');
            // Why it went: 'withdrawn' (admin refund) or 'void' (cash envelope
            // entered by mistake). Older rows predate this and are null.
            $table->string('reason', 32)->nullable()->after('deleted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations_deleted', function (Blueprint $table) {
            $table->dropColumn(['registration_id', 'addon_answers', 'deleted_by_user_id', 'reason']);
        });
    }
};
