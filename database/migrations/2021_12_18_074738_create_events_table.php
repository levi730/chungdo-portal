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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name', 255);
            $table->dateTime('startdatetime')->nullable()->default(null);
            $table->dateTime('enddatetime')->nullable()->default(null);
            $table->string('location', 255)->nullable()->default(null);
            $table->text('details')->nullable()->default(null);
            $table->decimal('cost', 18, 2)->default(0);
            $table->string('cost_type')->nullable()->default(null);
            $table->string('slug')->nullable()->unique()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
