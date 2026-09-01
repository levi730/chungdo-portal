<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $rank
 * @property string $color
 */
class Payment extends Model
{
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['user_id', 'product_order_id', 'amount_paid', 'stripe_payment_intent_id'];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Set when this payment is a store order rather than an event
     * registration. Store orders share this ledger so both reconcile against
     * Stripe in one place — see docs/store-design.md.
     */
    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }
}
