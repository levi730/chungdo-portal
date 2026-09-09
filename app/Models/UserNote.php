<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note an instructor keeps about a member.
 *
 * Permanent notes describe the person and always show. Temporary notes belong
 * to the event they were written for and show only in that event's context —
 * see the migration for why the two are one table with a flag.
 */
class UserNote extends Model
{
    public const SCOPE_TEMPORARY = 'temporary';

    public const SCOPE_PERMANENT = 'permanent';

    public const SCOPES = [self::SCOPE_TEMPORARY, self::SCOPE_PERMANENT];

    public $timestamps = true;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['user_id', 'event_id', 'added_by', 'note', 'scope'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function added_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function isPermanent(): bool
    {
        return $this->scope === self::SCOPE_PERMANENT;
    }

    public function scopePermanent(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_PERMANENT);
    }

    public function scopeTemporary(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_TEMPORARY);
    }

    /**
     * The notes worth reading while working a given event: everything permanent,
     * plus the temporary notes written for that event and no other.
     *
     * A temporary note with no event predates the flag or was written outside
     * any event context; it is nobody's business here and stays hidden.
     */
    public function scopeVisibleForEvent(Builder $query, ?Event $event): Builder
    {
        return $query->where(function (Builder $q) use ($event) {
            $q->permanent();

            if ($event) {
                $q->orWhere(fn (Builder $t) => $t->temporary()->where('event_id', $event->id));
            }
        });
    }
}
