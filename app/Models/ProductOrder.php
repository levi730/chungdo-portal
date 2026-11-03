<?php

namespace App\Models;

use App\Services\Stripe\ChargedToStripeAccount;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A store order — and the pending record for its payment.
 *
 * Written (with its items) BEFORE the card is charged, so the outcome can be
 * completed exactly once from either the synchronous response or the webhook,
 * per docs/payment-flow-pattern.md. Stripe only ever carries a pointer to this
 * row, never the order itself.
 *
 * @property int $id
 * @property string $reference
 * @property string $status
 * @property string $fulfillment_status
 * @property string $stripe_account
 * @property array $payload
 */
class ProductOrder extends Model implements ChargedToStripeAccount
{
    /** Payment lifecycle. */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Physical hand-over, which happens days later at a school. */
    public const FULFILLMENT_AWAITING = 'awaiting';
    public const FULFILLMENT_READY = 'ready';
    public const FULFILLMENT_COLLECTED = 'collected';

    /** Fulfillment states in the order they happen, with admin labels. */
    public const FULFILLMENT_STATUSES = [
        self::FULFILLMENT_AWAITING => 'Awaiting delivery',
        self::FULFILLMENT_READY => 'Ready for pickup',
        self::FULFILLMENT_COLLECTED => 'Collected',
    ];

    protected $keyType = 'integer';

    protected $fillable = [
        'reference', 'status', 'fulfillment_status', 'stripe_account',
        'stripe_payment_intent_id', 'stripe_checkout_session_id', 'payment_id',
        'user_id', 'email', 'name', 'phone', 'pickup_school_id',
        'subtotal', 'tax', 'total', 'amount_paid', 'refunded_amount', 'stripe_refund_id',
        'payload', 'admin_note', 'paid_at', 'fulfilled_at', 'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'collected_at' => 'datetime',
            'payload' => 'array',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // The reference is the buyer's order number and the key a guest uses to
        // look the order up, so it must exist from the moment the row does.
        static::creating(function (self $order) {
            if (blank($order->reference)) {
                $order->reference = (string) Str::uuid();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pickupSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'pickup_school_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(ProductOrderRefund::class)->orderBy('id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCollected(): bool
    {
        return $this->fulfillment_status === self::FULFILLMENT_COLLECTED;
    }

    /** What was captured for this order, less what has already gone back. */
    public function netPaid(): float
    {
        return round((float) ($this->amount_paid ?? 0) - (float) $this->refunded_amount, 2);
    }

    /** The most a further refund can return. Zero for anything not paid. */
    public function refundable(): float
    {
        return $this->isPaid() ? max(0, $this->netPaid()) : 0.0;
    }

    /**
     * Everything that was paid has gone back. Such an order stays `paid` —
     * the money did come in — but it is off the pick list, since nobody is
     * owed a shirt.
     */
    public function isFullyRefunded(): bool
    {
        return $this->isPaid() && (float) $this->refunded_amount > 0 && $this->netPaid() <= 0;
    }

    /** A short, readable form of the uuid for lists and subject lines. */
    public function shortReference(): string
    {
        return strtoupper(substr((string) $this->reference, 0, 8));
    }

    /** Scope: orders whose money arrived. */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /** A guest order — no portal account behind it. */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function stripeAccountSlug(): ?string
    {
        return $this->stripe_account;
    }

    /** Human label for the Stripe account this order was charged on. */
    public function stripeAccountLabel(): string
    {
        return app(StripeAccounts::class)->label($this->stripe_account);
    }

    /**
     * Orders that reached Stripe but never completed — what the reconcile sweep
     * asks Stripe about. See docs/store-design.md.
     */
    public function scopeStalePending(Builder $query, int $minutes = 15): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->where(fn ($q) => $q->whereNotNull('stripe_payment_intent_id')
                ->orWhereNotNull('stripe_checkout_session_id'));
    }
}
