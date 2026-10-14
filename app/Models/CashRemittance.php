<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One envelope of cash and checks an instructor collected for an event.
 *
 * The registrations it covers exist from the moment it is created; this row is
 * what the instructor owes for them. See App\Services\CashRegistration.
 *
 * @property int $id
 * @property int $event_id
 * @property string|null $reference
 * @property int $responsible_user_id
 * @property int $created_by_user_id
 * @property string $stripe_account
 * @property string $payee
 * @property string $amount_due
 * @property string $status
 * @property string|null $note
 * @property array $payload
 * @property string|null $amount_received
 * @property string|null $received_method
 * @property string|null $check_numbers
 * @property string|null $deposit_note
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property int|null $received_by_user_id
 * @property int|null $payment_id
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property int|null $voided_by_user_id
 * @property string|null $void_reason
 */
class CashRemittance extends Model
{
    public const STATUS_OUTSTANDING = 'outstanding';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_VOID = 'void';

    public const METHODS = [
        'cash' => 'Cash',
        'check' => 'Check',
        'mixed' => 'Cash and check',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'payload' => 'array',
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'cash_remittance_id');
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OUTSTANDING);
    }

    public function isOutstanding(): bool
    {
        return $this->status === self::STATUS_OUTSTANDING;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * What is still owed: everything while outstanding, the shortfall once
     * received, nothing once void. Negative when more came in than was due.
     */
    public function balance(): float
    {
        return match ($this->status) {
            self::STATUS_OUTSTANDING => (float) $this->amount_due,
            self::STATUS_RECEIVED => round((float) $this->amount_due - (float) $this->amount_received, 2),
            default => 0.0,
        };
    }

    /** The students on the slip, as submitted: [{user_id, name, amount, lines: [...]}]. */
    public function lines(): array
    {
        return $this->payload['students'] ?? [];
    }

    /** CR-00042: derived from the id so it is unique and never reused. */
    public static function referenceFor(int $id): string
    {
        return 'CR-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
