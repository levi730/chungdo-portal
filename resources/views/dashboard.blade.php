@extends('layouts.app')

@section('page-title')
    Dashboard
@endsection

@section('content')
<div class="container-xl">
    <!--<div class="row mb-5">
        <div class="col-12 bg-primary-lt fs-2 text-center p-3">
            Sparring tournament registration is now open for the Winter 2025 Tournament!
        </div>
    </div>-->
    <div class="row row-cards">
        <div class="col-sm-6">

            <h1 class="display-3">Welcome!</h1>

            <h3>
                You've made it to the main dashboard!
            </h3>

            <p>
                Check back here in the future for more information and events!
            </p>
        </div>

        <div class="col-sm-6">
            @foreach($next_events as $next_event)
            <div class="card mb-5">
                <div class="card-status-top bg-primary"></div>
                <div class="card-body">
                    <h3 class="card-title">{{ $next_event->name }}</h3>
                    <p>{{ $next_event->startdatetime->format('l, F j, Y | g:ia') }}</p>
                    <p>
                        {!! nl2br($next_event->location) !!}
                    </p>

                    @if($next_event->map_url)
                        <iframe src="{{ $next_event->map_url }}" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    @endif

                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="/event/{{ $next_event->slug }}/register" class="btn btn-primary ms-auto">Details</a>
                    </div>
                </div>
            </div>
            @endforeach


            {{-- Store items, shown only when someone has ticked "Feature on the
                 home page". Unlike events this never fills in with whatever is
                 on sale — a store row appearing unbidden would be a surprise. --}}
            @foreach($featured_products as $featured_product)
                @php($productImage = $featured_product->image())
                @php($priceRange = $featured_product->priceRange())
                <div class="card mb-5">
                    <div class="card-status-top bg-green"></div>
                    @if($productImage)
                        <a href="{{ route('store.show', $featured_product->slug) }}">
                            <img class="card-img-top" alt="{{ $featured_product->name }}"
                                 src="{{ glideCropUrlFromMedia($productImage, 600, 400) }}">
                        </a>
                    @endif
                    <div class="card-body">
                        <h3 class="card-title">{{ $featured_product->name }}</h3>
                        @if($priceRange)
                            <p class="text-secondary mb-1">
                                @if($priceRange['low'] == $priceRange['high'])
                                    ${{ number_format($priceRange['low'], 2) }}
                                @else
                                    From ${{ number_format($priceRange['low'], 2) }}
                                @endif
                            </p>
                        @endif
                        @php($openRun = $featured_product->openRun())
                        @if($openRun?->closes_at)
                            <p class="text-secondary mb-0">
                                Orders close {{ $openRun->closes_at->format('F j, Y') }}
                            </p>
                        @endif
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <a href="{{ route('store.show', $featured_product->slug) }}" class="btn btn-primary ms-auto">Shop</a>
                        </div>
                    </div>
                </div>
            @endforeach

            <a href="https://linktr.ee/chungdotkd" target="_blank"><div class="card mt-5">
                <!-- Photo -->
                <div class="img-responsive img-responsive-21x9 card-img-top" style="background-image: url('/img/linktree_chungdotkd.jpg')"></div>
                <div class="card-body">
                    <h3 class="card-title">Linktree</h3>
                    <p class="text-secondary">Check out our Linktree for updates and information across the organization.</p>
                </div>
            </div>
            </a>

        </div>
    </div>
</div>
@endsection
