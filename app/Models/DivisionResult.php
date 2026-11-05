<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DivisionResult extends Model
{
    protected $fillable = [
        'event_id',
        'event_division_version_id',
        'position',
        'discipline',
        'division_name',
        'completed_at',
        'completed_by_user_id',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(EventDivisionVersion::class, 'event_division_version_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(DivisionPlacement::class)->orderBy('place')->orderBy('name');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
