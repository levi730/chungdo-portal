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
        Schema::table('event_division_versions', function (Blueprint $table) {
            $table->boolean('starred')->default(false)->after('data');
        });

        // Plain nullable column (no FK) — the versions cascade-delete with their
        // event, and the app never deletes a version out from under this pointer,
        // so a DB constraint here would only add a cyclic-FK headache.
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('published_version_id')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('event_division_versions', function (Blueprint $table) {
            $table->dropColumn('starred');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('published_version_id');
        });
    }
};
