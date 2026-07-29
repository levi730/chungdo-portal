<?php

namespace App\EventAddons;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Turns a registration request into stored add-on answers. Used by
 * EventController::registerProcess to keep add-on logic out of the controller:
 * it parses each enabled add-on's answer, sums the money owed, and writes the
 * per-registration answer rows.
 */
class AddonRegistrar
{
    /**
     * Parse per-user answers for every enabled add-on on the event.
     *
     * @param  User[]  $users  the registrants, in submission order
     * @return array{perUser: array<int, array<int, array{addon: \App\Models\EventAddon, attrs: array}>>, total: float}
     */
    public function parse(Request $request, Event $event, array $users): array
    {
        $enabled = $event->enabledAddons();

        $perUser = [];
        $total = 0.0;
        $index = 0;

        foreach ($users as $user) {
            $perUser[$user->id] = [];

            foreach ($enabled as $addon) {
                $handler = $addon->handler();
                if (! $handler) {
                    continue;
                }

                // Never record a closed add-on, even if inputs were tampered in.
                if (! $addon->isOpen()) {
                    continue;
                }

                // Group-scoped add-ons (donation, potluck, ...) are answered once
                // for the whole submission — record them against the first user only.
                if ($handler->scope() === AddonHandler::SCOPE_GROUP && $index > 0) {
                    continue;
                }

                $attrs = $handler->parseAnswer($request, $addon, $user, $index);
                if ($attrs === null) {
                    continue;
                }

                $perUser[$user->id][] = ['addon' => $addon, 'attrs' => $attrs];
                $total += (float) ($attrs['amount'] ?? 0);
            }

            $index++;
        }

        return ['perUser' => $perUser, 'total' => round($total, 2)];
    }

    /**
     * Persist the parsed answers for a single registration.
     *
     * @param  array<int, array{addon: \App\Models\EventAddon, attrs: array}>  $items
     */
    public function persist(EventRegistration $registration, array $items): void
    {
        foreach ($items as $item) {
            $attrs = $item['attrs'];

            EventRegistrationAddon::updateOrCreate(
                [
                    'event_registration_id' => $registration->id,
                    'event_addon_id' => $item['addon']->id,
                ],
                [
                    'type' => $item['addon']->type,
                    'selected' => $attrs['selected'] ?? false,
                    'quantity' => $attrs['quantity'] ?? null,
                    'value' => $attrs['value'] ?? null,
                    'amount' => $attrs['amount'] ?? null,
                    'data' => $attrs['data'] ?? null,
                ]
            );
        }
    }
}
