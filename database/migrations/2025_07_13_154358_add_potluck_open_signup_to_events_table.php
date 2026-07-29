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
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('potluck_open_signup')->default(false);
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('potluck_open_item')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('potluck_open_signup');
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('potluck_open_item');
        });
    }
};
