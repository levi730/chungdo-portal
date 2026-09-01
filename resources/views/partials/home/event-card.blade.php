{{--
    An event on the home page. $large is the feature treatment at the top of the
    page; without it this is an ordinary grid card.
--}}
@php($large = $large ?? false)
@php($countdown = $event->countdown())

<div class="card h-100">
    <div class="card-status-top bg-primary"></div>
    <div class="card-body">
        @if($large)
            <div class="text-uppercase text-secondary small mb-1" style="letter-spacing:.05em;">Featured</div>
        @endif

        <h3 class="{{ $large ? 'h1' : 'card-title' }} mb-1">{{ $event->name }}</h3>

        <div class="text-secondary">{{ $event->startdatetime->format('l, F j, Y | g:ia') }}</div>
        <div class="text-secondary mt-1">{!! nl2br(e($event->location)) !!}</div>

        @if($countdown)
            <span class="badge bg-primary-lt mt-2">{{ $countdown }}</span>
        @endif

        @include('partials.event.map-preview', ['event' => $event])
    </div>

    {{-- route() throws on a null slug, which would take the whole dashboard
         down over one malformed event. --}}
    @if($event->slug)
        <div class="card-footer">
            <div class="d-flex">
                <a href="{{ route('event.register', $event->slug) }}"
                   class="btn btn-primary ms-auto {{ $large ? 'btn-lg' : '' }}">Details</a>
            </div>
        </div>
    @endif
</div>
