<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Guests add-on: a per-student count of guests they're bringing, capped at the
 * configured maximum. No charge. Replaces the legacy `max_guests_per_person`
 * (events) and `guests` (event_registrations) columns. The register form posts
 * a `guests` JSON map keyed by user id: { "<user_id>": 2, ... }.
 */
class GuestsAddon extends AbstractAddon
{
    public function type(): string
    {
        return 'guests';
    }

    public function label(): string
    {
        return 'Guests';
    }

    public function scope(): string
    {
        return self::SCOPE_PER_STUDENT;
    }

    public function defaultSettings(): array
    {
        return ['max' => 0];
    }

    public function configView(): ?string
    {
        return 'event.addons.config.guests';
    }

    public function registrantView(): ?string
    {
        return 'event.addons.register.guests';
    }

    public function sanitizeSettings(array $input): array
    {
        return ['max' => max(0, (int) ($input['max'] ?? 0))];
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $max = (int) $addon->setting('max', 0);
        if ($max <= 0) {
            return null;
        }

        $requested = (int) ($this->decodeMap($request->input('guests'))[$user->id] ?? 0);
        $count = max(0, min($requested, $max));

        if ($count <= 0) {
            return null;
        }

        return [
            'selected' => true,
            'quantity' => $count,
        ];
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        $count = (int) $answer->quantity;

        return $count > 0 ? $count.' '.str('guest')->plural($count) : null;
    }

    public function summarize(EventAddon $addon, ?array $attrs): ?string
    {
        $count = (int) ($attrs['quantity'] ?? 0);

        return $count > 0 ? $count.' '.str('guest')->plural($count) : 'None';
    }
}
