<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept so a correction can edit the post it corrects.
        Schema::table('division_results', function (Blueprint $table) {
            $table->unsignedBigInteger('zulip_message_id')->nullable()->after('completed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('division_results', function (Blueprint $table) {
            $table->dropColumn('zulip_message_id');
        });
    }
};
