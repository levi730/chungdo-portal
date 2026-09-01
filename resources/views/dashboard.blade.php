@extends('layouts.app')

@section('page-title')
    Dashboard
@endsection

@section('content')
<div class="container-xl">

    {{-- A band across the top, replacing a half-width column that held three
         lines of placeholder text and 96% empty space. Everything here is
         already-loaded data about the person reading it. --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md">
                    <h1 class="mb-1">Welcome back{{ $user?->firstname ? ', '.$user->firstname : '' }}!</h1>
                    <div class="text-secondary">
                        @if($user?->school)
                            {{ $user->school->name }}@if($user->rank) &middot; {{ $user->rank->rank }}@endif
                        @else
                            Chung Do Association
                        @endif
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="row g-2 text-center">
                        <div class="col">
                            <div class="h1 mb-0">{{ $my_registrations->count() }}</div>
                            <div class="text-secondary small">
                                {{ Str::plural('registration', $my_registrations->count()) }}
                            </div>
                        </div>
                        @if($household_count)
                            <div class="col">
                                <div class="h1 mb-0">{{ $household_count }}</div>
                                <div class="text-secondary small">in your household</div>
                            </div>
                        @endif
                        @if($my_orders->isNotEmpty())
                            <div class="col">
                                <div class="h1 mb-0">{{ $my_orders->count() }}</div>
                                <div class="text-secondary small">
                                    store {{ Str::plural('order', $my_orders->count()) }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($my_registrations->isNotEmpty())
                <div class="mt-3 pt-3 border-top">
                    <div class="text-secondary small mb-1">You're registered for</div>
                    @foreach($my_registrations as $registration)
                        @if($registration->slug)
                            <a href="{{ route('event.register', $registration->slug) }}" class="badge bg-blue-lt me-1 mb-1">
                                {{ $registration->name }}
                            </a>
                        @else
                            <span class="badge bg-blue-lt me-1 mb-1">{{ $registration->name }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- One grid for everything, so nothing is stranded in a half-empty column. --}}
    <div class="row row-cards">

        @foreach($next_events as $next_event)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-status-top bg-primary"></div>
                    <div class="card-body">
                        <h3 class="card-title mb-1">{{ $next_event->name }}</h3>
                        <div class="text-secondary">{{ $next_event->startdatetime->format('l, F j, Y | g:ia') }}</div>
                        <div class="text-secondary mt-1">{!! nl2br(e($next_event->location)) !!}</div>
                        @include('partials.event.map-preview', ['event' => $next_event])
                    </div>
                    {{-- Guarded because route() throws on a null slug, which
                         would take the whole dashboard down for everyone over
                         one malformed event. Same check as the events index. --}}
                    @if($next_event->slug)
                        <div class="card-footer">
                            <div class="d-flex">
                                <a href="{{ route('event.register', $next_event->slug) }}" class="btn btn-primary ms-auto">Details</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Store items appear only when someone ticked "Feature on the portal
             home page"; a store row turning up unbidden would be a surprise. --}}
        @foreach($featured_products as $featured_product)
            @php($productImage = $featured_product->image())
            @php($priceRange = $featured_product->priceRange())
            @php($openRun = $featured_product->openRun())
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-status-top bg-green"></div>
                    @if($productImage)
                        <a href="{{ route('store.show', $featured_product->slug) }}">
                            <img class="card-img-top" alt="{{ $featured_product->name }}"
                                 style="height:200px;object-fit:cover;"
                                 src="{{ glideCropUrlFromMedia($productImage, 600, 400) }}">
                        </a>
                    @endif
                    <div class="card-body">
                        <h3 class="card-title mb-1">{{ $featured_product->name }}</h3>
                        @if($priceRange)
                            <div class="text-secondary">
                                @if($priceRange['low'] == $priceRange['high'])
                                    ${{ number_format($priceRange['low'], 2) }}
                                @else
                                    From ${{ number_format($priceRange['low'], 2) }}
                                @endif
                            </div>
                        @endif
                        @if($openRun?->closes_at)
                            <div class="text-secondary">Orders close {{ $openRun->closes_at->format('F j, Y') }}</div>
                        @endif
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <a href="{{ route('store.show', $featured_product->slug) }}" class="btn btn-primary ms-auto">Shop</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-md-6 col-xl-4">
            <a href="https://linktr.ee/chungdotkd" target="_blank" rel="noopener" class="card h-100">
                <div class="img-responsive img-responsive-21x9 card-img-top" style="background-image: url('/img/linktree_chungdotkd.jpg')"></div>
                <div class="card-body">
                    <h3 class="card-title">Linktree</h3>
                    <p class="text-secondary mb-0">Check out our Linktree for updates and information across the organization.</p>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
