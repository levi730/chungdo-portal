<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A row means the trophy was handed over. Who is owed one is worked out
     * from the results, so it is never stored.
     */
    public function up(): void
    {
        Schema::create('participation_trophies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_registration_id')->unique();
            $table->foreign('event_registration_id')->references('id')->on('event_registrations')->cascadeOnDelete();
            $table->foreignId('given_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_trophies');
    }
};
