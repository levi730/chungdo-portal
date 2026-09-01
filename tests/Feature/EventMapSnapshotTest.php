<?php

use App\Models\Event;
use App\Services\EventMapSnapshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The dashboard used to embed a live Google map in every event card — three
 * iframes and 1,350px of page per visit, before anyone asked to see a map.
 *
 * Now one still image is fetched per venue, cached on our disk, and the live
 * map only loads on click. The cost question is entirely about how often this
 * calls Google, so that is what these pin: once per map_url, ever.
 */
beforeEach(function () {
    Storage::fake('public');
    config(['services.google_maps.key' => 'test-key']);
});

function fakeMapResponse(): void
{
    Http::fake(['maps.googleapis.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);
}

/** An embed URL of the shape the events table actually holds. */
function embedUrl(string $lat = '38.7187307', string $lng = '-90.4206949'): string
{
    return 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3112.95!2d'.$lng.'!3d'.$lat.'!2m3!1f0!2f0!3f0';
}

function mapEvent(array $attrs = []): Event
{
    $n = uniqid();

    return Event::create(array_merge([
        'name' => 'Map Event '.$n,
        'slug' => 'map-event-'.$n,
        'cost' => 0,
        'map_url' => embedUrl(),
    ], $attrs));
}

it('reads coordinates out of the embed url', function () {
    fakeMapResponse();
    $event = mapEvent();

    expect(app(EventMapSnapshot::class)->coordinates($event))
        ->toBe(['38.7187307', '-90.4206949']);
});

it('fetches the image once and reuses it', function () {
    fakeMapResponse();
    $event = mapEvent();          // saved() fetches it

    $snapshots = app(EventMapSnapshot::class);

    // Every later call is a cache hit — this is the whole cost argument.
    $snapshots->generate($event);
    $snapshots->generate($event);
    $snapshots->url($event);

    Http::assertSentCount(1);
});

it('re-fetches only when the map url actually changes', function () {
    fakeMapResponse();
    $event = mapEvent();
    Http::assertSentCount(1);

    // Saving other fields must not cost an API call.
    $event->update(['name' => 'Renamed']);
    $event->update(['location' => 'Somewhere else']);
    Http::assertSentCount(1);

    $event->update(['map_url' => embedUrl('39.1', '-91.2')]);
    Http::assertSentCount(2);
});

it('serves the cached image from our own disk', function () {
    fakeMapResponse();
    $event = mapEvent();

    expect(app(EventMapSnapshot::class)->url($event))->toContain('event-maps');
    Storage::disk('public')->assertExists(app(EventMapSnapshot::class)->path($event));
});

it('throws away the image made from a previous map url', function () {
    fakeMapResponse();
    $event = mapEvent();
    $old = app(EventMapSnapshot::class)->path($event);

    $event->update(['map_url' => embedUrl('39.1', '-91.2')]);

    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists(app(EventMapSnapshot::class)->path($event->fresh()));
});

it('does nothing without an api key', function () {
    config(['services.google_maps.key' => null]);
    fakeMapResponse();

    $event = mapEvent();

    Http::assertNothingSent();
    expect(app(EventMapSnapshot::class)->url($event))->toBeNull();
});

it('keeps no image when the url carries no coordinates', function () {
    fakeMapResponse();
    $event = mapEvent(['map_url' => 'https://example.com/not-a-google-embed']);

    Http::assertNothingSent();
    expect(app(EventMapSnapshot::class)->url($event))->toBeNull();
});

it('refuses a response that is not an image', function () {
    // Google answers some errors with a 200 and a body explaining itself.
    Http::fake(['maps.googleapis.com/*' => Http::response('over quota', 200, ['Content-Type' => 'text/html'])]);

    $event = mapEvent();

    expect(app(EventMapSnapshot::class)->url($event))->toBeNull();
});

it('survives the request failing', function () {
    Http::fake(fn () => throw new RuntimeException('network down'));

    $event = mapEvent();

    expect($event->exists)->toBeTrue()
        ->and(app(EventMapSnapshot::class)->url($event))->toBeNull();
});

it('shows the map as a still, not a live embed, until asked', function () {
    fakeMapResponse();
    $event = mapEvent(['startdatetime' => now()->addWeek(), 'highlighted' => true]);

    $user = App\Models\User::factory()->create();
    $user->markEmailAsVerified();

    $response = $this->actingAs($user)->get('/dashboard');

    // The still is there and the iframe is behind an x-if, not loaded up front.
    $response->assertOk()
        ->assertSee('event-maps', false)
        ->assertSee('Show map');
});
