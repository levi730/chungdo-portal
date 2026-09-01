<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise sold independently of event registration — see docs/store-design.md.
 *
 * A product is the DESIGN and nothing time-bound: the artwork, its description,
 * its images, the Stripe account its money lands in. Ordering windows live on
 * product_runs, because one design is printed more than once. There is no stock
 * count anywhere — a run is sized from the orders it took.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();

            $table->string('name');
            $table->string('slug')->unique();               // /store/{slug}
            $table->string('stripe_account', 50)->default('association');
            $table->string('status')->default('draft');     // draft | active | archived
            $table->text('description')->nullable();        // markdown
            $table->json('option_names')->nullable();       // ["Item","Size"]

            // Dates, pickup wording and the variants all belong to a run, not
            // here. max_per_order is product-level policy, so it stays.
            $table->unsignedInteger('max_per_order')->nullable();

            // Home page highlighting, same shape as events: a higher
            // highlight_order sits higher, so the default 0 is the baseline.
            $table->boolean('highlighted')->default(false);
            $table->unsignedSmallInteger('highlight_order')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['status', 'highlighted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
