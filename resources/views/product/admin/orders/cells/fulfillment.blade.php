@php($label = \App\Models\ProductOrder::FULFILLMENT_STATUSES[$order->fulfillment_status] ?? ucfirst($order->fulfillment_status))
@if(! $order->isPaid() || $order->isFullyRefunded())
    <span class="text-muted">—</span>
@elseif($order->isCollected())
    <span class="badge bg-green-lt">{{ $label }}</span>
@elseif($order->fulfillment_status === \App\Models\ProductOrder::FULFILLMENT_READY)
    <span class="badge bg-azure-lt">{{ $label }}</span>
@else
    <span class="text-muted">{{ $label }}</span>
@endif
