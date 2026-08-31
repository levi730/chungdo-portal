<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events flagged for the home page. When no upcoming event is highlighted the
 * dashboard falls back to the two soonest, which is what it did before this
 * existed — so nothing changes until someone ticks a box.
 *
 * highlight_order sorts the highlighted set (low first); ties and equal values
 * fall back to start date, so leaving it at 0 gives soonest-first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('highlighted')->default(false)->after('require_ticket');
            $table->unsignedSmallInteger('highlight_order')->default(0)->after('highlighted');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['highlighted', 'highlight_order']);
        });
    }
};
