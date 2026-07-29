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
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('person_2_discount', 18, 2)->default(0);
            $table->decimal('person_3_discount', 18, 2)->default(0);
            $table->decimal('person_4_discount', 18, 2)->default(0);
            $table->decimal('person_5_discount', 18, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('person_5_discount');
            $table->dropColumn('person_4_discount');
            $table->dropColumn('person_3_discount');
            $table->dropColumn('person_2_discount');
        });
    }
};
