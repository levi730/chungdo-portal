<?php

namespace App\EventAddons;

use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;

/**
 * Sensible defaults for add-on handlers. Concrete handlers override only the
 * pieces they need. By default an add-on is per-student, has no settings and
 * renders/records nothing until it opts in.
 */
abstract class AbstractAddon implements AddonHandler
{
    public function scope(): string
    {
        return self::SCOPE_PER_STUDENT;
    }

    public function appliesTo(Event $event): bool
    {
        return true;
    }

    public function defaultSettings(): array
    {
        return [];
    }

    public function configView(): ?string
    {
        return null;
    }

    public function registrantView(): ?string
    {
        return null;
    }

    public function groupView(): ?string
    {
        return null;
    }

    public function sanitizeSettings(array $input): array
    {
        return [];
    }

    public function reportView(): ?string
    {
        return null;
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        return null;
    }

    public function summarize(EventAddon $addon, ?array $attrs): ?string
    {
        if (! $attrs || empty($attrs['selected'])) {
            return 'None';
        }

        return 'Selected';
    }

    /**
     * Decode a per-user answer map posted by the register form, which arrives as
     * a JSON string (hidden input) or an already-decoded array.
     */
    protected function decodeMap($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: [];
        }

        return [];
    }
}
