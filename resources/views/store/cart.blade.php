@extends('layouts.dashboard')

@section('page-title')
    Your cart
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        {{-- Heading comes from @section('page-title'); repeating it prints twice. --}}
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('store.index') }}" class="btn btn-link">Keep shopping</a>
        </div>

        @include('store.partials.flash')

        @php($lines = $cart->lines())

        @if($lines->isEmpty())
            <div class="card">
                <div class="card-body text-muted">
                    Your cart is empty. <a href="{{ route('store.index') }}">Browse the store</a>.
                </div>
            </div>
        @else
            <div class="card mb-3">
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Price</th>
                                <th style="width: 9rem">Quantity</th>
                                <th class="text-end">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr>
                                    <td>
                                        <a href="{{ route('store.show', $line->product()->slug) }}">{{ $line->product()->name }}</a>
                                        <div class="text-muted small">{{ $line->label() }}</div>
                                    </td>
                                    <td class="text-end">${{ number_format($line->unitPrice(), 2) }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('store.cart.update') }}" class="d-flex gap-1">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="product_variant_id" value="{{ $line->variant->id }}">
                                            <input type="number" name="quantity" class="form-control form-control-sm"
                                                   min="0" max="100" value="{{ $line->quantity }}">
                                            <button class="btn btn-sm btn-outline-secondary">Update</button>
                                        </form>
                                    </td>
                                    <td class="text-end">${{ number_format($line->amount(), 2) }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('store.cart.remove', $line->variant->id) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ghost-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Subtotal</th>
                                <th class="text-end">${{ number_format($cart->subtotal(), 2) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Pickup wording is per run, and a cart can hold items from more
                 than one product, so each is named rather than assuming one. --}}
            @foreach($cart->products() as $product)
                @php($run = $product->openRun())
                @if($run && ($run->pickup_note || $run->expected_arrival_at))
                    <div class="alert alert-info">
                        <strong>{{ $product->name }}:</strong>
                        @if($run->expected_arrival_at)
                            expected around {{ $run->expected_arrival_at->format('F j, Y') }}.
                        @endif
                        {{ $run->pickup_note }}
                    </div>
                @endif
            @endforeach

            <div class="d-flex justify-content-end">
                <a href="{{ route('store.checkout') }}" class="btn btn-primary btn-lg">
                    Checkout — ${{ number_format($cart->subtotal(), 2) }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
