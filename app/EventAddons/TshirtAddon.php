<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * T-shirt add-on: a per-student shirt size. No charge. The register form posts a
 * `tshirts` JSON map keyed by user id: { "<user_id>": "M", ... }. The event's
 * `closes_at` deadline replaces the legacy `tshirt_deadline` column.
 */
class TshirtAddon extends AbstractAddon
{
    public function type(): string
    {
        return 'tshirt';
    }

    public function label(): string
    {
        return 'T-Shirt';
    }

    public function scope(): string
    {
        return self::SCOPE_PER_STUDENT;
    }

    public function registrantView(): ?string
    {
        return 'event.addons.register.tshirt';
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $sizes = $this->decodeMap($request->input('tshirts'));
        $size = $sizes[$user->id] ?? null;

        if (! $size) {
            return null;
        }

        return [
            'selected' => true,
            'value' => $size,
        ];
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        return $answer->value ?: null;
    }

    public function summarize(EventAddon $addon, ?array $attrs): ?string
    {
        return ! empty($attrs['value']) ? 'Size '.$attrs['value'] : 'None';
    }
}
