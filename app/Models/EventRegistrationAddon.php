<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_registration_id
 * @property int $event_addon_id
 * @property string $type
 * @property bool $selected
 * @property int|null $quantity
 * @property string|null $value
 * @property float|null $amount
 * @property array|null $data
 */
class EventRegistrationAddon extends Model
{
    protected $fillable = [
        'event_registration_id',
        'event_addon_id',
        'type',
        'selected',
        'quantity',
        'value',
        'amount',
        'payment_id',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'selected' => 'boolean',
            'quantity' => 'integer',
            'amount' => 'decimal:2',
            'data' => 'array',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(EventAddon::class, 'event_addon_id');
    }
}
