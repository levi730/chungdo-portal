<?php

use App\Models\EventAddon;
use App\Models\EventRegistrationAddon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Port donation data onto the add-on framework:
 *  - events.donations                         -> a donation add-on
 *  - event_registrations.donation_amount (>0) -> donation answer rows (amount)
 *
 * The legacy donation_amount column is still dual-written by registerProcess.
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $nextSort = fn (int $eventId): int => (int) DB::table('event_addons')
            ->where('event_id', $eventId)->max('sort_order') + 1;

        foreach (DB::table('events')->where('donations', true)->get() as $event) {
            $addon = EventAddon::firstOrNew(['event_id' => $event->id, 'type' => 'donation']);
            $addon->enabled = true;
            if (! $addon->exists) {
                $addon->settings = [];
                $addon->sort_order = $nextSort($event->id);
            }
            $addon->save();
        }

        $donationByEvent = EventAddon::where('type', 'donation')->get()->keyBy('event_id');

        DB::table('event_registrations')->where('donation_amount', '>', 0)->orderBy('id')
            ->chunkById(500, function ($regs) use ($donationByEvent) {
                foreach ($regs as $reg) {
                    $addon = $donationByEvent[$reg->event_id] ?? null;
                    if (! $addon) {
                        continue;
                    }
                    EventRegistrationAddon::updateOrCreate(
                        ['event_registration_id' => $reg->id, 'event_addon_id' => $addon->id],
                        ['type' => 'donation', 'selected' => true, 'amount' => $reg->donation_amount]
                    );
                }
            });
    }

    public function down(): void
    {
        EventRegistrationAddon::where('type', 'donation')->delete();
        EventAddon::where('type', 'donation')->delete();
    }
};
