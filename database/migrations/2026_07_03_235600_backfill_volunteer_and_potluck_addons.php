<?php

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use App\Models\PotluckOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Port existing volunteer and potluck data onto the add-on framework:
 *  - events.ask_volunteers / .volunteer_options -> a volunteer add-on
 *  - events.potluck / .potluck_open_signup       -> a potluck add-on
 *  - event_registrations.volunteer_selections    -> volunteer answer rows
 *  - event_registrations.potluck_item_id / .potluck_open_item -> potluck answers
 *
 * Legacy columns are left in place (registerProcess still writes them and the
 * existing by-volunteer / by-potluck reports still read them). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $nextSort = fn (int $eventId): int => (int) DB::table('event_addons')
            ->where('event_id', $eventId)->max('sort_order') + 1;

        // Volunteer add-on config.
        foreach (DB::table('events')->where('ask_volunteers', true)->get() as $event) {
            $options = collect(json_decode($event->volunteer_options ?? '[]', true) ?: [])
                ->map(fn ($o) => is_array($o) ? ($o['item'] ?? null) : $o)
                ->filter()
                ->values()
                ->all();

            $addon = EventAddon::firstOrNew(['event_id' => $event->id, 'type' => 'volunteer']);
            $addon->enabled = true;
            $addon->settings = ['options' => $options];
            if (! $addon->exists) {
                $addon->sort_order = $nextSort($event->id);
            }
            $addon->save();
        }

        // Potluck add-on config.
        foreach (DB::table('events')->where('potluck', true)->get() as $event) {
            $addon = EventAddon::firstOrNew(['event_id' => $event->id, 'type' => 'potluck']);
            $addon->enabled = true;
            $addon->settings = ['open_signup' => (bool) ($event->potluck_open_signup ?? false)];
            if (! $addon->exists) {
                $addon->sort_order = $nextSort($event->id);
            }
            $addon->save();
        }

        $addonsByEvent = EventAddon::whereIn('type', ['volunteer', 'potluck'])->get()
            ->groupBy('event_id')
            ->map(fn ($group) => $group->keyBy('type'));

        // Volunteer answers.
        DB::table('event_registrations')->whereNotNull('volunteer_selections')->orderBy('id')
            ->chunkById(500, function ($regs) use ($addonsByEvent) {
                foreach ($regs as $reg) {
                    $addon = $addonsByEvent[$reg->event_id]['volunteer'] ?? null;
                    $items = json_decode($reg->volunteer_selections ?? '[]', true) ?: [];
                    if (! $addon || empty($items)) {
                        continue;
                    }
                    EventRegistrationAddon::updateOrCreate(
                        ['event_registration_id' => $reg->id, 'event_addon_id' => $addon->id],
                        ['type' => 'volunteer', 'selected' => true, 'data' => $items]
                    );
                }
            });

        // Potluck answers.
        DB::table('event_registrations')
            ->where(fn ($q) => $q->whereNotNull('potluck_item_id')->orWhereNotNull('potluck_open_item'))
            ->orderBy('id')
            ->chunkById(500, function ($regs) use ($addonsByEvent) {
                foreach ($regs as $reg) {
                    $addon = $addonsByEvent[$reg->event_id]['potluck'] ?? null;
                    if (! $addon) {
                        continue;
                    }

                    $open = trim((string) $reg->potluck_open_item);
                    $value = $open !== '' ? $open : null;
                    if ($reg->potluck_item_id && ($opt = PotluckOptions::find($reg->potluck_item_id))) {
                        $value = trim($opt->category.' - '.$opt->item, ' -');
                    }

                    if ($value === null && ! $reg->potluck_item_id) {
                        continue;
                    }

                    EventRegistrationAddon::updateOrCreate(
                        ['event_registration_id' => $reg->id, 'event_addon_id' => $addon->id],
                        [
                            'type' => 'potluck',
                            'selected' => true,
                            'value' => $value,
                            'data' => [
                                'potluck_item_id' => $reg->potluck_item_id ?: null,
                                'open_item' => $open !== '' ? $open : null,
                            ],
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        EventRegistrationAddon::whereIn('type', ['volunteer', 'potluck'])->delete();
        EventAddon::whereIn('type', ['volunteer', 'potluck'])->delete();
    }
};
