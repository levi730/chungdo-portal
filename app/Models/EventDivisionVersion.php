<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDivisionVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_id', 'discipline', 'created_by_user_id', 'data', 'starred', 'note', 'created_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'starred' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
