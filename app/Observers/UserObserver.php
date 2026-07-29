<?php

namespace App\Observers;

use App\Models\Guardianship;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $user->syncToSendportal();
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $user->syncToSendportal();
    }

    /**
     * Keep the guardianships pivot mirrored from the legacy responsible_user_id
     * on every save, so the two stay in sync no matter how a user is created
     * (factory, seeder, raw insert) during the transition off that column.
     */
    public function saved(User $user): void
    {
        if (! $user->responsible_user_id) {
            return;
        }

        Guardianship::updateOrCreate(
            ['guardian_user_id' => $user->responsible_user_id, 'dependent_user_id' => $user->id],
            ['is_primary' => true],
        );
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $user->syncToSendportal(true);
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $user->syncToSendportal();
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        $user->syncToSendportal(true);
    }
}
