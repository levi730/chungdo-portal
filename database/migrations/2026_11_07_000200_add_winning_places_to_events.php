<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many places count as a win, which varies by event. Scoring ignores it.
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('winning_places')->default(3)->after('highlight_order');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('winning_places');
        });
    }
};
