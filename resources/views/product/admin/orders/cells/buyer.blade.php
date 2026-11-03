<div>{{ $order->name }}</div>
<div class="text-muted small">{{ $order->email }}@if($order->isGuest()) <span class="badge bg-secondary-lt ms-1" title="Guest checkout, no portal account">guest</span>@endif</div>
