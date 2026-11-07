<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
        'awaiting_finals_at',
        'completed_by_user_id',
        'zulip_message_id',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'awaiting_finals_at' => 'datetime'];
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

    /** Nulls sort first in MySQL, so finalists sit above 3rd. */
    public function placements(): HasMany
    {
        return $this->hasMany(DivisionPlacement::class)->orderBy('place')->orderBy('name');
    }

    public function hasFinalists(): bool
    {
        return $this->placements->contains->isFinalist();
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /** What the results page shows: complete, or saved as far as finals. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('completed_at')->orWhereNotNull('awaiting_finals_at'));
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByRaw('COALESCE(completed_at, awaiting_finals_at) DESC')->orderByDesc('id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isAwaitingFinals(): bool
    {
        return $this->completed_at === null && $this->awaiting_finals_at !== null;
    }

    public function publishedAt(): ?CarbonInterface
    {
        return $this->completed_at ?? $this->awaiting_finals_at;
    }
}
