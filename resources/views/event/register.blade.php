@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="content">

        <div class="row">
            <div class="text-center text-primary display-6 mb-4">{{ $event->name }}</div>

            {{--<div class="float-end">
                @include('partials.event.admin')
            </div>--}}
        </div>

        @if (session('success'))
            <div class="alert alert-success">{!! session('success')  !!}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @include('partials.event.image-slider')



        <div class="row">
            <div class="col-sm-6 order-2 order-sm-1 markdown-content">
                @md($event->details)

                {{--
                idk how this was supposed to work.
                @if(count($already_reg) > 0)
                <div>
                    <a href="/event/{{ $event->slug }}/register/complete" class="btn btn-primary">Check Registration Status</a>
                </div>
                @endif
                --}}

            </div>

            <div class="col-sm-6 bg-muted-lt order-1 order-sm-2 mb-3">
                <div class="mb-2 text-primary" style="font-size: 1.3rem;">
                    @if(!$event->enddatetime)

                        {{ $event->startdatetime->format('l, F j, Y') }}<br>
                        {{ $event->startdatetime->format('g:i a') }}

                    @elseif($event->startdatetime->format('Y-m-d') == $event->enddatetime->format('Y-m-d'))
                        {{ $event->startdatetime->format('l, F j, Y') }}<br>
                        {{ $event->startdatetime->format('g:i a') }} - {{ $event->enddatetime->format('g:i a') }}
                    @else
                        {{ $event->startdatetime->format('l, F j, Y @ g:i a') }} - {{ $event->enddatetime->format('l, F j, Y @ g:i a') }}
                    @endif
                </div>

                <div class="mb-2">
                    @if($event->location)
                        <div style="font-size: 1.1rem;">{!! nl2br(e($event->location)) !!}</div>
                    @endif
                </div>

                <div class="mb-2">
                    <div style="font-size: 1.1rem;">
                        @if($regFee['cost'] == 0)
                            <span class="text-red">FREE!</span>
                        @else
                            ${{ number_format($regFee['cost'], 2, '.', ',')}} {{ $regFee['cost_type'] }}
                        @endif
                    </div>
                </div>

                @if($event->map_url)
                <div class="mb-2">
                    <iframe src="{{ $event->map_url }}" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                @endif

                @if(count($event->getMedia('forms')) > 0)
                <h3 class="text-primary mt-3 mb-0">Forms</h3>
                <div class="small text-muted">Can't register online?  Downloadable forms must be filled out and given to your instructor along with payment.</div>
                <div class="mt-2">
                    <ul>
                    @foreach($event->getMedia('forms') as $form)
                        <li>
                            <a href="{{ $form->getUrl() }}">{{$form->name}}</a>
                        </li>
                    @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>

        <div class="hr-text text-primary my-5">Event Registration</div>

        @if($event->startdatetime->isPast())
        <div class="alert alert-important alert-danger" role="alert">
            <div class="d-flex">
                <div>
                    <!-- Download SVG icon from http://tabler-icons.io/i/alert-triangle -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v2m0 4v.01" /><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75" /></svg>
                </div>
                <div>
                    Event Registration is closed for {{ $event->name }}.
                </div>
            </div>
        </div>

        @else


            <div class="alert alert-important alert-warning alert-dismissible" role="alert">
                <div class="d-flex">
                    <div>
                        <!-- Download SVG icon from http://tabler-icons.io/i/alert-triangle -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v2m0 4v.01" /><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75" /></svg>
                    </div>
                    <div>
                        Now would be a good time to review your <a href="/profile">Account Information</a> and make sure it's up to date. We will be sending out further email communication as the plans get finalized.
                    </div>
                </div>
                <a class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="close"></a>
            </div>


            <div x-data="paymentData" x-on:stripe-elements-upd.window="updFromStripeElements($event)" @cancel-loading="loading = false">

                {{-- Post-registration add-on charge (shown when an edit needs payment) --}}
                <div id="adjust-payment-card" class="card mb-3 border-primary" x-show="adjustPayUid !== null" x-cloak>
                    <div class="card-header"><h3 class="card-title mb-0 text-primary">Add-on payment</h3></div>
                    <div class="card-body">
                        <p>Your add-on changes total <b>$<span x-text="Number(adjustPayAmount).toFixed(2)"></span></b>. Enter a card to complete them.</p>
                        <fieldset class="form-fieldset" style="max-width: 30rem;">
                            <div class="mb-2"><label class="form-label">Cardholder Name</label><input type="text" id="adj-cardholder" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Card Number</label><div id="adj-card-number" class="form-control"></div></div>
                            <div class="row">
                                <div class="col mb-2"><label class="form-label">Expiry</label><div id="adj-card-expiry" class="form-control"></div></div>
                                <div class="col mb-2"><label class="form-label">CVC</label><div id="adj-card-cvc" class="form-control"></div></div>
                            </div>
                        </fieldset>
                        <div class="text-red small my-2" x-show="adjustError" x-text="adjustError"></div>
                        <button type="button" class="btn btn-primary" onclick="submitAdjustPayment()" :disabled="adjustSaving">
                            Pay $<span x-text="Number(adjustPayAmount).toFixed(2)"></span> &amp; update
                            <span x-show="adjustSaving" class="spinner-border spinner-border-sm ms-1"></span>
                        </button>
                        <button type="button" class="btn btn-link" @click="closeAdjustPayment()">Cancel</button>
                    </div>
                </div>

                {{-- Section 1: Participants (each with their own add-ons) --}}
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">1 &middot; Who's registering</h3></div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Turn on the switch next to each student you'd like to register, then choose their add-ons.</p>
                        @if(auth()->user())
                            @include('event.register-person', ['user' => auth()->user()])
                        @endif
                        @foreach(auth()->user()->dependents as $fam)
                            @include('event.register-person', ['user' => $fam])
                        @endforeach
                    </div>
                </div>

                {{-- Section 2: Whole-group add-ons (potluck, donation) --}}
                @if($event->hasAddon('potluck') || $event->hasAddon('donation'))
                    <div class="card mb-3" x-show="registered.length > 0" x-cloak>
                        <div class="card-header"><h3 class="card-title mb-0">2 &middot; For the whole group</h3></div>
                        <div class="card-body">
                            <div class="addon-grid">
                                @if($event->hasAddon('potluck'))
                                    <div class="addon-cell" style="flex: 2 1 18rem;">
                                        <div class="addon-label">Potluck Item</div>
                                        <input class="form-control form-control-sm" name="potluck_open_item" x-model="potluck_open_item" placeholder="Your dish…">
                                        <details class="mt-1">
                                            <summary class="text-muted small" style="cursor: pointer;">See what others are bringing ({{ count($registrations) }})</summary>
                                            <table class="table table-sm mt-1">
                                                <tbody>
                                                    @foreach($registrations as $reg)
                                                        <tr><td>{{ $reg->full_name }}</td><td>{{ $reg->open_item }}</td></tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </details>
                                    </div>
                                @endif
                                @if($event->hasAddon('donation'))
                                    <div class="addon-cell">
                                        <div class="addon-label">Donation</div>
                                        <div class="input-group input-group-flat input-group-sm" style="max-width: 9rem;">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" x-model="donation_amount" autocomplete="off" />
                                        </div>
                                        <div class="small text-muted mt-1">Optional — helps offset costs.</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Section 3: Order summary --}}
                <div class="card mb-3" x-show="registered.length > 0" x-cloak>
                    <div class="card-header"><h3 class="card-title mb-0">3 &middot; Order summary</h3></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0" style="max-width: 28rem;">
                            <tbody>
                                <tr>
                                    <td>Registration (<span x-text="registered.length"></span>)</td>
                                    <td class="text-end">$<span x-text="fees().toFixed(2)"></span></td>
                                </tr>
                                @if($mealPrice)
                                    <tr x-show="totalMeals() > 0">
                                        <td>{{ $event->addon('meal_ticket')?->setting('label', 'Meal') }} <span class="text-muted">&times; <span x-text="totalMeals()"></span></span></td>
                                        <td class="text-end">$<span x-text="mealsCost().toFixed(2)"></span></td>
                                    </tr>
                                @endif
                                @if($event->hasAddon('guests'))
                                    <tr x-show="totalGuests() > 0">
                                        <td>Guests</td>
                                        <td class="text-end text-muted"><span x-text="totalGuests()"></span></td>
                                    </tr>
                                @endif
                                @if($event->hasAddon('donation'))
                                    <tr x-show="(parseFloat(donation_amount) || 0) > 0">
                                        <td>Donation</td>
                                        <td class="text-end">$<span x-text="(parseFloat(donation_amount) || 0).toFixed(2)"></span></td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold" style="font-size: 1.15rem;">
                                    <td>Total</td>
                                    <td class="text-end">$<span x-text="calcCost().toFixed(2)"></span></td>
                                </tr>
                            </tfoot>
                        </table>

                        @if(count($already_reg) > 0 && $event->require_ticket)
                            @php($appleWallet = \App\Services\Wallet\AppleWalletPass::isConfigured())
                            @php($googleWallet = \App\Services\Wallet\GoogleWalletPass::isConfigured())
                            <div class="mt-3">
                                <a id="addToWallet"></a>
                                <p class="mb-1">{{ count($already_reg) }} completed registration(s).</p>
                                @if($appleWallet || $googleWallet)
                                    <p class="mb-2 small text-muted">Add your check-in QR code to your phone's wallet:</p>
                                    @if($appleWallet)
                                        <a href="{{ route('event.pass.apple', $event->slug) }}" class="d-inline-block me-2 align-middle">
                                            <img src="/img/Apple_Wallet.svg" alt="Add to Apple Wallet" style="height:48px">
                                        </a>
                                    @endif
                                    @if($googleWallet)
                                        <a href="{{ route('event.pass.google', $event->slug) }}" class="d-inline-block align-middle">
                                            <img src="/img/Google_Wallet.svg" alt="Save to Google Wallet" style="height:48px">
                                        </a>
                                    @endif
                                    <p class="text-muted small mt-1 mb-0">(Come back anytime before the event to add completed registrations to your wallet.)</p>
                                @else
                                    <p class="text-muted small mb-0">Your check-in QR code is in your registration confirmation email.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Section 4: Payment --}}
                <div class="card mb-3" x-show="registered.length > 0" x-cloak>
                    <div class="card-header"><h3 class="card-title mb-0">4 &middot; Payment</h3></div>
                    <div class="card-body">
                        <div id="stripe_payment">
                            <form method="POST" x-show="calcCost() == 0 && registered.length > 0" action="{{ route('event.register-process', [$event->slug]) }}" class="mt-3 mb-3" name="freeForm" id="freeForm" x-transition>
                                @csrf
                                <input type="hidden" name="registered" x-model="registered">
                                <input type="hidden" name="potluck_open_item" x-model="potluck_open_item">
                                <input type="hidden" name="volunteer_selections" x-model="JSON.stringify(volunteer_selections)">
                                @if($event->hasOpenAddon('tshirt'))
                                    <input type="hidden" name="tshirts" x-model="JSON.stringify(tshirts)">
                                @endif
                                @if($event->hasOpenAddon('participation'))
                                    <input type="hidden" name="participation" x-model="JSON.stringify(participation)">
                                @endif
                                <input type="hidden" name="ranks" x-model="JSON.stringify(ranks)">
                                @if($event->hasOpenAddon('guests'))
                                    <input type="hidden" name="guests" x-model="JSON.stringify(guests)">
                                @endif
                                @if($event->hasOpenAddon('meal_ticket'))
                                    <input type="hidden" name="meals" x-model="JSON.stringify(meals)">
                                @endif
                                <div id="reg-errors" class="text-red mb-2"></div>

                                <button type="submit" name="freesubmit" class="btn btn-primary" x-on:click="loading = true;">
                                    Register <div x-show="loading" class="spinner-border ms-2"></div>
                                </button>
                            </form>

                            <form method="POST" x-show="calcCost() > 0 && registered.length > 0" action="{{ route('event.register-process', [$event->slug]) }}" class="card-form mt-3 mb-3" name="payForm" id="payForm" x-transition>
                                @csrf
                                <input type="hidden" name="payment_method" class="payment-method">
                                <input type="hidden" name="donation_amount" x-model="donation_amount">
                                <input type="hidden" name="registered" x-model="registered" id="registered">
                                <input type="hidden" name="potluck_open_item" x-model="potluck_open_item">
                                <input type="hidden" name="volunteer_selections" x-model="JSON.stringify(volunteer_selections)">
                                @if($event->hasOpenAddon('tshirt'))
                                    <input type="hidden" name="tshirts" x-model="JSON.stringify(tshirts)" id="tshirts">
                                @endif
                                @if($event->hasOpenAddon('participation'))
                                    <input type="hidden" name="participation" x-model="JSON.stringify(participation)" id="participation">
                                @endif
                                <input type="hidden" name="ranks" x-model="JSON.stringify(ranks)" id="ranks">
                                @if($event->hasOpenAddon('guests'))
                                    <input type="hidden" name="guests" x-model="JSON.stringify(guests)" id="guests">
                                @endif
                                @if($event->hasOpenAddon('meal_ticket'))
                                    <input type="hidden" name="meals" x-model="JSON.stringify(meals)" id="meals">
                                @endif


                                <fieldset class="form-fieldset" >
                                    <div class="mb-3">
                                        <label class="form-label">Cardholder Name</label>
                                        <input type="text" class="form-control" />
                                        <div class="text-red"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Card Number</label>
                                        <div id="card-number" class="form-control"></div>
                                        <div id="card-number-errors" class="text-red"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Card Expiry</label>
                                        <div id="card-expiry" class="form-control"></div>
                                        <div id="card-expiry-errors" class="text-red"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">CVC</label>
                                        <div id="card-cvc" class="form-control"></div>
                                        <div id="card-cvc-errors" class="text-red"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">ZIP Code</label>
                                        <input type="text" class="form-control" />
                                        <div class="text-red"></div>
                                    </div>
                                </fieldset>

                                <div class="form-group mt-3">
                                    <div id="card-errors" class="text-red mb-2"></div>

                                    <button type="submit" x-on:click="loading = true;" name="paysubmit" class="btn btn-primary pay">
                                        <span x-text="submitText()"></span>
                                        <span x-show="calcCost() > 0" class="ms-0">: $<span class="ms-0 ps-0" x-text="calcCost().toFixed(2)"></span></span>
                                        <div x-show="loading" class="spinner-border ms-2"></div>

                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        @endif


    </div>
</div>
@endsection


@push('css')
    <style>
        [x-cloak] { display: none !important; }

        /* Responsive add-on cells: horizontal columns with vertical dividers on
           desktop, stacked with horizontal dividers on mobile. */
        .addon-grid { display: flex; flex-wrap: wrap; }
        .addon-cell { flex: 1 1 11rem; min-width: 10rem; padding: 0.6rem 0.85rem; }
        .addon-cell + .addon-cell { border-top: 1px solid var(--tblr-border-color, #e6e7e9); }
        @media (min-width: 768px) {
            .addon-cell + .addon-cell { border-top: none; border-left: 1px solid var(--tblr-border-color, #e6e7e9); }
        }
        .addon-label {
            font-size: 0.7rem; font-weight: 600; letter-spacing: 0.02em;
            text-transform: uppercase; color: var(--tblr-secondary, #6c757d); margin-bottom: 0.35rem;
        }
        /* Count inputs use custom - / + steppers, so hide the native spinner. */
        .qty-input { -moz-appearance: textfield; appearance: textfield; }
        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

        tr.itemLimit td {
            color: gray;
            text-decoration: line-through;
        }
        .markdown-content :is(h1, h2, h3, h4, h5, h6) a {
            color: var(--tblr-primary, #0d6efd);
            text-decoration: underline;
        }
    </style>
@endpush

@push('js')
    <script src="https://code.jquery.com/jquery-3.6.0.slim.min.js" integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI=" crossorigin="anonymous"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script>

        document.addEventListener('alpine:init', () => {
            Alpine.data('paymentData', () => ({

                registered: [],
                donation_amount: 0,
                buttonDisabled: false,
                potluck_open_item: '',
                volunteer_selections: [],
                loading: false,
                event: {!! $event->toJson() !!},
                tshirts: {!! $tshirts->toJson() !!},
                participation: {!! $participation->toJson() !!},
                ranks: {!! $ranks->toJson() !!},
                guests: {!! $guests->toJson() !!},
                meals: {!! $meals->toJson() !!},
                // --- Post-registration edit state ---
                editing: {},
                adjustSaving: false,
                adjustError: '',
                adjustCurrent: {!! ($adjustCurrent ?? collect())->toJson() !!},
                adjustPayUid: null,
                adjustPayAmount: 0,
                adjustUrl: "{{ route('event.register-adjust', $event->slug) }}",
                adjustFinalizeUrl: "{{ route('event.register-adjust-finalize', $event->slug) }}",
                csrf: "{{ csrf_token() }}",
                _editSnapshot: {},
                startEdit(uid) {
                    this._editSnapshot[uid] = {
                        meal: Object.assign({attending: false, additional: 0}, this.meals[uid] || {}),
                        tshirt: this.tshirts[uid],
                        participation: this.participation[uid],
                        guest: this.guests[uid],
                    };
                    this.adjustError = '';
                    this.editing[uid] = true;
                },
                cancelEdit(uid) {
                    let s = this._editSnapshot[uid];
                    if(s) { this.meals[uid] = Object.assign({}, s.meal); this.tshirts[uid] = s.tshirt; this.participation[uid] = s.participation; this.guests[uid] = s.guest; }
                    this.editing[uid] = false;
                },
                userMealCost(uid) {
                    if(!this.mealPrice) { return 0; }
                    let m = this.meals[uid];
                    if(!m || !m.attending) { return 0; }
                    return (1 + (parseInt(m.additional, 10) || 0)) * this.mealPrice;
                },
                adjustDelta(uid) {
                    return this.userMealCost(uid) - (parseFloat(this.adjustCurrent[uid]) || 0);
                },
                saveEdit(uid) {
                    this.adjustSaving = true;
                    this.adjustError = '';
                    let fd = new FormData();
                    fd.append('_token', this.csrf);
                    fd.append('user_id', uid);
                    fd.append('meals', JSON.stringify(this.meals));
                    fd.append('tshirts', JSON.stringify(this.tshirts));
                    fd.append('participation', JSON.stringify(this.participation));
                    fd.append('guests', JSON.stringify(this.guests));
                    fetch(this.adjustUrl, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                        .then(r => r.json())
                        .then(data => {
                            this.adjustSaving = false;
                            if(data.status === 'applied' || data.status === 'refund_requested') { window.location = data.redirect; return; }
                            if(data.status === 'payment_required') { this.openAdjustPayment(uid, data.amount); return; }
                            this.adjustError = data.message || 'Could not save your changes.';
                        })
                        .catch(e => { this.adjustSaving = false; this.adjustError = e.message || 'Could not save your changes.'; });
                },
                openAdjustPayment(uid, amount) {
                    this.adjustPayUid = uid;
                    this.adjustPayAmount = amount;
                    this.$nextTick(() => { window.dispatchEvent(new CustomEvent('adjust-pay-open')); });
                },
                closeAdjustPayment() {
                    this.adjustPayUid = null;
                    this.adjustError = '';
                    this.adjustSaving = false;
                },
                mealPrice: {{ $mealPrice }},
                regFee: {!! json_encode($regFee) !!},
                fees() {
                    let ct = this.registered.length;
                    let total = 0;
                    let reg_number, disc;
                    for(let i=0; i<ct; i++) {
                        reg_number = i+1; //fix zero indexing
                        if(reg_number > 1) {
                            disc = parseFloat(this.regFee.discounts[Math.min(reg_number, 5)]) || 0;
                        } else {
                            disc = 0;
                        }

                        total += Math.max(0, parseFloat(this.regFee.cost) - disc);
                    }
                    return total
                },
                selectedForReg(userid) {
                    if(this.registered.includes(userid.toString())) {
                        return true;
                    } else {
                        return false;
                    }
                },
                mealsCost() {
                    if(!this.mealPrice) { return 0; }
                    let total = 0;
                    for(const uid of this.registered) {
                        let m = this.meals[uid];
                        if(!m || !m.attending) { continue; }
                        let count = 1 + (parseInt(m.additional, 10) || 0);
                        total += count * this.mealPrice;
                    }
                    return total;
                },
                totalMeals() {
                    let total = 0;
                    for(const uid of this.registered) {
                        let m = this.meals[uid];
                        if(!m || !m.attending) { continue; }
                        total += 1 + (parseInt(m.additional, 10) || 0);
                    }
                    return total;
                },
                totalGuests() {
                    let total = 0;
                    for(const uid of this.registered) {
                        total += parseInt(this.guests[uid], 10) || 0;
                    }
                    return total;
                },
                calcCost() {
                    fees = parseFloat(this.fees());
                    if(isNaN(fees)) { fees = 0; }
                    donation = parseFloat(this.donation_amount);
                    if(isNaN(donation)) { donation = 0; }
                    return fees + donation + this.mealsCost();
                },
                submitText() {
                    if(this.fees() > 0) {
                        return "Purchase";
                    } else {
                        return "Register & Donate";
                    }
                },
                showCCForm() {
                    return this.calcCost() > 0;
                }


            }))
        })

        document.addEventListener("DOMContentLoaded", function() {

            let stripe = Stripe("{{ config('app.stripe_key') }}")
            let elements = stripe.elements()

            const cardNumberElement = elements.create('cardNumber');
            const cardExpiryElement = elements.create('cardExpiry');
            const cardCvcElement = elements.create('cardCvc');

            cardNumberElement.mount('#card-number');
            cardExpiryElement.mount('#card-expiry');
            cardCvcElement.mount('#card-cvc');

            let paymentMethod = null

            // --- Post-registration edit: its own card elements (mounted on first open) ---
            const adjElements = stripe.elements();
            const adjCardNumber = adjElements.create('cardNumber');
            const adjCardExpiry = adjElements.create('cardExpiry');
            const adjCardCvc = adjElements.create('cardCvc');
            let adjMounted = false;

            window.addEventListener('adjust-pay-open', function () {
                if (!adjMounted) {
                    adjCardNumber.mount('#adj-card-number');
                    adjCardExpiry.mount('#adj-card-expiry');
                    adjCardCvc.mount('#adj-card-cvc');
                    adjMounted = true;
                }
                document.getElementById('adjust-payment-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });

            window.submitAdjustPayment = function () {
                const data = Alpine.$data(document.querySelector('[x-data]'));
                data.adjustSaving = true;
                data.adjustError = '';
                stripe.createPaymentMethod({
                    type: 'card', card: adjCardNumber,
                    billing_details: { name: document.getElementById('adj-cardholder').value }
                }).then(function (res) {
                    if (res.error) { throw res.error; }
                    const fd = new FormData();
                    fd.append('_token', data.csrf);
                    fd.append('user_id', data.adjustPayUid);
                    fd.append('meals', JSON.stringify(data.meals));
                    fd.append('tshirts', JSON.stringify(data.tshirts));
                    fd.append('participation', JSON.stringify(data.participation));
                    fd.append('guests', JSON.stringify(data.guests));
                    fd.append('payment_method', res.paymentMethod.id);
                    return fetch(data.adjustUrl, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd }).then(r => r.json());
                }).then(function (resp) {
                    if (resp.status === 'charged') { window.location = resp.redirect; return; }
                    if (resp.status === 'requires_action') {
                        return stripe.confirmCardPayment(resp.client_secret).then(function (r) {
                            if (r.error) { throw r.error; }
                            const fd2 = new FormData();
                            fd2.append('_token', data.csrf);
                            fd2.append('payment_intent_id', r.paymentIntent.id);
                            return fetch(data.adjustFinalizeUrl, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd2 })
                                .then(x => x.json())
                                .then(function (fin) { if (fin.status === 'succeeded') { window.location = fin.redirect; } else { throw new Error(fin.message || 'Could not finalize.'); } });
                        });
                    }
                    throw new Error(resp.message || 'Payment failed.');
                }).catch(function (err) {
                    data.adjustSaving = false;
                    data.adjustError = err.message || 'Payment failed.';
                });
            };

            $('#freeForm').on('submit', function(e) {

                const alpineElement = document.querySelector('[x-data]');

                let sep = $("input#registered").val();
                if(sep == '') {
                    $('#reg-errors').text("You must select one or more registrants.");
                    alpineElement.dispatchEvent(new CustomEvent('cancel-loading'));
                    return false;
                }

                let registrants = sep.split(',');

                let ranks = JSON.parse($('#ranks').val());
                for (const registrant of registrants) {
                    if(!ranks[registrant]) {
                        $('#reg-errors').text("One or more registrants is missing belt/rank selection.");
                        alpineElement.dispatchEvent(new CustomEvent('cancel-loading'));
                        return false;
                    }
                }

                @if($event->minimum_rank_id)
                for (const registrant of registrants) {
                    if(parseInt(ranks[registrant], 10) < {{ $event->minimum_rank_id }}) {
                        $('#reg-errors').text("One or more registrants does not meet the minimum belt rank ({{ $event->minimumRank->rank }} or higher) required for this event.");
                        alpineElement.dispatchEvent(new CustomEvent('cancel-loading'));
                        return false;
                    }
                }
                @endif

                @if($event->hasOpenAddon('tshirt'))
                let tshirts = JSON.parse($('#tshirts').val());
                for (const registrant of registrants) {
                    if(!tshirts[registrant]) {
                        $('#reg-errors').text("One or more registrants is missing a T-shirt size selection.");
                        alpineElement.dispatchEvent(new CustomEvent('cancel-loading'));
                        return false;
                    }
                }
                @endif

            });


            $('.card-form').on('submit', function (e) {
                e.preventDefault();

                const alpineElement = document.querySelector('[x-data]');
                const failValidation = (msg) => {
                    $('#card-errors').text(msg);
                    $('button.pay').removeAttr('disabled');
                    alpineElement.dispatchEvent(new CustomEvent('cancel-loading'));
                };

                let sep = $("input#registered").val();
                if(sep == '') { failValidation("You must select one or more registrants."); return false; }
                let registrants = sep.split(',');

                let ranks = JSON.parse($('#ranks').val());
                for (const registrant of registrants) {
                    if(!ranks[registrant]) { failValidation("One or more registrants is missing a belt rank selection."); return false; }
                }

                @if($event->minimum_rank_id)
                for (const registrant of registrants) {
                    if(parseInt(ranks[registrant], 10) < {{ $event->minimum_rank_id }}) { failValidation("One or more registrants does not meet the minimum belt rank ({{ $event->minimumRank->rank }} or higher) required for this event."); return false; }
                }
                @endif

                @if($event->hasOpenAddon('tshirt'))
                let tshirts = JSON.parse($('#tshirts').val());
                for (const registrant of registrants) {
                    if(!tshirts[registrant]) { failValidation("One or more registrants is missing a T-shirt size selection."); return false; }
                }
                @endif

                $('#card-errors').text("");
                $('button.pay').attr('disabled', true);

                const form = document.getElementById('payForm');
                const finalizeUrl = "{{ route('event.register-finalize', $event->slug) }}";
                const csrfToken = "{{ csrf_token() }}";
                const showError = (msg) => {
                    $('#card-errors').text(msg || 'Payment could not be completed.');
                    $('button.pay').removeAttr('disabled');
                    alpineElement.dispatchEvent(new CustomEvent('cancel-loading'));
                };

                // 1) Tokenize the card once (reused on retry).
                const ensurePaymentMethod = paymentMethod
                    ? Promise.resolve(paymentMethod)
                    : stripe.confirmCardSetup("{{ $intent->client_secret }}", {
                        payment_method: { card: cardNumberElement, billing_details: { name: $('.card_holder_name').val() } }
                      }).then(function (result) {
                        if (result.error) { throw result.error; }
                        paymentMethod = result.setupIntent.payment_method;
                        return paymentMethod;
                      });

                ensurePaymentMethod.then(function (pm) {
                    $('.payment-method').val(pm);
                    // 2) Submit the order; the server creates + confirms the PaymentIntent.
                    return fetch(form.action, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: new FormData(form)
                    }).then(function (r) { return r.json(); });
                }).then(function (data) {
                    if (data.status === 'succeeded') {
                        window.location = data.redirect;
                        return;
                    }
                    if (data.status === 'requires_action') {
                        // 3) Run 3-D Secure in the browser, then finalize server-side.
                        return stripe.confirmCardPayment(data.client_secret).then(function (result) {
                            if (result.error) { throw result.error; }
                            return fetch(finalizeUrl, {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                body: JSON.stringify({ payment_intent_id: result.paymentIntent.id })
                            }).then(function (r) { return r.json(); }).then(function (fin) {
                                if (fin.status === 'succeeded') { window.location = fin.redirect; }
                                else { throw new Error(fin.message || 'Could not finalize registration.'); }
                            });
                        });
                    }
                    throw new Error(data.message || 'Payment failed.');
                }).catch(function (err) {
                    showError(err && err.message);
                });

                return false;
            });

            cardNumberElement.on('change', function(event) {
                var displayError = document.getElementById('card-number-errors');

                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }


            });

            cardExpiryElement.on('change', function(event) {
                var displayError = document.getElementById('card-expiry-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }

            });

            cardCvcElement.on('change', function(event) {
                var displayError = document.getElementById('card-cvc-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }

            });

        });
    </script>
@endpush
