<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reference
 * @property string|null $stripe_payment_intent_id
 * @property int $event_id
 * @property int|null $registering_user_id
 * @property float $amount
 * @property float|null $amount_paid
 * @property string $status
 * @property array $payload
 * @property int|null $payment_id
 * @property \Illuminate\Support\Carbon|null $fulfilled_at
 */
class PendingEventRegistration extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'reference',
        'stripe_payment_intent_id',
        'event_id',
        'registering_user_id',
        'amount',
        'amount_paid',
        'status',
        'payload',
        'payment_id',
        'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'payload' => 'array',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isFulfilled(): bool
    {
        return $this->status === self::STATUS_FULFILLED;
    }
}
