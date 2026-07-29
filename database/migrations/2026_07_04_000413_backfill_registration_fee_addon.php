<?php

use App\Models\EventAddon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Port the base registration fee onto the add-on framework. Every event gets a
 * registration_fee add-on seeded from its legacy cost / cost_type /
 * person_N_discount columns; it is enabled so registration keeps charging as
 * before (a cost of 0 simply charges nothing).
 *
 * Only the config is backfilled — not historical per-registrant answer rows.
 * The old flow only ever stored a flat amount_due and put the discounted total
 * on the paying registrant, so per-person fee attribution can't be reconstructed
 * reliably; historical payments already live in the payments table. New
 * registrations record registration_fee answers going forward.
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('events')->get() as $event) {
            $addon = EventAddon::firstOrNew(['event_id' => $event->id, 'type' => 'registration_fee']);
            $addon->enabled = true;
            $addon->settings = [
                'cost' => (float) $event->cost,
                'cost_type' => $event->cost_type ?: 'per person',
                'discounts' => [
                    '2' => (float) $event->person_2_discount,
                    '3' => (float) $event->person_3_discount,
                    '4' => (float) $event->person_4_discount,
                    '5' => (float) $event->person_5_discount,
                ],
            ];
            if (! $addon->exists) {
                $addon->sort_order = 0; // base fee sorts first
            }
            $addon->save();
        }
    }

    public function down(): void
    {
        EventAddon::where('type', 'registration_fee')->delete();
    }
};
