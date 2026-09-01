<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A cached still image of an event's location.
 *
 * The dashboard used to embed a live 600x450 Google map in every event card —
 * three iframes and 1,350px of page on a single visit. This fetches ONE picture
 * per event instead, stores it on our own disk, and the live map is only loaded
 * if someone actually clicks it.
 *
 * The cache key is a hash of the event's map_url, so the image is fetched once
 * and reused for every visitor, forever, until the map_url actually changes —
 * at which point the hash changes and a new one is fetched. Cost is one Static
 * Maps request per venue, not one per page view.
 *
 * Everything degrades to null when the API key is missing or the coordinates
 * can't be read, and the card falls back to showing the address.
 */
class EventMapSnapshot
{
    private const DISK = 'public';

    private const DIRECTORY = 'event-maps';

    /** The public URL of the cached image, or null if there isn't one. */
    public function url(Event $event): ?string
    {
        $path = $this->path($event);

        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Fetch and store the snapshot. Returns true if an image is on disk
     * afterwards — including when it was already there and $force is false.
     */
    public function generate(Event $event, bool $force = false): bool
    {
        $path = $this->path($event);

        if (! $path) {
            return false;
        }

        if (! $force && Storage::disk(self::DISK)->exists($path)) {
            return true;
        }

        $coordinates = $this->coordinates($event);
        $key = config('services.google_maps.key');

        if (! $coordinates || ! $key) {
            return false;
        }

        [$latitude, $longitude] = $coordinates;

        try {
            $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/staticmap', [
                'center' => $latitude.','.$longitude,
                'zoom' => config('services.google_maps.zoom'),
                'size' => config('services.google_maps.size'),
                'scale' => config('services.google_maps.scale'),
                'markers' => 'color:red|'.$latitude.','.$longitude,
                'key' => $key,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Event map snapshot request failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            return false;
        }

        // Google answers errors with a 200 and a PNG saying so; only keep a
        // body that actually looks like an image.
        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            Log::warning('Event map snapshot rejected', [
                'event_id' => $event->id,
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
            ]);

            return false;
        }

        Storage::disk(self::DISK)->put($path, $response->body());

        $this->forgetStaleSnapshots($event, $path);

        return true;
    }

    /**
     * Latitude and longitude, read out of the Google embed URL already stored
     * on the event. Its `pb` parameter carries !2d<longitude>!3d<latitude>;
     * undocumented, but stable across every event in this database and it saves
     * geocoding the address.
     *
     * @return array{0: string, 1: string}|null [latitude, longitude]
     */
    public function coordinates(Event $event): ?array
    {
        if (blank($event->map_url)) {
            return null;
        }

        if (! preg_match('/!2d(-?\d+\.\d+)!3d(-?\d+\.\d+)/', $event->map_url, $matches)) {
            return null;
        }

        return [$matches[2], $matches[1]];
    }

    /** Where this event's image lives, keyed by the map_url it was made from. */
    public function path(Event $event): ?string
    {
        if (blank($event->map_url)) {
            return null;
        }

        return self::DIRECTORY.'/'.$event->id.'-'.md5($event->map_url).'.png';
    }

    /** Drop images made from an earlier map_url for this same event. */
    private function forgetStaleSnapshots(Event $event, string $keep): void
    {
        foreach (Storage::disk(self::DISK)->files(self::DIRECTORY) as $file) {
            if ($file !== $keep && str_starts_with(basename($file), $event->id.'-')) {
                Storage::disk(self::DISK)->delete($file);
            }
        }
    }
}
