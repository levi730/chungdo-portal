{{--
    A store item on the home page. $large is the feature treatment at the top;
    without it this is an ordinary grid card.
--}}
@php($large = $large ?? false)
@php($image = $product->image())
@php($range = $product->priceRange())
@php($countdown = $product->ordersCloseCountdown())

<div class="card h-100">
    <div class="card-status-top bg-green"></div>

    @if($image)
        <a href="{{ route('store.show', $product->slug) }}">
            <img class="card-img-top" alt="{{ $product->name }}"
                 style="height:{{ $large ? '320px' : '200px' }};object-fit:cover;"
                 src="{{ glideCropUrlFromMedia($image, 600, 400) }}">
        </a>
    @endif

    <div class="card-body">
        @if($large)
            <div class="text-uppercase text-secondary small mb-1" style="letter-spacing:.05em;">Featured</div>
        @endif

        <h3 class="{{ $large ? 'h1' : 'card-title' }} mb-1">{{ $product->name }}</h3>

        @if($range)
            <div class="text-secondary">
                @if($range['low'] == $range['high'])
                    ${{ number_format($range['low'], 2) }}
                @else
                    From ${{ number_format($range['low'], 2) }}
                @endif
            </div>
        @endif

        @if($countdown)
            <span class="badge bg-green-lt mt-2">{{ $countdown }}</span>
        @endif
    </div>

    <div class="card-footer">
        <div class="d-flex">
            <a href="{{ route('store.show', $product->slug) }}"
               class="btn btn-primary ms-auto {{ $large ? 'btn-lg' : '' }}">Shop</a>
        </div>
    </div>
</div>
