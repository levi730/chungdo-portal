@foreach($order->items as $line)
    <div class="text-nowrap">{{ $line->quantity }} &times; {{ $line->product_name }}@if($line->variant_name) <span class="text-muted">— {{ $line->variant_name }}</span>@endif</div>
@endforeach
