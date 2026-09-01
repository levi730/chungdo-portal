<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A print run of a product — see docs/store-design.md.
 *
 * The same design is printed more than once, so the ordering window is not a
 * property of the design. Each run opens, closes, and arrives on its own dates,
 * and owns its own variants: vendor costs move between runs and a run has to
 * keep the price list it actually sold at.
 *
 * Only one run of a product may be open at a time (enforced in ProductRunRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('product_id');
            $table->string('name');                          // "Fall 2026"

            $table->timestamp('opens_at')->nullable();       // null = already open
            $table->timestamp('closes_at')->nullable();      // null = no deadline yet
            // What the buyer is told to expect. A date, not a promise — the
            // print shop's estimate, shown on the order confirmation.
            $table->date('expected_arrival_at')->nullable();

            $table->string('pickup_note')->nullable();       // "Pick up at your school after Oct 15"
            $table->unsignedInteger('sort_order')->default(0);

            // The store asks "is a run of this product open right now" on every
            // page that lists products.
            $table->index(['product_id', 'opens_at', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_runs');
    }
};
