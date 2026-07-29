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
        Schema::create('school_instructors', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('school_id');
            $table->foreignId('user_id');
            $table->boolean('principal')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_instructors');
    }
};
