<?php

namespace App\EventAddons;

use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Contract for an event add-on (registration fee, donation, potluck, meal
 * ticket, t-shirt, ...). A handler is a stateless service that knows how to
 * configure, render, validate, price and persist its one add-on. Handlers are
 * registered in config/event_addons.php and resolved via AddonRegistry.
 */
interface AddonHandler
{
    /** Answered once per registered student (e.g. t-shirt size, meal ticket). */
    public const SCOPE_PER_STUDENT = 'per_student';

    /** Answered once for the whole submission (e.g. donation, potluck). */
    public const SCOPE_GROUP = 'group';

    /** The stable type key, matching event_addons.type. */
    public function type(): string;

    /** Human label for admin UI and reports. */
    public function label(): string;

    /** SCOPE_PER_STUDENT or SCOPE_GROUP. */
    public function scope(): string;

    /**
     * Whether this add-on is applicable to (and so offered for) the given event.
     * Most add-ons apply to any event; a few are type-specific (e.g. competition
     * participation only makes sense for a Combined tournament).
     */
    public function appliesTo(Event $event): bool;

    /** Default configuration merged under any stored settings. */
    public function defaultSettings(): array;

    /** Blade view for editing this add-on's settings in the admin UI, or null. */
    public function configView(): ?string;

    /** Blade partial rendered inside each student row on the register form, or null. */
    public function registrantView(): ?string;

    /** Blade partial rendered once for the whole submission, or null. */
    public function groupView(): ?string;

    /** Normalize/whitelist raw admin-form input into the stored settings array. */
    public function sanitizeSettings(array $input): array;

    /**
     * Extract this add-on's answer for one registrant out of the register
     * request and return the EventRegistrationAddon attributes to store
     * (keys among: selected, quantity, value, amount, data), or null to store
     * nothing. $index is the registrant's position in the submission (0-based)
     * so GROUP-scoped add-ons can record only on the first.
     */
    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array;

    /** Blade view for this add-on's registrant breakdown report, or null. */
    public function reportView(): ?string;

    /**
     * A short label summarizing a stored answer, shown as a badge next to an
     * already-registered student (e.g. "BBQ Dinner +2"). Return null to show
     * no badge for this answer.
     */
    public function badgeLabel(EventRegistrationAddon $answer): ?string;

    /**
     * A plain-language description of an answer given its raw attributes
     * (selected/quantity/value/amount), e.g. "2 meals" or "Size L". Used to show
     * a from -> to summary of a requested change. $attrs is null when the add-on
     * isn't chosen.
     */
    public function summarize(EventAddon $addon, ?array $attrs): ?string;
}
