{{--
    An event's location: a cached still image that swaps itself for the live
    Google map when clicked.

    Three embedded maps used to load on every dashboard visit — 1,350px of page
    and three third-party requests before anyone asked to see a map. The still
    is fetched once per venue and served from our own domain, so a visitor's
    browser never talks to Google unless they click.

    No snapshot (no API key, or coordinates we couldn't read) falls back to the
    address and a Directions link, which is the useful part anyway.
--}}
@php($mapImage = app(\App\Services\EventMapSnapshot::class)->url($event))

@if($event->map_url)
    <div x-data="{ live: false }" class="mt-2">
        <template x-if="! live">
            <button type="button" @click="live = true"
                    class="btn p-0 border-0 w-100 position-relative d-block"
                    style="overflow:hidden;border-radius:6px;line-height:0;"
                    aria-label="Show the map for {{ $event->name }}">
                @if($mapImage)
                    <img src="{{ $mapImage }}" alt="Map of {{ $event->location }}"
                         class="w-100" style="height:180px;object-fit:cover;display:block;">
                @else
                    <div class="w-100 d-flex align-items-center justify-content-center bg-secondary-lt"
                         style="height:120px;">
                        <span class="text-secondary">Show map</span>
                    </div>
                @endif
                <span class="badge bg-dark position-absolute"
                      style="bottom:.5rem;right:.5rem;opacity:.85;">Show map</span>
            </button>
        </template>

        <template x-if="live">
            <iframe src="{{ $event->map_url }}" width="100%" height="300" style="border:0;border-radius:6px;"
                    allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    title="Map of {{ $event->location }}"></iframe>
        </template>
    </div>
@endif
