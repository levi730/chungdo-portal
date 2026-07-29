<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Meal ticket add-on: a per-student "attending the meal?" switch plus a count
 * of additional (non-registered) people they're buying meals for. Each meal is
 * charged at a flat price configured on the event.
 *
 * Register form posts a `meals` JSON map keyed by user id:
 *   { "<user_id>": { "attending": true, "additional": 2 }, ... }
 */
class MealTicketAddon extends AbstractAddon
{
    public function type(): string
    {
        return 'meal_ticket';
    }

    public function label(): string
    {
        return 'Meal Ticket';
    }

    public function scope(): string
    {
        return self::SCOPE_PER_STUDENT;
    }

    public function defaultSettings(): array
    {
        return [
            'price' => 0,
            'label' => 'Meal',
            'description' => '',
        ];
    }

    public function configView(): ?string
    {
        return 'event.addons.config.meal_ticket';
    }

    public function registrantView(): ?string
    {
        return 'event.addons.register.meal_ticket';
    }

    public function reportView(): ?string
    {
        return 'event.addons.report.meal_ticket';
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        $eating = (bool) $answer->selected;
        $additional = (int) $answer->quantity;

        if (! $eating && $additional === 0) {
            return null;
        }

        $label = $answer->addon?->setting('label', 'Meal') ?? 'Meal';

        return $additional > 0 ? $label.' +'.$additional : $label;
    }

    public function summarize(EventAddon $addon, ?array $attrs): ?string
    {
        if (! $attrs) {
            return 'No meal';
        }

        $meals = (($attrs['selected'] ?? false) ? 1 : 0) + (int) ($attrs['quantity'] ?? 0);

        return $meals > 0 ? $meals.' '.str('meal')->plural($meals) : 'No meal';
    }

    public function sanitizeSettings(array $input): array
    {
        $label = trim((string) ($input['label'] ?? 'Meal'));

        return [
            'price' => round(max(0, (float) ($input['price'] ?? 0)), 2),
            'label' => $label !== '' ? $label : 'Meal',
            'description' => trim((string) ($input['description'] ?? '')),
        ];
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $meals = $this->decodeMap($request->input('meals'));
        $entry = $meals[$user->id] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        $attending = filter_var($entry['attending'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $additional = max(0, (int) ($entry['additional'] ?? 0));

        // Nothing to record if they're not eating and buying no extra meals.
        if (! $attending && $additional === 0) {
            return null;
        }

        $price = (float) $addon->setting('price', 0);
        $meals_count = ($attending ? 1 : 0) + $additional;

        return [
            'selected' => $attending,
            'quantity' => $additional,
            'amount' => round($meals_count * $price, 2),
        ];
    }
}
