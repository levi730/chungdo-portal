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
            $table->integer('registering_user_id')->nullable();
            $table->integer('docs_printed')->default(0);
            $table->string('potluck_open_item')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations_deleted', function (Blueprint $table) {

            $table->dropColumn('potluck_open_item', 'docs_printed', 'registering_user_id');
        });
    }
};
