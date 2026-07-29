<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardianship extends Model
{
    protected $fillable = [
        'guardian_user_id',
        'dependent_user_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function dependent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dependent_user_id');
    }
}
