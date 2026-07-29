<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Registration fee add-on: the base per-registrant charge, with household
 * discounts for the 2nd..5th+ person registered together. Replaces the legacy
 * events.cost / cost_type / person_N_discount columns and Event::calcCostFor().
 *
 * Per-student scope: AddonRegistrar passes each registrant's 0-based position as
 * $index, which selects the discount tier (mirrors calcCostFor's ordering).
 */
class RegistrationFeeAddon extends AbstractAddon
{
    public function type(): string
    {
        return 'registration_fee';
    }

    public function label(): string
    {
        return 'Registration Fee';
    }

    public function scope(): string
    {
        return self::SCOPE_PER_STUDENT;
    }

    public function defaultSettings(): array
    {
        return [
            'cost' => 0,
            'cost_type' => 'per person',
            'discounts' => ['2' => 0, '3' => 0, '4' => 0, '5' => 0],
        ];
    }

    public function configView(): ?string
    {
        return 'event.addons.config.registration_fee';
    }

    public function sanitizeSettings(array $input): array
    {
        $discounts = [];
        foreach (['2', '3', '4', '5'] as $tier) {
            $discounts[$tier] = round(max(0, (float) ($input['discounts'][$tier] ?? 0)), 2);
        }

        $costType = trim((string) ($input['cost_type'] ?? 'per person'));

        return [
            'cost' => round(max(0, (float) ($input['cost'] ?? 0)), 2),
            'cost_type' => $costType !== '' ? $costType : 'per person',
            'discounts' => $discounts,
        ];
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $amount = $this->amountForPosition($addon, $index);

        if ($amount <= 0) {
            return null;
        }

        return [
            'selected' => true,
            'amount' => $amount,
        ];
    }

    /**
     * The discounted fee for a registrant at the given 0-based position in the
     * submission. Positions past the 5th reuse the 5-person discount.
     */
    public function amountForPosition(EventAddon $addon, int $index): float
    {
        $cost = (float) $addon->setting('cost', 0);
        $regNumber = $index + 1;

        $discount = 0.0;
        if ($regNumber > 1) {
            $tier = (string) min($regNumber, 5);
            $discounts = (array) $addon->setting('discounts', []);
            $discount = (float) ($discounts[$tier] ?? 0);
        }

        return round(max(0, $cost - $discount), 2);
    }
}
