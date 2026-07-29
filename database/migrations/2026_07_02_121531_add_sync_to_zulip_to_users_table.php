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
        Schema::table('users', function (Blueprint $table) {
            // Whether this user participates in the portal -> Zulip sync
            // (created in Zulip if missing, belt rank + group memberships pushed).
            $table->boolean('sync_to_zulip')->default(false)->index()->after('mailings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sync_to_zulip');
        });
    }
};
