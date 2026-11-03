@if($order->isFullyRefunded())
    <span class="badge bg-red-lt">Refunded</span>
@elseif($order->isPaid())
    <span class="badge bg-green-lt">Paid</span>
    @if((float) $order->refunded_amount > 0)
        <span class="badge bg-red-lt ms-1">Part refunded</span>
    @endif
@elseif($order->isPending())
    <span class="badge bg-yellow-lt">Pending</span>
@elseif($order->status === \App\Models\ProductOrder::STATUS_FAILED)
    <span class="badge bg-secondary-lt">Failed</span>
@else
    <span class="badge bg-secondary-lt">{{ ucfirst($order->status) }}</span>
@endif
