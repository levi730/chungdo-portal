<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Members have never had a phone number.
 *
 * `User::getPhoneAttribute()` has always read a `phone` attribute off the member
 * or their guardian, but no such column existed, so `getAttrFromParent()` fell
 * straight through to null for everyone. The Phone line on every registration
 * card printed blank, and the portal had no way to reach anybody but by email.
 *
 * With the column in place that accessor does what it was written to do: a
 * dependent with no number of their own answers with their guardian's, which is
 * the number you actually want when a child is in a ring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
