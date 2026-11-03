<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A refund issued on a store order, written once by
 * App\Services\Store\ProductOrderRefunder. `amount` is the money that actually
 * went back through Stripe, on `stripe_account` — which is the order's account,
 * because a refund can only be issued where the charge was taken.
 *
 * Separate from the event-scoped `refunds` table on purpose; see the migration.
 *
 * @property int $id
 * @property int $product_order_id
 * @property int|null $payment_id
 * @property string $stripe_account
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_refund_id
 * @property float $amount
 * @property int|null $refunded_by_user_id
 * @property string|null $admin_note
 */
class ProductOrderRefund extends Model
{
    protected $keyType = 'integer';

    protected $fillable = [
        'product_order_id', 'payment_id', 'stripe_account', 'stripe_payment_intent_id',
        'stripe_refund_id', 'amount', 'refunded_by_user_id', 'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by_user_id');
    }
}
