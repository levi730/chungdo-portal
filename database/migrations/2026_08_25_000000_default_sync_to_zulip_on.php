<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everyone is synced to Zulip now (committee membership drives group
     * membership, so all users must be eligible). Backfill existing rows and
     * default new users to synced.
     */
    public function up(): void
    {
        DB::table('users')->update(['sync_to_zulip' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sync_to_zulip')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sync_to_zulip')->default(false)->change();
        });
    }
};
