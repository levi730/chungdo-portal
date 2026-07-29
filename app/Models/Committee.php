<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Committee extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Members of the committee. The pivot carries timestamps so we know when
     * each member was added (created_at on committee_user).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->orderBy('lastname')
            ->orderBy('firstname');
    }
}
