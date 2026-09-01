{{-- A non-default account is worth noticing at a glance, same as the events index. --}}
@if($product->stripe_account === config('services.stripe.default_account', 'association'))
    <span class="text-muted">{{ $product->stripeAccountLabel() }}</span>
@else
    <span class="badge bg-yellow-lt">{{ $product->stripeAccountLabel() }}</span>
@endif
