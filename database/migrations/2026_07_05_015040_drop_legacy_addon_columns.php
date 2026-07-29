<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy add-on columns now that everything reads/writes the add-on
 * tables (event_addons, event_registration_addons). All data was backfilled by
 * the earlier 2026_07_03/04 backfill migrations. Guarded with hasColumn so it's
 * safe to run against DBs at slightly different states.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->drop('events', [
            'cost', 'cost_type', 'person_2_discount', 'person_3_discount', 'person_4_discount', 'person_5_discount',
            'donations', 'potluck', 'potluck_items', 'potluck_open_signup', 'ask_volunteers', 'volunteer_options',
            'tshirt_deadline', 'max_guests_per_person',
        ]);

        $this->drop('event_registrations', [
            'tshirt_size', 'donation_amount', 'potluck_item_id', 'potluck_open_item', 'volunteer_selections', 'guests',
        ]);

        // Archive mirror table (never read/written by app code).
        $this->drop('event_registrations_deleted', [
            'tshirt_size', 'donation_amount', 'potluck_item', 'potluck_item_id', 'potluck_open_item', 'volunteer_selections', 'guests',
        ]);
    }

    private function drop(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $present = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
        if (empty($present)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($present) {
            $t->dropColumn($present);
        });
    }

    public function down(): void
    {
        // Best-effort restore (nullable); original data is not recovered.
        Schema::table('events', function (Blueprint $t) {
            $t->decimal('cost', 8, 2)->default(0);
            $t->string('cost_type')->nullable();
            $t->decimal('person_2_discount', 8, 2)->default(0);
            $t->decimal('person_3_discount', 8, 2)->default(0);
            $t->decimal('person_4_discount', 8, 2)->default(0);
            $t->decimal('person_5_discount', 8, 2)->default(0);
            $t->boolean('donations')->default(false);
            $t->boolean('potluck')->default(false);
            $t->longText('potluck_items')->nullable();
            $t->boolean('potluck_open_signup')->default(false);
            $t->boolean('ask_volunteers')->default(false);
            $t->longText('volunteer_options')->nullable();
            $t->date('tshirt_deadline')->nullable();
            $t->integer('max_guests_per_person')->nullable();
        });

        Schema::table('event_registrations', function (Blueprint $t) {
            $t->string('tshirt_size')->nullable();
            $t->decimal('donation_amount', 8, 2)->nullable();
            $t->unsignedInteger('potluck_item_id')->nullable();
            $t->string('potluck_open_item')->nullable();
            $t->longText('volunteer_selections')->nullable();
            $t->unsignedInteger('guests')->nullable();
        });
    }
};
