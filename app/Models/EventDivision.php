<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventDivision extends Model
{
    public const DISCIPLINE_SPARRING = 'sparring';
    public const DISCIPLINE_FORMS = 'forms';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['created_at', 'updated_at', 'event_id', 'discipline', 'name', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_division_id');
    }

    /** Members when this is a forms division (assigned via forms_event_division_id). */
    public function formsRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'forms_event_division_id');
    }
}
