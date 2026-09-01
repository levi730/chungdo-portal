@extends('layouts.dashboard')

@section('page-title')
    Your order
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        @if($order->isPaid())
            <div class="alert alert-success">
                <h3 class="mb-1">Thanks — your order is in.</h3>
                <div>Order number <strong>{{ $order->reference }}</strong>. A confirmation is on its way to {{ $order->email }}.</div>
            </div>
        @elseif($order->isPending())
            {{-- The synchronous path didn't finish it. The webhook and the
                 15-minute sweep both still will, so don't alarm the buyer. --}}
            <div class="alert alert-warning">
                <h3 class="mb-1">We're confirming your payment.</h3>
                <div>
                    Order number <strong>{{ $order->reference }}</strong>. This usually takes a moment —
                    you'll get an email as soon as it's confirmed. No need to pay again.
                </div>
            </div>
        @else
            <div class="alert alert-danger">
                <h3 class="mb-1">That payment didn't go through.</h3>
                <div>Order number <strong>{{ $order->reference }}</strong>. Nothing was charged.</div>
            </div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    {{ $item->product_name }}
                                    <div class="text-muted small">{{ $item->variant_name }}</div>
                                </td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end">${{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">${{ number_format($item->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if($order->tax > 0)
                            <tr>
                                <th colspan="3" class="text-end">Tax</th>
                                <th class="text-end">${{ number_format($order->tax, 2) }}</th>
                            </tr>
                        @endif
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end">${{ number_format($order->total, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Arrival and pickup are per run, and an order can span runs. --}}
        @foreach($order->items->map->run->filter()->unique('id') as $run)
            @if($run->expected_arrival_at || $run->pickup_note)
                <div class="alert alert-info mt-3">
                    @if($run->expected_arrival_at)
                        Expected around <strong>{{ $run->expected_arrival_at->format('F j, Y') }}</strong>.
                    @endif
                    {{ $run->pickup_note }}
                </div>
            @endif
        @endforeach

        <div class="mt-3">
            <a href="{{ route('store.index') }}" class="btn btn-outline-primary">Back to the store</a>
        </div>
    </div>
</div>
@endsection
