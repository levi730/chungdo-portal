<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items, written when the order is created (before the charge) and never
 * rewritten. Names and prices are snapshotted so editing a product later cannot
 * rewrite what someone was charged — and so the pick list and the financials
 * export read from one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_order_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('product_order_id');
            $table->unsignedBigInteger('product_id');
            // Nullable: a variant may be removed from the catalog later. The
            // snapshot below is what the order actually was.
            $table->unsignedBigInteger('product_variant_id')->nullable();

            // Which print run this line belongs to. The variant implies it, but
            // it is recorded here too: product_variant_id is nullable, and the
            // pick list groups by run — that query should not have to reach
            // through a row that may be gone. Goods arrive per run, so an order
            // spanning two products can legitimately span two arrival dates,
            // which is why this is on the line and not on the order.
            $table->unsignedBigInteger('product_run_id')->nullable();

            $table->string('product_name');
            $table->string('variant_name');
            $table->decimal('unit_price', 8, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 8, 2);                 // unit_price * quantity

            $table->index('product_order_id');
            $table->index('product_id');
            $table->index('product_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
    }
};
