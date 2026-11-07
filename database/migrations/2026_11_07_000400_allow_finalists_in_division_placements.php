<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A null place is a finalist: in the top two, order decided at finals.
        Schema::table('division_placements', function (Blueprint $table) {
            $table->unsignedSmallInteger('place')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('division_placements')->whereNull('place')->delete();

        Schema::table('division_placements', function (Blueprint $table) {
            $table->unsignedSmallInteger('place')->nullable(false)->change();
        });
    }
};
