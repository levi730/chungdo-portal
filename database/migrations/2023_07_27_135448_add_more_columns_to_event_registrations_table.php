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
        Schema::table('event_registrations_deleted', function (Blueprint $table) {
            $table->decimal('donation_amount')->nullable();
            $table->string('potluck_item', 50)->nullable();
            $table->json('volunteer_selections')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations_deleted', function (Blueprint $table) {
            $table->dropColumn(['donation_amount', 'potluck_item', 'volunteer_selections']);
        });
    }
};
