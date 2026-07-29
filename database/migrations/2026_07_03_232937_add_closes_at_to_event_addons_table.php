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
        Schema::table('event_addons', function (Blueprint $table) {
            // The drop-dead date for this add-on: after it passes, the add-on no
            // longer accepts sign-ups or changes (registration itself may still
            // be open). Null means no deadline.
            $table->dateTime('closes_at')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('event_addons', function (Blueprint $table) {
            $table->dropColumn('closes_at');
        });
    }
};
