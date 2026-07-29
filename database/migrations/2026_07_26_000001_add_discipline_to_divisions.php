<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Combined tournaments run two independent division sets — sparring and
 * forms/kata. Tag each division and version with its discipline, give events a
 * second published pointer for the forms set, and give each registration a
 * second division assignment for forms. Existing data is all sparring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_divisions', function (Blueprint $table) {
            $table->string('discipline')->default('sparring')->after('event_id');
        });

        Schema::table('event_division_versions', function (Blueprint $table) {
            $table->string('discipline')->default('sparring')->after('event_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('published_forms_version_id')->nullable()->after('published_version_id');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('forms_event_division_id')->nullable()->after('event_division_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_divisions', fn (Blueprint $table) => $table->dropColumn('discipline'));
        Schema::table('event_division_versions', fn (Blueprint $table) => $table->dropColumn('discipline'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('published_forms_version_id'));
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn('forms_event_division_id'));
    }
};
