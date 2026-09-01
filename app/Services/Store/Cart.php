<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * The shopping cart, kept in the session.
 *
 * Nothing else in this portal keeps state in the session between requests, so
 * this sets the convention. It is the session rather than a database row on
 * purpose: guests have no user to hang a cart on, and a `pending` ProductOrder
 * written at add-to-cart time would be picked up by
 * ProductOrder::scopeStalePending and the reconcile sweep would spend its life
 * interrogating Stripe about abandoned carts. The order row is written at
 * checkout, immediately before the charge — see docs/payment-flow-pattern.md.
 *
 * Stored shape is deliberately minimal: [product_variant_id => quantity]. Prices
 * and names are read live from the variant on every request and snapshotted only
 * when the order is written, so a cart left open across a price change cannot
 * charge yesterday's price.
 */
class Cart
{
    public const SESSION_KEY = 'store.cart';

    /** Adding this would mix Stripe accounts in one charge. */
    public const ERROR_MIXED_ACCOUNT = 'mixed_account';

    /** The variant is not on sale (run closed, product archived, disabled). */
    public const ERROR_UNAVAILABLE = 'unavailable';

    /** Would exceed the product's max_per_order. */
    public const ERROR_MAX_PER_ORDER = 'max_per_order';

    /**
     * Add a quantity of a variant. Returns null on success, or one of the
     * ERROR_* constants.
     */
    public function add(ProductVariant $variant, int $quantity = 1): ?string
    {
        $quantity = max(1, $quantity);

        if (! $this->isBuyable($variant)) {
            return self::ERROR_UNAVAILABLE;
        }

        $product = $variant->run->product;

        // One cart, one Stripe account. A charge lands in exactly one account,
        // so a cart spanning two would silently put half the money in the wrong
        // place. In practice everything is 'association', but the first mixed
        // cart is exactly when this matters.
        if (! $this->acceptsAccount($product)) {
            return self::ERROR_MIXED_ACCOUNT;
        }

        $items = $this->raw();
        $wanted = ($items[$variant->id] ?? 0) + $quantity;

        if (! $this->withinMaxPerOrder($product, $variant, $wanted)) {
            return self::ERROR_MAX_PER_ORDER;
        }

        $items[$variant->id] = $wanted;
        $this->put($items);

        return null;
    }

    /** Set an exact quantity; 0 or less removes the line. */
    public function update(ProductVariant $variant, int $quantity): ?string
    {
        if ($quantity <= 0) {
            $this->remove($variant);

            return null;
        }

        if (! $this->isBuyable($variant)) {
            return self::ERROR_UNAVAILABLE;
        }

        if (! $this->withinMaxPerOrder($variant->run->product, $variant, $quantity)) {
            return self::ERROR_MAX_PER_ORDER;
        }

        $items = $this->raw();
        $items[$variant->id] = $quantity;
        $this->put($items);

        return null;
    }

    public function remove(ProductVariant $variant): void
    {
        $items = $this->raw();
        unset($items[$variant->id]);
        $this->put($items);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * The cart's lines, hydrated from the database.
     *
     * Anything no longer buyable is dropped here AND removed from the session,
     * so a cart left open past a run's close date empties itself rather than
     * carrying a price that can no longer be honoured.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $items = $this->raw();

        if (! $items) {
            return collect();
        }

        $variants = ProductVariant::with('run.product')
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $lines = collect();
        $keep = [];

        foreach ($items as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if (! $variant || ! $this->isBuyable($variant)) {
                continue; // dropped: no longer on sale
            }

            $keep[$variantId] = $quantity;
            $lines->push(new CartLine($variant, (int) $quantity));
        }

        if ($keep !== $items) {
            $this->put($keep);
        }

        return $lines;
    }

    /** True when something was silently dropped by the last lines() call. */
    public function hasDroppedLines(): bool
    {
        $before = count($this->raw());
        $after = $this->lines()->count();

        return $after < $before;
    }

    public function subtotal(): float
    {
        return round($this->lines()->sum(fn (CartLine $line) => $line->amount()), 2);
    }

    /** Total units in the cart — what the nav badge shows. */
    public function count(): int
    {
        return $this->lines()->sum(fn (CartLine $line) => $line->quantity);
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    /**
     * The Stripe account this cart will charge to, or null when empty. Every
     * line shares it — add() refuses anything else.
     */
    public function stripeAccount(): ?string
    {
        return $this->lines()->first()?->variant->run->product->stripe_account;
    }

    /** The distinct products in the cart, for the pickup notes and the account. */
    public function products(): Collection
    {
        return $this->lines()
            ->map(fn (CartLine $line) => $line->variant->run->product)
            ->unique('id')
            ->values();
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    /** On sale means: enabled variant, open run, active product. */
    private function isBuyable(ProductVariant $variant): bool
    {
        $run = $variant->run;

        return $variant->enabled
            && $run !== null
            && $run->isOpen()
            && $run->product !== null
            && $run->product->status === Product::STATUS_ACTIVE;
    }

    private function acceptsAccount(Product $product): bool
    {
        $current = $this->stripeAccount();

        return $current === null || $current === $product->stripe_account;
    }

    /** max_per_order caps the units of that PRODUCT, across all its variants. */
    private function withinMaxPerOrder(Product $product, ProductVariant $variant, int $wanted): bool
    {
        if (! $product->max_per_order) {
            return true;
        }

        $others = $this->lines()
            ->filter(fn (CartLine $line) => $line->variant->run->product_id === $product->id
                && $line->variant->id !== $variant->id)
            ->sum(fn (CartLine $line) => $line->quantity);

        return ($others + $wanted) <= $product->max_per_order;
    }

    /** @return array<int, int> */
    private function raw(): array
    {
        return array_filter(
            (array) session(self::SESSION_KEY, []),
            fn ($qty) => is_numeric($qty) && $qty > 0
        );
    }

    /** @param  array<int, int>  $items */
    private function put(array $items): void
    {
        if ($items) {
            session()->put(self::SESSION_KEY, $items);
        } else {
            session()->forget(self::SESSION_KEY);
        }
    }
}
