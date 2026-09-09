<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserNote;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may change a note once it is written.
 *
 * The author, because a note is usually corrected by the person who noticed the
 * thing; and event.admin, because the people running an event have to be able to
 * strike a note that is wrong about a child in front of them, and the instructor
 * who wrote it may be in a ring.
 *
 * super.admin passes everything through the Gate::before hook in
 * AppServiceProvider, so it isn't repeated here.
 *
 * Writing a note is a wider right than changing one — anyone who can see the
 * registrants list can add — so creation stays gated on
 * event.viewAllSchoolRegistrants in the controller rather than living here.
 */
class UserNotePolicy
{
    use HandlesAuthorization;

    public function update(User $user, UserNote $note): bool
    {
        return $this->authorOrEventAdmin($user, $note);
    }

    public function delete(User $user, UserNote $note): bool
    {
        return $this->authorOrEventAdmin($user, $note);
    }

    private function authorOrEventAdmin(User $user, UserNote $note): bool
    {
        return (int) $note->added_by === (int) $user->id
            || $user->hasRole('event.admin');
    }
}
