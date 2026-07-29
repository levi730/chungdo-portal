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
            $table->foreignId('payment_id')->nullable();
            $table->string('tshirt_size', 5)->nullable();
            $table->timestamp('checkin')->nullable();
            $table->foreignId('event_division_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations_deleted', function (Blueprint $table) {
            $table->dropColumn('payment_id');
            $table->dropColumn('tshirt_size');
            $table->dropColumn('checkin');
            $table->dropColumn('event_division_id');
        });
    }
};
