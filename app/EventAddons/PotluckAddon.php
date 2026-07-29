<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\PotluckOptions;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Potluck add-on: group-scoped (one answer per submission, recorded against the
 * registering user). No charge. The potluck catalog (potluck_options) and the
 * current_count counter are still managed by registerProcess; this handler
 * mirrors the chosen item into the framework so badges/reports/top-ups work.
 *
 * Reads the legacy request inputs potluck_item_id (catalog choice) and
 * potluck_open_item (free-text open sign-up).
 */
class PotluckAddon extends AbstractAddon
{
    public function type(): string
    {
        return 'potluck';
    }

    public function label(): string
    {
        return 'Potluck';
    }

    public function scope(): string
    {
        return self::SCOPE_GROUP;
    }

    public function defaultSettings(): array
    {
        return ['open_signup' => false];
    }

    public function configView(): ?string
    {
        return 'event.addons.config.potluck';
    }

    public function sanitizeSettings(array $input): array
    {
        return ['open_signup' => (bool) ($input['open_signup'] ?? false)];
    }

    public function parseAnswer(Request $request, EventAddon $addon, User $user, int $index): ?array
    {
        $itemId = $request->input('potluck_item_id');
        $openItem = trim((string) $request->input('potluck_open_item'));

        if (! $itemId && $openItem === '') {
            return null;
        }

        $value = $openItem !== '' ? $openItem : null;
        if ($itemId && ($opt = PotluckOptions::find($itemId))) {
            $value = trim($opt->category.' - '.$opt->item, ' -');
        }

        return [
            'selected' => true,
            'value' => $value,
            'data' => [
                'potluck_item_id' => $itemId ?: null,
                'open_item' => $openItem !== '' ? $openItem : null,
            ],
        ];
    }

    public function badgeLabel(EventRegistrationAddon $answer): ?string
    {
        return $answer->value ? 'Potluck: '.$answer->value : null;
    }
}
