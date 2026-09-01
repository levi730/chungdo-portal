@extends('layouts.dashboard')

@section('page-title')
    Store
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        {{-- The heading comes from @section('page-title'); repeating it here
             would print "Store" twice. --}}
        <div class="d-flex justify-content-end mb-3">
            @include('store.partials.cart-button')
        </div>

        @include('store.partials.flash')

        @forelse($products->chunk(3) as $row)
            <div class="row row-cards mb-3">
                @foreach($row as $product)
                    @php($range = $product->priceRange())
                    @php($image = $product->image())
                    <div class="col-md-4">
                        <div class="card h-100">
                            @if($image)
                                <a href="{{ route('store.show', $product->slug) }}">
                                    <img class="card-img-top" alt="{{ $product->name }}"
                                         src="{{ glideCropUrlFromMedia($image, 600, 400) }}">
                                </a>
                            @endif
                            <div class="card-body d-flex flex-column">
                                <h3 class="card-title mb-1">
                                    <a href="{{ route('store.show', $product->slug) }}">{{ $product->name }}</a>
                                </h3>
                                @if($range)
                                    <div class="text-muted mb-2">
                                        @if($range['low'] == $range['high'])
                                            ${{ number_format($range['low'], 2) }}
                                        @else
                                            From ${{ number_format($range['low'], 2) }}
                                        @endif
                                    </div>
                                @endif
                                @php($run = $product->openRun())
                                @if($run?->closes_at)
                                    <div class="small text-muted mb-2">
                                        Orders close {{ $run->closes_at->format('M j, Y') }}
                                    </div>
                                @endif
                                <a href="{{ route('store.show', $product->slug) }}" class="btn btn-primary mt-auto">View</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="card">
                <div class="card-body text-muted">
                    Nothing is on sale right now. Check back when the next order window opens.
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
