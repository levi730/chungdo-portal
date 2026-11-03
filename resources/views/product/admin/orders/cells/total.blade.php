<span class="text-nowrap">${{ number_format((float) $order->total, 2) }}</span>
@if((float) $order->refunded_amount > 0)
    <div class="text-nowrap small text-danger" title="Refunded">&minus;${{ number_format((float) $order->refunded_amount, 2) }}</div>
@endif
