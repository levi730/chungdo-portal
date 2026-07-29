<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlternateContact extends Model
{

    protected $table = 'alternate_contacts';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'value',
        'mailing',
    ];

    protected $casts = [
        'mailing' => 'boolean',
    ];

    /**
     * Get the user that owns the alternate contact.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
