<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schools are archived, never destroyed.
 *
 * users.school_id, school_instructors.school_id and
 * product_orders.pickup_school_id all point here, and none of them is a foreign
 * key with a defined behaviour on delete — removing a row would silently orphan
 * members, instructor records and the school someone chose to collect an order
 * from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
