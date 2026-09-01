<p>Hi {{ $order->name }},</p>

<p>Thanks — we've got your order.</p>

<p><strong>Order number:</strong> {{ $order->reference }}</p>

<table cellpadding="6" cellspacing="0" border="0">
    <thead>
        <tr>
            <th align="left">Item</th>
            <th align="right">Qty</th>
            <th align="right">Price</th>
            <th align="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product_name }} — {{ $item->variant_name }}</td>
                <td align="right">{{ $item->quantity }}</td>
                <td align="right">${{ number_format($item->unit_price, 2) }}</td>
                <td align="right">${{ number_format($item->amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" align="right">Subtotal</td>
            <td align="right">${{ number_format($order->subtotal, 2) }}</td>
        </tr>
        @if($order->tax > 0)
            <tr>
                <td colspan="3" align="right">Tax</td>
                <td align="right">${{ number_format($order->tax, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="3" align="right"><strong>Total</strong></td>
            <td align="right"><strong>${{ number_format($order->total, 2) }}</strong></td>
        </tr>
    </tfoot>
</table>

{{-- Arrival and pickup wording live on the run, and an order can span runs with
     different arrival dates, so each is named rather than assuming one. --}}
@php($runs = $order->items->map->run->filter()->unique('id'))
@foreach($runs as $run)
    @if($run->expected_arrival_at || $run->pickup_note)
        <p>
            @if($run->expected_arrival_at)
                Expected to arrive around <strong>{{ $run->expected_arrival_at->format('F j, Y') }}</strong>.
            @endif
            {{ $run->pickup_note }}
        </p>
    @endif
@endforeach

<p>We'll be in touch when it's ready to collect.</p>

<p>— Chung Do Association</p>
