<?php

namespace App\EventAddons;

use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Competition participation: for a Combined (sparring + forms/kata) tournament,
 * which events each student competes in — Sparring, Forms, or Both (the default).
 * No charge; the choice drives which registration cards print and which division
 * sets a competitor lands in. The register form posts a `participation` JSON map
 * keyed by user id: { "<user_id>": "both", ... }.
 */
class EventParticipationAddon extends AbstractAddon
{
    public const SPARRING = 'sparring';
    public const FORMS = 'forms';
    public const BOTH = 'both';

    public const CHOICES = [self::SPARRING, self::FORMS, self::BOTH];

    public function type(): string
    {
        return 'participation';
    }

    public function label(): string
    {
        return 'Event Participation';
    }

    public function scope(): string
    {
        return self::SCOPE_PER_STUDENT;
    }

    /** Only meaningful when the event runs both sparring and forms competition. */
    public function appliesTo(Event $event): bool
    {
        return $event->hasSparring() && $event->hasForms();
    }

    public function registrantView(): ?string
    {
        return 'event.addons.register.participation';
    }

    public function reportView(): ?string
    {
        return 'event.addons.report.participation';
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $choices = $this->decodeMap($request->input('participation'));
        $choice = $choices[$user->id] ?? self::BOTH;

        if (! in_array($choice, self::CHOICES, true)) {
            $choice = self::BOTH;
        }

        return [
            'selected' => true,
            'value' => $choice,
        ];
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        return self::choiceLabel($answer->value);
    }

    public function summarize(EventAddon $addon, ?array $attrs): ?string
    {
        return empty($attrs['value']) ? 'None' : self::choiceLabel($attrs['value']);
    }

    /** Human label for a stored choice; defaults to Both. */
    public static function choiceLabel(?string $choice): string
    {
        return match ($choice) {
            self::SPARRING => 'Sparring',
            self::FORMS => 'Forms',
            default => 'Sparring + Forms',
        };
    }
}
