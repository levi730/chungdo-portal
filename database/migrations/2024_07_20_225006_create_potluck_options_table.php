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
        Schema::create('potluck_options', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('event_id');
            $table->string('category');
            $table->string('item');
            $table->string('limit');
            $table->integer('current_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('potluck_options');
    }
};
