<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $created_at
 * @property string $updated_at
 * @property int $person_id
 * @property int $event_id
 * @property float $amount_due
 * @property float $amount_paid
 */
class EventRegistration extends Pivot
{
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    protected $table = 'event_registrations';

    /**
     * @var array
     */
    protected $guarded = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function event_division(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class);
    }

    public function forms_event_division(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'forms_event_division_id');
    }

    public function addonAnswers(): HasMany
    {
        return $this->hasMany(EventRegistrationAddon::class, 'event_registration_id');
    }

    /**
     * The stored answer for an add-on type (from the loaded addonAnswers), or null.
     */
    public function addonAnswer(string $type): ?EventRegistrationAddon
    {
        return $this->addonAnswers->firstWhere('type', $type);
    }

    // --- Convenience accessors that read from event_registration_addons ---
    // (replace the retired legacy pivot columns).

    public function tshirtSize(): ?string
    {
        return $this->addonAnswer('tshirt')?->value;
    }

    /**
     * The competitor's chosen events for a Combined tournament: 'sparring',
     * 'forms', or 'both'. Defaults to Both when unanswered (or the add-on is off).
     */
    public function participation(): string
    {
        return $this->addonAnswer('participation')?->value
            ?? \App\EventAddons\EventParticipationAddon::BOTH;
    }

    public function guestCount(): int
    {
        return (int) ($this->addonAnswer('guests')?->quantity ?? 0);
    }

    public function donationAmount(): float
    {
        return (float) ($this->addonAnswer('donation')?->amount ?? 0);
    }

    public function potluckItemId(): ?int
    {
        $id = $this->addonAnswer('potluck')?->data['potluck_item_id'] ?? null;

        return $id ? (int) $id : null;
    }

    public function potluckOpenItem(): ?string
    {
        return $this->addonAnswer('potluck')?->data['open_item'] ?? null;
    }

    public function potluckItem(): ?PotluckOptions
    {
        $id = $this->potluckItemId();

        return $id ? PotluckOptions::find($id) : null;
    }

    /**
     * A display label for the potluck contribution: the free-text dish for
     * open-signup events, or the "Category - Item" for catalog events.
     */
    public function potluckLabel(): ?string
    {
        if ($open = $this->potluckOpenItem()) {
            return $open;
        }

        $item = $this->potluckItem();

        return $item ? trim($item->category.' - '.$item->item) : null;
    }

    /** @return string[] */
    public function volunteerSelections(): array
    {
        return (array) ($this->addonAnswer('volunteer')?->data ?? []);
    }
}
