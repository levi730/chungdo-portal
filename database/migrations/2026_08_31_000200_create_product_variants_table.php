<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The buyable SKUs of a single RUN. `options` is a map keyed by the product's
 * option_names, so one run can span item x size without a column per axis.
 *
 * Variants hang off the run rather than the product because prices move between
 * runs and each run has to keep the list it actually sold at. A new run copies
 * the previous run's variants (ProductVariantSync::copy) rather than sharing
 * them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('product_run_id');
            $table->string('name');                        // display: "Adult Hoodie / L"
            $table->json('options')->nullable();           // {"Item":"Adult Hoodie","Size":"L"}
            $table->string('sku')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['product_run_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
