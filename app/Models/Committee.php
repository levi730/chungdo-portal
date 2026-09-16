<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;

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

    /**
     * Roles this committee confers on every one of its members.
     *
     * Membership is the grant: nothing is written onto the user, so removing
     * someone from the committee removes the access in the same act. See
     * {@see User::committeePermissionNames()} for how that reaches the gate.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps()
            ->orderBy('name');
    }
}
