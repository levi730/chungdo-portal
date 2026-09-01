<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\Stripe\ChargedToStripeAccount;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
/**
 * @property int $id
 * @property string $created_at
 * @property string $updated_at
 * @property string $name
 * @property string $startdatetime
 * @property string $enddatetime
 * @property string $location
 * @property string $details
 * @property float $cost
 */
class Event extends Model implements ChargedToStripeAccount, HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    // Event type keys (stored in the `type` column). Competition types decide
    // which registration cards print; the rest are non-competition gatherings.
    public const TYPE_SPARRING = 'sparring';
    public const TYPE_FORMS = 'forms';
    public const TYPE_COMBINED = 'combined';
    public const TYPE_TRAINING = 'training';
    public const TYPE_PICNIC = 'picnic';
    public const TYPE_SOCIAL = 'social';
    public const TYPE_OTHER = 'other';

    /** Type key => human label, in menu order. */
    public const TYPES = [
        self::TYPE_SPARRING => 'Sparring Tournament',
        self::TYPE_FORMS => 'Forms/Technique Tournament',
        self::TYPE_COMBINED => 'Combined Tournament',
        self::TYPE_TRAINING => 'Training Event',
        self::TYPE_PICNIC => 'Picnic/Potluck',
        self::TYPE_SOCIAL => 'Social Event',
        self::TYPE_OTHER => 'Other',
    ];

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['created_at', 'updated_at', 'name', 'type', 'startdatetime', 'enddatetime', 'location', 'host_school_id', 'stripe_account', 'details', 'minimum_rank_id', 'slug', 'map_url', 'require_ticket', 'highlighted', 'highlight_order', 'published_version_id', 'published_forms_version_id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'startdatetime' => 'datetime',
            'enddatetime' => 'datetime',
            'highlighted' => 'boolean',
        ];
    }

    /** How many events the home page shows in total. */
    public const HOMEPAGE_LIMIT = 3;

    /**
     * What the home page shows: featured upcoming events pinned to the top,
     * then the soonest upcoming events filling in below, capped at
     * HOMEPAGE_LIMIT.
     *
     * Featured events sort by highlight_order DESCENDING — a higher number sits
     * higher on the page — then by date. That way the default of 0 is the
     * resting baseline and any positive number promotes an event above it
     * without having to renumber everything else. Featured events with equal
     * order fall back to date order, so leaving them all at 0 is soonest-first.
     *
     * Featuring nothing leaves the page exactly as it was before highlighting
     * existed: the soonest upcoming events, by date.
     */
    public static function forHomepage(): \Illuminate\Support\Collection
    {
        $featured = static::upcoming()
            ->where('highlighted', true)
            ->orderBy('highlight_order', 'desc')
            ->orderBy('startdatetime', 'asc')
            ->get();

        $fill = static::upcoming()
            ->whereNotIn('id', $featured->pluck('id')->all())
            ->orderBy('startdatetime', 'asc')
            ->take(self::HOMEPAGE_LIMIT)
            ->get();

        return $featured->concat($fill)->take(self::HOMEPAGE_LIMIT);
    }

    /**
     * True once any money has moved for this event. The Stripe account is
     * locked at that point: a refund has to be issued on the account that took
     * the charge, so repointing the event would strand it.
     */
    public function hasPayments(): bool
    {
        return \App\Models\PendingEventRegistration::where('event_id', $this->id)
            ->whereNotNull('stripe_payment_intent_id')
            ->exists();
    }

    /**
     * Refresh the cached map picture when — and only when — the map URL
     * changes. That is what keeps this to one Static Maps request per venue
     * rather than one per page view. Failure is not fatal: the card falls back
     * to showing the address.
     */
    protected static function booted(): void
    {
        // Two hooks rather than one on `saved`: performInsert() never calls
        // syncChanges(), so wasChanged() is false on create, while
        // wasRecentlyCreated never clears and would re-fetch on every later
        // save of the same instance. Split, each says exactly what it means.
        static::created(function (self $event) {
            app(\App\Services\EventMapSnapshot::class)->generate($event, force: true);
        });

        static::updated(function (self $event) {
            if ($event->wasChanged('map_url')) {
                app(\App\Services\EventMapSnapshot::class)->generate($event, force: true);
            }
        });
    }

    /**
     * "In 12 days" for the home page. Events have no registration deadline
     * field, so this counts down to the event itself. Null once it has passed,
     * so a stale card never claims urgency it doesn't have.
     */
    public function countdown(): ?string
    {
        if (! $this->startdatetime) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->startdatetime->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => null,
            $days === 0 => 'Today',
            $days === 1 => 'Tomorrow',
            default => 'In '.$days.' days',
        };
    }

    /** Which Stripe account this event's money lands in (ChargedToStripeAccount). */
    public function stripeAccountSlug(): ?string
    {
        return $this->stripe_account;
    }

    /** Human label for the Stripe account this event's money lands in. */
    public function stripeAccountLabel(): string
    {
        return app(\App\Services\Stripe\StripeAccounts::class)->label($this->stripe_account);
    }

    public function publishedDivisionVersion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EventDivisionVersion::class, 'published_version_id');
    }

    public function publishedFormsDivisionVersion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EventDivisionVersion::class, 'published_forms_version_id');
    }

    /** The published-version id for a discipline ('sparring' or 'forms'). */
    public function publishedVersionIdFor(string $discipline): ?int
    {
        return $discipline === EventDivision::DISCIPLINE_FORMS
            ? $this->published_forms_version_id
            : $this->published_version_id;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('forms');
        $this->addMediaCollection('slideshow-images');
    }


    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
            ->using(EventRegistration::class)
            ->withPivot('id', 'amount_due', 'amount_paid', 'payment_id', 'event_division_id', 'registering_user_id', 'checkin');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('startdatetime', '>=', (new Carbon())->setTime(0, 0, 0));
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')->using(EventRegistration::class)->withPivot('id', 'amount_due', 'amount_paid', 'event_division_id', 'payment_id', 'registering_user_id', 'checkin')->withTimestamps();
    }

    public function potluck_options(): HasMany
    {
        return $this->hasMany(PotluckOptions::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(EventAddon::class);
    }

    /**
     * The enabled add-ons for this event, ordered for display, keyed by type.
     *
     * @return \Illuminate\Support\Collection<string, EventAddon>
     */
    public function enabledAddons()
    {
        return $this->addons
            ->where('enabled', true)
            ->sortBy('sort_order')
            ->keyBy('type');
    }

    public function addon(string $type): ?EventAddon
    {
        return $this->addons->firstWhere('type', $type);
    }

    /** Whether this event has the given add-on enabled. */
    public function hasAddon(string $type): bool
    {
        return (bool) optional($this->addon($type))->enabled;
    }

    /** Whether the given add-on is enabled AND still open (before its deadline). */
    public function hasOpenAddon(string $type): bool
    {
        return (bool) optional($this->addon($type))->isOpen();
    }

    public function minimumRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'minimum_rank_id');
    }

    public function hostSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'host_school_id');
    }

    /** Human label for this event's type ("Combined Tournament"), or null. */
    public function typeLabel(): ?string
    {
        return self::TYPES[$this->type] ?? null;
    }

    /** Whether this event runs sparring competition (Sparring or Combined). */
    public function hasSparring(): bool
    {
        return in_array($this->type, [self::TYPE_SPARRING, self::TYPE_COMBINED], true);
    }

    /** Whether this event runs forms/kata competition (Forms or Combined). */
    public function hasForms(): bool
    {
        return in_array($this->type, [self::TYPE_FORMS, self::TYPE_COMBINED], true);
    }

    /** A typed competition event that uses the new tournament cards. */
    public function isCompetition(): bool
    {
        return $this->hasSparring() || $this->hasForms();
    }

    /** The hosting organization name for the cards; falls back to the association. */
    public function hostName(): string
    {
        return $this->hostSchool?->name ?: 'Chung Do Association';
    }

    /**
     * Whether the given user's stored rank satisfies this event's minimum rank
     * requirement. Rank ids are numerically ordered (lower id = lower rank), so a
     * simple comparison works. Events without a minimum allow everyone.
     */
    public function userMeetsMinimumRank(User $user): bool
    {
        if (! $this->minimum_rank_id) {
            return true;
        }

        return $user->rank_id !== null && $user->rank_id >= $this->minimum_rank_id;
    }
}
