<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The registry of which add-ons an event is using, and each add-on's
 * configuration. Replaces the flag columns previously smeared across the
 * `events` table (potluck, donations, ask_volunteers, tshirt_deadline, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_addons', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // One of the registered add-on type keys (registration_fee, donation,
            // potluck, meal_ticket, tshirt, volunteer, guests, ...).
            $table->string('type');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // Add-on specific configuration, e.g. meal_ticket => {price, label}.
            $table->json('settings')->nullable();

            $table->unique(['event_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_addons');
    }
};
