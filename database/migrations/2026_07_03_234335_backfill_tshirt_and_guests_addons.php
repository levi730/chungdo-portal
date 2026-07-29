<?php

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Port existing t-shirt and guests data onto the add-on framework:
 *  - events.tshirt_deadline        -> a tshirt add-on (closes_at = deadline)
 *  - events.max_guests_per_person  -> a guests add-on (settings.max)
 *  - event_registrations.tshirt_size / .guests -> event_registration_addons rows
 *
 * The legacy columns are left in place (registerProcess still dual-writes them)
 * so nothing that still reads them breaks; they can be dropped once verified.
 * Idempotent, so it's safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $nextSort = fn (int $eventId): int => (int) DB::table('event_addons')
            ->where('event_id', $eventId)->max('sort_order') + 1;

        // T-shirt add-on for every event that had a t-shirt deadline.
        foreach (DB::table('events')->whereNotNull('tshirt_deadline')->get() as $event) {
            $addon = EventAddon::firstOrNew(['event_id' => $event->id, 'type' => 'tshirt']);
            $addon->enabled = true;
            $addon->closes_at = Carbon::parse($event->tshirt_deadline)->endOfDay();
            if (! $addon->exists) {
                $addon->settings = [];
                $addon->sort_order = $nextSort($event->id);
            }
            $addon->save();
        }

        // Guests add-on for every event that allowed guests.
        foreach (DB::table('events')->where('max_guests_per_person', '>', 0)->get() as $event) {
            $addon = EventAddon::firstOrNew(['event_id' => $event->id, 'type' => 'guests']);
            $addon->enabled = true;
            $addon->settings = ['max' => (int) $event->max_guests_per_person];
            if (! $addon->exists) {
                $addon->sort_order = $nextSort($event->id);
            }
            $addon->save();
        }

        // event_id => [type => addon] lookup for writing the answer rows.
        $addonsByEvent = EventAddon::whereIn('type', ['tshirt', 'guests'])->get()
            ->groupBy('event_id')
            ->map(fn ($group) => $group->keyBy('type'));

        DB::table('event_registrations')->whereNotNull('tshirt_size')->orderBy('id')
            ->chunkById(500, function ($regs) use ($addonsByEvent) {
                foreach ($regs as $reg) {
                    $addon = $addonsByEvent[$reg->event_id]['tshirt'] ?? null;
                    if (! $addon) {
                        continue;
                    }
                    EventRegistrationAddon::updateOrCreate(
                        ['event_registration_id' => $reg->id, 'event_addon_id' => $addon->id],
                        ['type' => 'tshirt', 'selected' => true, 'value' => $reg->tshirt_size]
                    );
                }
            });

        DB::table('event_registrations')->where('guests', '>', 0)->orderBy('id')
            ->chunkById(500, function ($regs) use ($addonsByEvent) {
                foreach ($regs as $reg) {
                    $addon = $addonsByEvent[$reg->event_id]['guests'] ?? null;
                    if (! $addon) {
                        continue;
                    }
                    EventRegistrationAddon::updateOrCreate(
                        ['event_registration_id' => $reg->id, 'event_addon_id' => $addon->id],
                        ['type' => 'guests', 'selected' => true, 'quantity' => (int) $reg->guests]
                    );
                }
            });
    }

    public function down(): void
    {
        // Remove the ported answer rows and add-on configs for these two types.
        EventRegistrationAddon::whereIn('type', ['tshirt', 'guests'])->delete();
        EventAddon::whereIn('type', ['tshirt', 'guests'])->delete();
    }
};
