@php($label = \App\Models\Product::STATUSES[$product->status] ?? $product->status)

@if($product->status === \App\Models\Product::STATUS_ACTIVE)
    <span class="badge bg-green-lt">{{ $label }}</span>
@elseif($product->status === \App\Models\Product::STATUS_DRAFT)
    <span class="badge bg-secondary-lt">{{ $label }}</span>
@else
    <span class="text-muted">{{ $label }}</span>
@endif
