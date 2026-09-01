<?php

namespace App\Services\Store;

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a cart into a ProductOrder and its line items — written BEFORE the card
 * is charged, per docs/payment-flow-pattern.md.
 *
 * The order is self-describing the moment it exists: the items carry snapshotted
 * names and prices, so the pick list, the financials export and any refund read
 * from `product_order_items` rather than parsing `payload`. `payload` keeps the
 * raw submission for forensics only.
 *
 * Nothing here talks to Stripe. The caller charges afterwards and stamps the
 * intent or session id onto the row.
 */
class OrderBuilder
{
    public function __construct(private Cart $cart) {}

    /**
     * @param  array<string, mixed>  $buyer  name, email, phone, pickup_school_id
     */
    public function build(array $buyer, ?User $user = null): ProductOrder
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            throw new \RuntimeException('Cannot place an order for an empty cart.');
        }

        $subtotal = round($lines->sum(fn (CartLine $line) => $line->amount()), 2);

        // Sales tax is undecided; the column exists so the answer is a config
        // change rather than a migration over financial records. See
        // docs/store-design.md.
        $tax = 0.00;

        return DB::transaction(function () use ($lines, $buyer, $user, $subtotal, $tax) {
            $order = ProductOrder::create([
                'status' => ProductOrder::STATUS_PENDING,
                // Snapshot of the account, taken from the cart. Every line
                // shares it — Cart::add refuses anything else.
                'stripe_account' => $this->cart->stripeAccount(),
                'user_id' => $user?->id,
                'email' => $buyer['email'],
                'name' => $buyer['name'],
                'phone' => $buyer['phone'] ?? null,
                'pickup_school_id' => $buyer['pickup_school_id'] ?? null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => round($subtotal + $tax, 2),
                'payload' => [
                    'buyer' => $buyer,
                    'lines' => $lines->map(fn (CartLine $line) => [
                        'product_variant_id' => $line->variant->id,
                        'product_run_id' => $line->run()->id,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unitPrice(),
                    ])->all(),
                ],
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product()->id,
                    'product_run_id' => $line->run()->id,
                    'product_variant_id' => $line->variant->id,
                    'product_name' => $line->product()->name,
                    'variant_name' => $line->label(),
                    'unit_price' => $line->unitPrice(),
                    'quantity' => $line->quantity,
                    'amount' => $line->amount(),
                ]);
            }

            return $order->fresh('items');
        });
    }
}
