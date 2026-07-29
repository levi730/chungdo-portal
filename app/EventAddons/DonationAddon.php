<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Donation add-on: an optional group-level donation amount added to the payment.
 * Replaces events.donations and event_registrations.donation_amount. The
 * register form posts a single `donation_amount` field.
 */
class DonationAddon extends AbstractAddon
{
    public function type(): string
    {
        return 'donation';
    }

    public function label(): string
    {
        return 'Donation';
    }

    public function scope(): string
    {
        return self::SCOPE_GROUP;
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $amount = round((float) ($request->input('donation_amount') ?? 0), 2);

        if ($amount <= 0) {
            return null;
        }

        return [
            'selected' => true,
            'amount' => $amount,
        ];
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        $amount = (float) $answer->amount;

        return $amount > 0 ? 'Donation: $'.number_format($amount, 2) : null;
    }
}
