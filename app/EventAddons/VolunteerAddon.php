<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Volunteer add-on: a per-student multi-select of volunteer roles. No charge.
 * Replaces events.ask_volunteers / events.volunteer_options and
 * event_registrations.volunteer_selections.
 *
 * The register form posts `volunteer_selections` as a JSON array of JSON
 * strings, each {"user_id": X, "item": "Role"} (legacy double-encoded shape,
 * kept so registerProcess can still dual-write the legacy column).
 */
class VolunteerAddon extends AbstractAddon
{
    public function type(): string
    {
        return 'volunteer';
    }

    public function label(): string
    {
        return 'Volunteer';
    }

    public function scope(): string
    {
        return self::SCOPE_PER_STUDENT;
    }

    public function defaultSettings(): array
    {
        return ['options' => []];
    }

    public function configView(): ?string
    {
        return 'event.addons.config.volunteer';
    }

    public function registrantView(): ?string
    {
        return 'event.addons.register.volunteer';
    }

    public function sanitizeSettings(array $input): array
    {
        // Admin enters one role per line; store as a clean string array.
        $raw = $input['options'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n/', $raw);
        }

        $options = collect((array) $raw)
            ->map(fn ($o) => trim((string) $o))
            ->filter()
            ->values()
            ->all();

        return ['options' => $options];
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $items = [];
        foreach ($this->selections($request) as $selection) {
            if (is_array($selection) && ($selection['user_id'] ?? null) == $user->id) {
                $items[] = $selection['item'];
            }
        }

        if (empty($items)) {
            return null;
        }

        return [
            'selected' => true,
            'data' => $items,
        ];
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        $items = (array) ($answer->data ?? []);

        return empty($items) ? null : 'Volunteer: '.implode(', ', $items);
    }

    /** Decode the double-encoded volunteer_selections payload. */
    private function selections(Request $request): array
    {
        $all = json_decode($request->input('volunteer_selections'), true) ?: [];

        return array_map(fn ($v) => is_string($v) ? json_decode($v, true) : $v, $all);
    }
}
