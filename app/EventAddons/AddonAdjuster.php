<?php

namespace App\EventAddons;

use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use Illuminate\Http\Request;

/**
 * Plans and applies post-registration add-on changes for a single registration.
 * Only open, per-student, optional add-ons are adjustable (the base registration
 * fee is fixed once registered). The net of the change decides what happens:
 *   net > 0  -> charge the difference, then apply
 *   net == 0 -> apply immediately (e.g. a t-shirt size swap)
 *   net < 0  -> a refund, held for admin approval (see AddonChangeRequest)
 */
class AddonAdjuster
{
    /**
     * Compare the desired add-on answers (parsed from the request) against what
     * the registration currently has.
     *
     * @return array{changes: array<int, array{addon: \App\Models\EventAddon, attrs: array|null, currentAmount: float, desiredAmount: float}>, net: float}
     */
    public function planChange(Request $request, EventRegistration $registration): array
    {
        $event = $registration->event;
        $user = $registration->user;
        $current = $registration->addonAnswers->keyBy('event_addon_id');

        $changes = [];
        $currentTotal = 0.0;
        $desiredTotal = 0.0;

        foreach ($event->enabledAddons() as $addon) {
            $handler = $addon->handler();

            if (! $handler
                || $addon->type === 'registration_fee'
                || $handler->scope() !== AddonHandler::SCOPE_PER_STUDENT
                || ! $addon->isOpen()) {
                continue;
            }

            $attrs = $handler->parseAnswer($request, $addon, $user, 0); // null = removed
            $currentAnswer = $current->get($addon->id);

            if (! $currentAnswer && $attrs === null) {
                continue; // nothing here before or after
            }

            $currentAmount = (float) ($currentAnswer->amount ?? 0);
            $desiredAmount = (float) ($attrs['amount'] ?? 0);

            $changes[] = [
                'addon' => $addon,
                'attrs' => $attrs,
                'currentAmount' => $currentAmount,
                'desiredAmount' => $desiredAmount,
            ];

            $currentTotal += $currentAmount;
            $desiredTotal += $desiredAmount;
        }

        return [
            'changes' => $changes,
            'net' => round($desiredTotal - $currentTotal, 2),
        ];
    }

    /**
     * Apply a set of planned changes to the registration's answers. A newly
     * charged amount is linked to $paymentId; unchanged paid answers keep their
     * existing payment link; removed answers are deleted.
     *
     * @param  array<int, array{addon: \App\Models\EventAddon, attrs: array|null}>  $changes
     */
    public function apply(EventRegistration $registration, array $changes, ?int $paymentId = null): void
    {
        foreach ($changes as $change) {
            $addon = $change['addon'];
            $attrs = $change['attrs'];

            $existing = EventRegistrationAddon::where('event_registration_id', $registration->id)
                ->where('event_addon_id', $addon->id)
                ->first();

            if ($attrs === null) {
                $existing?->delete();

                continue;
            }

            $paymentLink = null;
            if (($attrs['amount'] ?? 0) > 0) {
                $paymentLink = $paymentId ?? $existing?->payment_id;
            }

            EventRegistrationAddon::updateOrCreate(
                ['event_registration_id' => $registration->id, 'event_addon_id' => $addon->id],
                [
                    'type' => $addon->type,
                    'selected' => $attrs['selected'] ?? false,
                    'quantity' => $attrs['quantity'] ?? null,
                    'value' => $attrs['value'] ?? null,
                    'amount' => $attrs['amount'] ?? null,
                    'payment_id' => $paymentLink,
                    'data' => $attrs['data'] ?? null,
                ]
            );
        }
    }

    /**
     * Serialize planned changes for storage on an AddonChangeRequest so they can
     * be applied verbatim on approval.
     *
     * @param  array<int, array{addon: \App\Models\EventAddon, attrs: array|null}>  $changes
     */
    public function serialize(array $changes): array
    {
        return array_map(fn ($c) => [
            'event_addon_id' => $c['addon']->id,
            'type' => $c['addon']->type,
            'attrs' => $c['attrs'],
        ], $changes);
    }

    /**
     * Apply changes previously stored via serialize() (e.g. on approval of a
     * refund request).
     */
    public function applySerialized(EventRegistration $registration, array $serialized, ?int $paymentId = null): void
    {
        $changes = [];
        foreach ($serialized as $item) {
            $addon = EventAddon::find($item['event_addon_id']);
            if (! $addon) {
                continue;
            }
            $changes[] = ['addon' => $addon, 'attrs' => $item['attrs'] ?? null];
        }

        $this->apply($registration, $changes, $paymentId);
    }
}
