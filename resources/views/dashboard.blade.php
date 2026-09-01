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

    {{-- The feature row: the top one or two highlighted things, events and store
         items alike, ranked on one shared highlight_order scale. One featured
         thing fills the width on its own rather than sitting beside a gap;
         nothing featured means no row at all. --}}
    @if($featured->isNotEmpty())
        <div class="row row-cards mb-3">
            @foreach($featured as $item)
                <div class="{{ $featured->count() === 1 ? 'col-12' : 'col-lg-6' }}">
                    @if($item instanceof \App\Models\Event)
                        @include('partials.home.event-card', ['event' => $item, 'large' => true])
                    @else
                        @include('partials.home.product-card', ['product' => $item, 'large' => true])
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Everything else, same priority order, at normal size. --}}
    @if($rest->isNotEmpty())
        <div class="row row-cards">
            @foreach($rest as $item)
                <div class="col-md-6 col-xl-4">
                    @if($item instanceof \App\Models\Event)
                        @include('partials.home.event-card', ['event' => $item])
                    @else
                        @include('partials.home.product-card', ['product' => $item])
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Linktree is evergreen rather than timely, so it no longer occupies a
         card slot equal to a tournament. --}}
    <a href="https://linktr.ee/chungdotkd" target="_blank" rel="noopener"
       class="card card-sm mt-3 text-decoration-none">
        <div class="card-body d-flex align-items-center">
            <span class="avatar me-3" style="background-image: url('/img/linktree_chungdotkd.jpg')"></span>
            <div>
                <div class="fw-bold">Linktree</div>
                <div class="text-secondary small">Updates and information across the organization.</div>
            </div>
            <span class="ms-auto text-secondary">&rarr;</span>
        </div>
    </a>
</div>
@endsection
