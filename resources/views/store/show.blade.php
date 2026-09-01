@extends('layouts.dashboard')

@section('page-title')
    {{ $product->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="{{ route('store.index') }}" class="btn btn-link px-0">&larr; Store</a>
            @include('store.partials.cart-button')
        </div>

        @include('store.partials.flash')

        <div class="row">
            <div class="col-md-6 mb-3">
                @php($images = $product->getMedia('product-images'))
                @if($images->count())
                    @include('store.partials.gallery')
                @endif
            </div>

            <div class="col-md-6">
                <h1 class="mb-2">{{ $product->name }}</h1>

                @if($product->description)
                    <div class="markdown-content mb-3">@md($product->description)</div>
                @endif

                @if(! $run)
                    <div class="alert alert-info">
                        @if($nextRun && $nextRun->opens_at)
                            Ordering opens {{ $nextRun->opens_at->format('F j, Y') }}.
                        @else
                            This isn't open for orders right now.
                        @endif
                    </div>
                @elseif($variants->isEmpty())
                    <div class="alert alert-info">Nothing is on sale in this run yet.</div>
                @else
                    @if($run->closes_at)
                        <p class="text-muted">
                            Orders close <strong>{{ $run->closes_at->format('F j, Y \a\t g:ia') }}</strong>.
                            There is no stock count — the print run is sized from the orders taken by then.
                        </p>
                    @endif
                    @if($run->expected_arrival_at)
                        <p class="text-muted">Expected to arrive around <strong>{{ $run->expected_arrival_at->format('F j, Y') }}</strong>.</p>
                    @endif
                    @if($run->pickup_note)
                        <p class="text-muted">{{ $run->pickup_note }}</p>
                    @endif

                    @include('store.partials.variant-picker')
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
