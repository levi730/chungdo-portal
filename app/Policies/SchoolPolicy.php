<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchoolPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function edit(User $current_user, School $school)
    {
        if ($current_user->hasRole('school-instructor')) {
            return $school->instructors->contains('id', $current_user->id);
        }

        return false;
    }
}
