<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventMapSnapshot;
use Illuminate\Console\Command;

/**
 * Backfills the cached map pictures for events that already exist.
 *
 * New and edited events snapshot themselves on save (Event::booted), so this is
 * for the ones that predate the feature, and for re-fetching after a key change.
 */
class CacheEventMaps extends Command
{
    protected $signature = 'events:cache-maps
                            {--force : Re-fetch even when an image is already cached}';

    protected $description = 'Fetch and cache a static map image for each event that has a map URL';

    public function handle(EventMapSnapshot $snapshots): int
    {
        if (! config('services.google_maps.key')) {
            $this->error('GOOGLE_MAPS_KEY is not set — nothing to fetch.');
            $this->line('Event cards will show the venue address until it is.');

            return self::FAILURE;
        }

        $events = Event::whereNotNull('map_url')->where('map_url', '!=', '')->get();

        if ($events->isEmpty()) {
            $this->info('No events have a map URL.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $made = $cached = $failed = 0;

        foreach ($events as $event) {
            $already = ! $force && $snapshots->url($event) !== null;

            if ($already) {
                $cached++;
                continue;
            }

            if ($snapshots->generate($event, $force)) {
                $made++;
                $this->line('  fetched: '.$event->name);
            } else {
                $failed++;
                $this->warn('  could not fetch: '.$event->name.' (no coordinates in its map URL?)');
            }
        }

        $this->info("Done. fetched={$made} already cached={$cached} failed={$failed}");

        return self::SUCCESS;
    }
}
