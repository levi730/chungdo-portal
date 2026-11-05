<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per scored division. The division's name and order are
        // copied here because results are a permanent record and the board
        // that produced them is not.
        Schema::create('division_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_division_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('discipline');
            $table->string('division_name');
            $table->unsignedInteger('sort_order')->default(0);
            // Null while reopened for a correction: scored, but not shown.
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
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

        // Off by default: results stay behind sign-in until someone decides.
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('results_public')->default(false)->after('published_forms_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('results_public'));
        Schema::dropIfExists('division_placements');
        Schema::dropIfExists('division_results');
    }
};
