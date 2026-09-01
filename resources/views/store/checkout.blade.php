@extends('layouts.dashboard')

@section('page-title')
    Checkout
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('store.cart') }}" class="btn btn-link">Back to cart</a>
        </div>

        @include('store.partials.flash')

        @php($lines = $cart->lines())

        <div class="row">
            <div class="col-md-7">
                <form method="POST" action="{{ route('store.checkout.store') }}" id="checkoutForm">
                    @csrf
                    <input type="hidden" name="payment_method" class="payment-method">

                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Your details</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Name</label>
                                    <input type="text" name="name" class="form-control" required
                                           value="{{ old('name', $user ? trim($user->firstname.' '.$user->lastname) : '') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Email</label>
                                    <input type="email" name="email" class="form-control" required
                                           value="{{ old('email', $user?->email) }}">
                                    <small class="form-hint">Your order confirmation goes here.</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $user?->phone) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Pick up at</label>
                                    <select name="pickup_school_id" class="form-select" required>
                                        <option value="">Choose a school…</option>
                                        @foreach($schools as $school)
                                            <option value="{{ $school->id }}"
                                                @selected(old('pickup_school_id', $user?->school_id) == $school->id)>
                                                {{ $school->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-hint">Pickup only — there is no shipping.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($user)
                        <div class="card mb-3">
                            <div class="card-header"><h3 class="card-title mb-0">Payment</h3></div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Name on card</label>
                                    <input type="text" class="form-control card_holder_name"
                                           value="{{ trim($user->firstname.' '.$user->lastname) }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Card number</label>
                                    <div id="card-number" class="form-control"></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Expiry</label>
                                        <div id="card-expiry" class="form-control"></div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">CVC</label>
                                        <div id="card-cvc" class="form-control"></div>
                                    </div>
                                </div>
                                <div id="card-errors" class="text-danger small"></div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg pay">
                            Pay ${{ number_format($cart->subtotal(), 2) }}
                        </button>
                    @else
                        <div class="alert alert-info">
                            You'll enter your card on Stripe's secure page. Your order is recorded here first.
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">
                            Continue to payment — ${{ number_format($cart->subtotal(), 2) }}
                        </button>
                        <p class="form-hint mt-2">
                            Already have an account? <a href="{{ route('login') }}">Sign in</a> to pay without leaving this page.
                        </p>
                    @endif
                </form>
            </div>

            <div class="col-md-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title mb-0">Your order</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-sm mb-0">
                            <tbody>
                                @foreach($lines as $line)
                                    <tr>
                                        <td>
                                            {{ $line->product()->name }}
                                            <div class="text-muted small">{{ $line->label() }} &times; {{ $line->quantity }}</div>
                                        </td>
                                        <td class="text-end">${{ number_format($line->amount(), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end">${{ number_format($cart->subtotal(), 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @foreach($cart->products() as $product)
                    @php($run = $product->openRun())
                    @if($run && ($run->pickup_note || $run->expected_arrival_at))
                        <div class="alert alert-info mt-3">
                            <strong>{{ $product->name }}:</strong>
                            @if($run->expected_arrival_at)
                                expected around {{ $run->expected_arrival_at->format('F j, Y') }}.
                            @endif
                            {{ $run->pickup_note }}
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@if($user)
@push('js')
<script src="https://js.stripe.com/v3/"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Publishable key for THIS cart's Stripe account — it has to match the
        // account the SetupIntent and PaymentIntent are created on.
        const stripe = Stripe(@json($stripeKey));
        const elements = stripe.elements();

        const cardNumber = elements.create('cardNumber');
        const cardExpiry = elements.create('cardExpiry');
        const cardCvc = elements.create('cardCvc');
        cardNumber.mount('#card-number');
        cardExpiry.mount('#card-expiry');
        cardCvc.mount('#card-cvc');

        const form = document.getElementById('checkoutForm');
        const button = form.querySelector('button.pay');
        const errors = document.getElementById('card-errors');
        const finalizeUrl = @json(route('store.checkout.finalize'));
        const csrfToken = @json(csrf_token());

        // Tokenize once and reuse, so a decline doesn't make the buyer retype
        // their card.
        let paymentMethod = null;

        const fail = (msg) => {
            errors.textContent = msg || 'Payment could not be completed.';
            button.removeAttribute('disabled');
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            errors.textContent = '';
            button.setAttribute('disabled', 'disabled');

            const ensure = paymentMethod
                ? Promise.resolve(paymentMethod)
                : stripe.confirmCardSetup(@json($intent?->client_secret), {
                    payment_method: {
                        card: cardNumber,
                        billing_details: { name: form.querySelector('.card_holder_name').value },
                    },
                  }).then(function (result) {
                      if (result.error) { throw result.error; }
                      paymentMethod = result.setupIntent.payment_method;
                      return paymentMethod;
                  });

            ensure.then(function (pm) {
                form.querySelector('.payment-method').value = pm;

                return fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                }).then(function (r) { return r.json(); });
            }).then(function (data) {
                if (data.status === 'succeeded') {
                    window.location = data.redirect;
                    return;
                }

                if (data.status === 'requires_action') {
                    // 3-D Secure in the browser, then finalize server-side. The
                    // server re-fetches the intent; it never trusts this result.
                    return stripe.confirmCardPayment(data.client_secret).then(function (result) {
                        if (result.error) { throw result.error; }

                        return fetch(finalizeUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ payment_intent_id: result.paymentIntent.id }),
                        }).then(function (r) { return r.json(); }).then(function (done) {
                            if (done.status === 'succeeded') { window.location = done.redirect; }
                            else { throw new Error(done.message || 'Could not complete your order.'); }
                        });
                    });
                }

                throw new Error(data.message || 'Payment failed.');
            }).catch(function (err) {
                fail(err && err.message);
            });
        });
    });
</script>
@endpush
@endif
