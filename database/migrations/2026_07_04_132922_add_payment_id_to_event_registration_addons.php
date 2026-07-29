<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_registration_addons', function (Blueprint $table) {
            // Which payment charged this answer's amount, so a later reduction
            // can be refunded against the correct PaymentIntent.
            $table->unsignedBigInteger('payment_id')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_addons', function (Blueprint $table) {
            $table->dropColumn('payment_id');
        });
    }
};
