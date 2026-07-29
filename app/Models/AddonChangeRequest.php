<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int $event_registration_id
 * @property int|null $requested_by_user_id
 * @property array $new_state
 * @property float $refund_amount
 * @property string|null $stripe_payment_intent_id
 * @property string $status
 * @property string|null $admin_note
 * @property int|null $decided_by_user_id
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $stripe_refund_id
 */
class AddonChangeRequest extends Model
{
    // Refund requests (need admin approval):
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_SUPERSEDED = 'superseded'; // a newer change replaced it

    // Charges (top-ups; apply automatically once paid):
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_APPLIED = 'applied';

    protected $fillable = [
        'event_id',
        'event_registration_id',
        'requested_by_user_id',
        'new_state',
        'refund_amount',
        'stripe_payment_intent_id',
        'status',
        'admin_note',
        'decided_by_user_id',
        'decided_at',
        'stripe_refund_id',
    ];

    protected function casts(): array
    {
        return [
            'new_state' => 'array',
            'refund_amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * A human "from -> to" breakdown of the requested change per add-on whose
     * price actually changed (used by the admin screen and the notification).
     *
     * @return array<int, array{label: string, fromText: ?string, toText: ?string, from: float, to: float}>
     */
    public function summaryLines(): array
    {
        $this->loadMissing(['registration.addonAnswers', 'event.addons']);
        // The registration may be gone (e.g. the registrant was removed after an
        // already-decided request) — treat its current answers as empty so a
        // historical request still renders instead of erroring the whole page.
        $current = $this->registration?->addonAnswers->keyBy('event_addon_id') ?? collect();

        $lines = [];
        foreach ($this->new_state as $item) {
            $answer = $current->get($item['event_addon_id']);
            $from = (float) ($answer->amount ?? 0);
            $to = (float) ($item['attrs']['amount'] ?? 0);
            if ($from === $to) {
                continue;
            }
            $addon = $this->event->addon($item['type']);
            $handler = $addon?->handler();
            $fromAttrs = $answer ? ['selected' => $answer->selected, 'quantity' => $answer->quantity, 'value' => $answer->value, 'amount' => $answer->amount] : null;

            $lines[] = [
                'label' => $addon?->label() ?? ucwords(str_replace('_', ' ', $item['type'])),
                'fromText' => $handler?->summarize($addon, $fromAttrs),
                'toText' => $handler?->summarize($addon, $item['attrs'] ?? null),
                'from' => $from,
                'to' => $to,
            ];
        }

        return $lines;
    }
}
