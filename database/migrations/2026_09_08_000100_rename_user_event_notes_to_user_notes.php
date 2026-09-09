<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notes are about a person, not about an event.
 *
 * They split two ways. A permanent note describes the member and stays true
 * across events ("DEAF, with processing issues — extra care with center
 * reffing"). A temporary note is only true for one event ("cannot stay for
 * finals"), and showing it a year later is worse than not showing it at all.
 *
 * `scope` records that intent explicitly rather than inferring it from
 * `event_id`: a permanent note is still worth knowing the event it was written
 * at, so the flag and the context are separate facts.
 *
 * Every existing row becomes permanent. Nothing written before this migration
 * carries the distinction, and defaulting to permanent keeps every note
 * visible — the wrong guess in that direction shows a stale note, the other
 * direction hides a live one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('user_event_notes', 'user_notes');

        Schema::table('user_notes', function (Blueprint $table) {
            $table->string('scope', 20)->default('temporary')->after('event_id');
        });

        DB::table('user_notes')->update(['scope' => 'permanent']);
    }

    public function down(): void
    {
        Schema::table('user_notes', function (Blueprint $table) {
            $table->dropColumn('scope');
        });

        Schema::rename('user_notes', 'user_event_notes');
    }
};
