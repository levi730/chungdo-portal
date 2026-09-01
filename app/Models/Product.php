<?php

namespace App\Models;

use App\Services\Stripe\ChargedToStripeAccount;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Merchandise sold independently of an event — see docs/store-design.md.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string $stripe_account
 * @property bool $highlighted
 * @property int $highlight_order
 */
class Product extends Model implements ChargedToStripeAccount, HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /** Status key => human label, in menu order. */
    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $keyType = 'integer';

    protected $fillable = [
        'name', 'slug', 'stripe_account', 'status', 'description', 'option_names',
        'max_per_order', 'highlighted', 'highlight_order', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'option_names' => 'array',
            'highlighted' => 'boolean',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ProductRun::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Every variant across every run. Individual screens almost always want a
     * single run's variants ($run->variants) — this exists for counting and for
     * the admin list.
     */
    public function variants(): HasManyThrough
    {
        return $this->hasManyThrough(ProductVariant::class, ProductRun::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product-images');
    }

    /** The image the store card and the product hero use, if any. */
    public function image(): ?Media
    {
        return $this->getMedia('product-images')->first();
    }

    /**
     * Cheapest and dearest price in the open run, for the "from $20" on a store
     * card. Null when nothing is on sale.
     *
     * @return array{low: float, high: float}|null
     */
    public function priceRange(): ?array
    {
        $run = $this->openRun();

        if (! $run) {
            return null;
        }

        $prices = $run->variants()->enabled()->pluck('price');

        if ($prices->isEmpty()) {
            return null;
        }

        return ['low' => (float) $prices->min(), 'high' => (float) $prices->max()];
    }

    /* ------------------------------------------------------------------ *
     * Availability
     * ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** Active products with a run taking orders right now. */
    public function scopeOrderable(Builder $query): Builder
    {
        return $query->active()->whereHas('runs', fn ($q) => $q->open());
    }

    /**
     * The run taking orders right now, or null. Only one run of a product may
     * be open at a time (ProductRunRequest enforces it), so this is unambiguous.
     */
    public function openRun(): ?ProductRun
    {
        return $this->runs()->open()->first();
    }

    /** The next run that has not opened yet — what to show once one closes. */
    public function nextRun(): ?ProductRun
    {
        return $this->runs()
            ->whereNotNull('opens_at')
            ->where('opens_at', '>', now())
            ->reorder('opens_at')
            ->first();
    }

    /**
     * "Orders close in 12 days" for the home page. Null when the open run has
     * no deadline, or has already shut — an expired card must not keep
     * advertising urgency.
     */
    public function ordersCloseCountdown(): ?string
    {
        $closes = $this->openRun()?->closes_at;

        if (! $closes) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($closes->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => null,
            $days === 0 => 'Orders close today',
            $days === 1 => 'Orders close tomorrow',
            default => 'Orders close in '.$days.' days',
        };
    }

    /** Whether this product is accepting orders right now. */
    public function isOrderable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->openRun() !== null;
    }

    /**
     * What the home page shows: featured products, highest highlight_order
     * first.
     *
     * Uncapped, unlike events. Events fill the page with whatever is coming up
     * next, so they need a ceiling; a product only appears because someone
     * ticked the box for it, and silently dropping the third tick would make
     * that checkbox a liar.
     *
     * Unlike events this does NOT fill in with whatever else exists. An event
     * has a date, so "the soonest three" is a sensible default; a product does
     * not, and a store section appearing on the home page unbidden would be a
     * surprise. Featuring nothing shows nothing.
     */
    public static function forHomepage(): Collection
    {
        return static::orderable()
            ->where('highlighted', true)
            ->orderBy('highlight_order', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /* ------------------------------------------------------------------ *
     * Stripe account
     * ------------------------------------------------------------------ */

    public function stripeAccountSlug(): ?string
    {
        return $this->stripe_account;
    }

    /** Human label for the Stripe account this product's money lands in. */
    public function stripeAccountLabel(): string
    {
        return app(StripeAccounts::class)->label($this->stripe_account);
    }

    /**
     * True once a charge has been attempted for this product. The Stripe
     * account is locked from then on: a refund has to be issued on the account
     * that took the charge, so repointing the product would strand it.
     *
     * Attempted, not settled — an order that reached Stripe and is still
     * pending would be stranded just the same.
     */
    public function hasPayments(): bool
    {
        return ProductOrder::whereHas('items', fn ($q) => $q->where('product_id', $this->id))
            ->where(fn ($q) => $q->whereNotNull('stripe_payment_intent_id')
                ->orWhereNotNull('stripe_checkout_session_id'))
            ->exists();
    }
}
