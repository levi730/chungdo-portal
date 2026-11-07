<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set while 3rd is saved and 1st and 2nd wait for finals. The division
        // is on the results page but not complete, so completed_at stays null.
        Schema::table('division_results', function (Blueprint $table) {
            $table->timestamp('awaiting_finals_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('division_results', function (Blueprint $table) {
            $table->dropColumn('awaiting_finals_at');
        });
    }
};
