<?php

namespace App\EventAddons;

use App\Models\Event;
use App\Models\EventAddon;
use Illuminate\Support\Carbon;

/**
 * Persists an event's add-on enablement + settings + deadlines from the standard
 * enabled[type] / settings[type] / closes_at[type] form input. Shared by the
 * Manage Add-ons page and the event create/edit form.
 */
class AddonConfigurator
{
    public function apply(Event $event, array $enabled, array $settings, array $closesAt): void
    {
        $i = 0;
        foreach (AddonRegistry::all() as $type => $handler) {
            $sanitized = $handler->sanitizeSettings((array) ($settings[$type] ?? []));

            $when = trim((string) ($closesAt[$type] ?? ''));
            $when = $when !== '' ? Carbon::parse($when) : null;

            $addon = EventAddon::firstOrNew(['event_id' => $event->id, 'type' => $type]);
            $addon->enabled = (bool) ($enabled[$type] ?? false);
            $addon->settings = $sanitized;
            $addon->closes_at = $when;
            if (! $addon->exists) {
                $addon->sort_order = $i;
            }
            $addon->save();
            $i++;
        }
    }
}
