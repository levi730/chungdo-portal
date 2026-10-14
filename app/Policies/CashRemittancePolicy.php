<?php

namespace App\Policies;

use App\Models\CashRemittance;
use App\Models\Event;
use App\Models\User;

/**
 * Who may do what with cash/check registrations.
 *
 * - **Entering** a batch belongs to any instructor of any school (read off
 *   school_instructors, as SchoolPolicy does) and to event managers. Whoever
 *   is named responsible is on the hook for the money.
 * - **Naming someone else** responsible is for event managers only — an admin
 *   typing in an envelope an instructor handed them. An instructor cannot put
 *   a colleague on the hook.
 * - **Receiving** is `payments.receive`: recording that money was deposited is
 *   narrower than running events.
 * - **Voiding** is for receivers and event managers, and for the instructor
 *   who entered or owns it — only while outstanding, which the service enforces.
 *
 * super.admin arrives through the Gate::before hook and is not repeated here.
 */
class CashRemittancePolicy
{
    public function create(User $user, Event $event): bool
    {
        return $user->can('event.manage') || $user->isInstructor();
    }

    public function assignResponsible(User $user): bool
    {
        return $user->can('event.manage');
    }

    /** The outstanding/received list across everyone. */
    public function viewAny(User $user): bool
    {
        return $user->can('payments.receive') || $user->can('event.approveRefunds');
    }

    public function view(User $user, CashRemittance $remittance): bool
    {
        return $this->owns($user, $remittance) || $this->viewAny($user) || $user->can('event.manage');
    }

    public function receive(User $user, CashRemittance $remittance): bool
    {
        return $user->can('payments.receive');
    }

    public function void(User $user, CashRemittance $remittance): bool
    {
        return $user->can('payments.receive') || $user->can('event.manage') || $this->owns($user, $remittance);
    }

    private function owns(User $user, CashRemittance $remittance): bool
    {
        return in_array($user->id, [$remittance->responsible_user_id, $remittance->created_by_user_id], true);
    }
}
