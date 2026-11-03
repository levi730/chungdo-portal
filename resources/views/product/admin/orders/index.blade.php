@extends('layouts.dashboard')

@section('page-title')
    Store Orders
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="mb-0">Store orders</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Manage products</a>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Export financials</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="{{ route('orders.financials') }}">All store orders</a>
                        @foreach($products as $product)
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('orders.financials', ['product' => $product->id]) }}">{{ $product->name }} — all runs</a>
                            @foreach($product->runs as $run)
                                <a class="dropdown-item ps-5" href="{{ route('orders.financials', ['run' => $run->id]) }}">{{ $run->name }}</a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <p class="text-muted">
            Pick lists — who collects what at each school — are on each print run:
            @foreach($products as $product)
                @foreach($product->runs as $run)
                    <a href="{{ route('products.runs.pick-list', [$product, $run]) }}" class="ms-1">{{ $product->name }} / {{ $run->name }}</a>@if(! $loop->parent->last || ! $loop->last),@endif
                @endforeach
            @endforeach
        </p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <livewire:admin.product-orders-table />
            </div>
        </div>
    </div>
</div>
@endsection
