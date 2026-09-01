<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One print run of a product — see docs/store-design.md.
 *
 * The run carries the ordering window, the expected arrival and the pickup
 * wording, and owns the variants that were on sale during it. Only one run of a
 * product may be open at a time; ProductRunRequest enforces that.
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 */
class ProductRun extends Model
{
    protected $keyType = 'integer';

    protected $fillable = [
        'product_id', 'name', 'opens_at', 'closes_at',
        'expected_arrival_at', 'pickup_note', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'expected_arrival_at' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    /**
     * Runs taking orders right now. A null bound is an open end: no opens_at
     * means "already open", no closes_at means "no deadline yet".
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    public function isOpen(): bool
    {
        $opened = $this->opens_at === null || $this->opens_at->isPast();
        $stillOpen = $this->closes_at === null || $this->closes_at->isFuture();

        return $opened && $stillOpen;
    }

    public function hasClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    public function opensLater(): bool
    {
        return $this->opens_at !== null && $this->opens_at->isFuture();
    }

    /**
     * True once a charge has been attempted against this run. Its variants and
     * prices are history from then on.
     */
    public function hasOrders(): bool
    {
        return ProductOrder::whereHas('items', fn ($q) => $q->where('product_run_id', $this->id))
            ->where(fn ($q) => $q->whereNotNull('stripe_payment_intent_id')
                ->orWhereNotNull('stripe_checkout_session_id'))
            ->exists();
    }

    /** Whether this run's dates overlap another's — only one may be open. */
    public function overlaps(self $other): bool
    {
        $startsBeforeOtherEnds = $this->opens_at === null
            || $other->closes_at === null
            || $this->opens_at < $other->closes_at;

        $endsAfterOtherStarts = $this->closes_at === null
            || $other->opens_at === null
            || $this->closes_at > $other->opens_at;

        return $startsBeforeOtherEnds && $endsAfterOtherStarts;
    }
}
