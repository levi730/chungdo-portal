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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name', 255);
            $table->string('address1', 100)->nullable()->default(null);
            $table->string('address2', 100)->nullable()->default(null);
            $table->string('city', 100)->nullable()->default(null);
            $table->string('state', 10)->nullable()->default(null);
            $table->string('zip', 15)->nullable()->default(null);
            $table->string('phone', 50)->nullable()->default(null);
            $table->string('email', 255)->nullable()->default(null);
            $table->string('url', 100)->nullable()->default(null);
            $table->string('shortname', 50)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
