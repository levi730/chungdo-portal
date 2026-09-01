<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\ProductRun;
use App\Models\ProductVariant;

/**
 * One line of the cart: a variant and how many.
 *
 * Prices are read from the variant rather than stored in the session, so a cart
 * left open across a price edit always shows and charges the current price.
 * The snapshot happens later, on product_order_items, at the moment the order
 * is written.
 */
class CartLine
{
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $quantity,
    ) {}

    public function run(): ProductRun
    {
        return $this->variant->run;
    }

    public function product(): Product
    {
        return $this->variant->run->product;
    }

    public function unitPrice(): float
    {
        return (float) $this->variant->price;
    }

    public function amount(): float
    {
        return round($this->unitPrice() * $this->quantity, 2);
    }

    /** "Adult Hoodie / L" — the variant's display name. */
    public function label(): string
    {
        return $this->variant->displayName();
    }
}
