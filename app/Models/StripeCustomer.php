<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's Stripe customer id on one specific Stripe account. Customer ids do
 * not cross accounts, so there is one row per (user, account).
 *
 * @property int    $user_id
 * @property string $account            key in config services.stripe.accounts
 * @property string $stripe_customer_id
 */
class StripeCustomer extends Model
{
    protected $fillable = ['user_id', 'account', 'stripe_customer_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
