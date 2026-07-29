<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued refund. Written once, at approval time, by App\Services\RefundApprover.
 * `amount` is the actual money returned via Stripe; `breakdown` is a snapshot of
 * which add-on categories were reduced (and by how much), captured at issue time
 * because the source add-on rows are mutated/deleted when the refund is applied.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $event_registration_id
 * @property int|null $person_id
 * @property int|null $refunded_to_user_id
 * @property int|null $addon_change_request_id
 * @property int|null $payment_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_refund_id
 * @property float $amount
 * @property array|null $breakdown
 * @property int|null $decided_by_user_id
 * @property string|null $admin_note
 */
class Refund extends Model
{
    protected $fillable = [
        'event_id',
        'event_registration_id',
        'person_id',
        'refunded_to_user_id',
        'addon_change_request_id',
        'payment_id',
        'stripe_payment_intent_id',
        'stripe_refund_id',
        'amount',
        'breakdown',
        'decided_by_user_id',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'person_id');
    }

    public function refundedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_to_user_id');
    }
}
