<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per scored division of a published version. A version's
        // divisions have no ids of their own, so one is named by its position
        // in the version's data, which never changes. Publishing a different
        // version removes these rows, so only the published version has any.
        Schema::create('division_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_division_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('discipline');
            $table->string('division_name');
            // Null while reopened for a correction: scored, but not shown.
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_division_version_id', 'position']);
        });

        // A place may repeat (two thirds), and there is no ceiling on it: four
        // places is the norm, but a division can number everyone. The
        // competitor's details are as they were that day, and survive the
        // registration being withdrawn.
        Schema::create('division_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('place');
            $table->string('name');
            $table->string('school')->nullable();
            $table->string('rank')->nullable();
            $table->timestamps();

            $table->unique(['division_result_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_placements');
        Schema::dropIfExists('division_results');
    }
};
