<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A registrant's answer to a single add-on. One row per (registration x addon).
 * Replaces the answer columns previously smeared across `event_registrations`
 * (tshirt_size, donation_amount, potluck_item_id, volunteer_selections, guests)
 * and removes the need to mirror them in `event_registrations_deleted`.
 *
 * Typed columns cover the common answer shapes so reporting stays plain SQL;
 * `data` is the escape hatch for anything irregular (e.g. volunteer picks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_addons', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_addon_id')->constrained()->cascadeOnDelete();
            // Denormalized copy of event_addons.type so answers can be grouped
            // without a join and survive if the addon config is later removed.
            $table->string('type');
            $table->boolean('selected')->default(false); // opted in? (meal yes/no, wants tshirt)
            $table->unsignedInteger('quantity')->nullable(); // additional meals, guest count
            $table->string('value')->nullable(); // tshirt size, potluck item
            $table->decimal('amount', 8, 2)->nullable(); // money contribution (fee, donation, meal)
            $table->json('data')->nullable(); // escape hatch (volunteer selections, etc.)

            $table->unique(['event_registration_id', 'event_addon_id'], 'era_reg_addon_unique');
            $table->index(['event_addon_id', 'type'], 'era_addon_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_addons');
    }
};
