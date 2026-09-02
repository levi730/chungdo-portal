<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may manage a school.
 *
 * Two distinct rights, deliberately kept apart:
 *
 * - **Editing** a school's own details belongs to the people who run it. That is
 *   read straight off school_instructors, which already holds the data.
 * - **Creating and deleting** schools is an association-level act and needs the
 *   `school.manage` permission. An instructor correcting their phone number
 *   should not be able to archive a sibling school.
 *
 * super.admin passes everything through the Gate::before hook in
 * AppServiceProvider, so it isn't repeated here.
 *
 * The previous version gated editing on hasRole('school-instructor'). That role
 * has never existed in this database, so the check was always false and the Edit
 * button never appeared for anybody — the 12 rows in school_instructors were
 * doing nothing. The relationship is the thing that carries the fact.
 */
class SchoolPolicy
{
    use HandlesAuthorization;

    /** Anyone signed in can see the school list. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, School $school): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('school.manage');
    }

    /** An instructor of this school, or anyone who manages schools generally. */
    public function update(User $user, School $school): bool
    {
        return $user->can('school.manage') || $this->instructs($user, $school);
    }

    /**
     * Kept because the existing views and SchoolController call can('edit', ...)
     * rather than can('update', ...).
     */
    public function edit(User $user, School $school): bool
    {
        return $this->update($user, $school);
    }

    public function delete(User $user, School $school): bool
    {
        return $user->can('school.manage');
    }

    public function restore(User $user, School $school): bool
    {
        return $user->can('school.manage');
    }

    private function instructs(User $user, School $school): bool
    {
        if (! $school->exists) {
            return false;
        }

        return $school->instructors()->where('users.id', $user->id)->exists();
    }
}
