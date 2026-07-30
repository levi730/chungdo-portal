<?php

namespace App\Http\Controllers;

use App\Exports\Event\RegistrantExport;
use App\Mail\EventRegistered;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\Payment;
use App\Models\PendingEventRegistration;
use App\Models\PotluckOptions;
use App\Models\Rank;
use App\Models\School;
use App\Models\User;
use App\Services\RegistrationFulfiller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Wallet\AppleWalletPass;
use App\Services\Wallet\GoogleWalletPass;
use App\Services\Wallet\PassData;
use mikehaertl\pdftk\Pdf;
use QR_Code\QR_Code;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Stripe\Stripe;

class EventController extends Controller
{
    public function latest(Request $request)
    {
        $event = Event::orderBy('startdatetime', 'desc')->first();

        return redirect('/event/'.$event->slug.'/register');
    }

    public function manageAddons(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.manageAddons')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        // One row per registered handler: the stored EventAddon if it exists, or
        // a virtual (disabled) one seeded with the handler's default settings.
        $rows = [];
        $i = 0;
        foreach (\App\EventAddons\AddonRegistry::all() as $type => $handler) {
            $addon = $event->addon($type) ?? new EventAddon([
                'event_id' => $event->id,
                'type' => $type,
                'enabled' => false,
                'sort_order' => $i,
                'settings' => $handler->defaultSettings(),
            ]);
            $rows[] = ['handler' => $handler, 'addon' => $addon];
            $i++;
        }

        return view('event.addons.manage', compact('event', 'rows'));
    }

    public function saveAddons(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.manageAddons')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        (new \App\EventAddons\AddonConfigurator())->apply(
            $event,
            (array) $request->input('enabled', []),
            (array) $request->input('settings', []),
            (array) $request->input('closes_at', [])
        );

        $message = 'Add-ons updated.';
        if ($request->has('potluck_catalog_present')) {
            $blocked = (new \App\Services\PotluckCatalog())->sync($event->id, (array) $request->input('potluck_catalog', []));
            if (! empty($blocked)) {
                $message .= ' Kept potluck items still chosen by registrants: '.implode(', ', $blocked).'.';
            }
        }

        return back()->with('success', $message);
    }

    public function refundRequests(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.approveRefunds')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        // Only refund-lifecycle requests belong here — not the charge (top-up)
        // requests, which apply automatically once paid.
        $requests = \App\Models\AddonChangeRequest::where('event_id', $event->id)
            ->whereIn('status', [
                \App\Models\AddonChangeRequest::STATUS_PENDING,
                \App\Models\AddonChangeRequest::STATUS_APPROVED,
                \App\Models\AddonChangeRequest::STATUS_DENIED,
                \App\Models\AddonChangeRequest::STATUS_SUPERSEDED,
            ])
            ->with(['registration.user', 'registration.addonAnswers', 'requestedBy', 'event.addons'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'denied', 'superseded')")
            ->orderByDesc('id')
            ->get();

        // A human-readable "from -> to" summary per request for the view.
        $requests->each(fn ($req) => $req->setAttribute('summary_lines', $req->summaryLines()));

        return view('event.refund-requests', compact('event', 'requests'));
    }

    public function approveRefund(Request $request, $slug = null, $id = null)
    {
        if (! auth()->user()->can('event.approveRefunds')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();
        $changeRequest = \App\Models\AddonChangeRequest::where('event_id', $event->id)->findOrFail($id);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0|max:'.$changeRequest->refund_amount,
            'note' => 'nullable|string',
        ]);

        try {
            (new \App\Services\RefundApprover())->approve($changeRequest, (float) $data['amount'], auth()->user(), $data['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('error', 'Refund failed: '.$e->getMessage());
        }

        return back()->with('success', 'Refund approved and issued.');
    }

    public function denyRefund(Request $request, $slug = null, $id = null)
    {
        if (! auth()->user()->can('event.approveRefunds')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();
        $changeRequest = \App\Models\AddonChangeRequest::where('event_id', $event->id)->findOrFail($id);

        (new \App\Services\RefundApprover())->deny($changeRequest, auth()->user(), $request->input('note'));

        return back()->with('success', 'Request denied.');
    }

    public function registerForm(Request $request, $slug = null)
    {
        $event = Event::where('slug', '=', $slug)->firstOrFail();

        // Open-signup potluck items already brought (name + item), from add-on answers.
        $registrations = collect();
        if ($potluckAddon = $event->addon('potluck')) {
            $registrations = EventRegistrationAddon::where('event_addon_id', $potluckAddon->id)
                ->with('registration.user')
                ->get()
                ->map(fn ($a) => (object) ['full_name' => $a->registration?->user?->full_name, 'open_item' => $a->data['open_item'] ?? null])
                ->filter(fn ($r) => $r->full_name && $r->open_item)
                ->sortBy('open_item')
                ->values();
        }

        $intent = auth()->user()->createSetupIntent();
        $allRanks = Rank::orderBy('id')->get();

        // The user's most recent t-shirt size, from add-on answers.
        $lastTshirt = fn ($userId) => EventRegistrationAddon::where('type', 'tshirt')
            ->whereHas('registration', fn ($q) => $q->where('user_id', $userId))
            ->latest('id')->value('value');

        $ids = [auth()->user()->id];
        $tshirts = [auth()->user()->id => $lastTshirt(auth()->user()->id)];
        $ranks = [auth()->user()->id => null];
        $guests = [auth()->user()->id => 0];
        $meals = [auth()->user()->id => ['attending' => false, 'additional' => 0]];
        foreach (auth()->user()->dependents as $fam) {
            $ids[] = $fam->id;
            $tshirts[$fam->id] = $lastTshirt($fam->id);
            $ranks[$fam->id] = null;
            $guests[$fam->id] = 0;
            $meals[$fam->id] = ['attending' => false, 'additional' => 0];
        }

        $tshirts = collect($tshirts);
        $ranks = collect($ranks);
        $guests = collect($guests);
        $meals = collect($meals);
        // Competition participation defaults to Both; overridden below for anyone
        // already registered.
        $participation = collect($ids)->mapWithKeys(fn ($id) => [$id => \App\EventAddons\EventParticipationAddon::BOTH]);

        // Flat per-meal price for the client-side total; 0 when the add-on is off
        // or past its deadline.
        $mealAddon = $event->addon('meal_ticket');
        $mealPrice = ($mealAddon && $mealAddon->isOpen()) ? (float) $mealAddon->setting('price', 0) : 0;

        // Registration-fee settings drive both the cost display and the client
        // total. Source of truth is the registration_fee add-on.
        $regFeeAddon = $event->addon('registration_fee');
        $regFeeOn = $regFeeAddon && $regFeeAddon->enabled;
        $discounts = $regFeeOn ? (array) $regFeeAddon->setting('discounts', []) : [];
        $regFee = [
            'cost' => $regFeeOn ? (float) $regFeeAddon->setting('cost', 0) : 0.0,
            'cost_type' => $regFeeOn ? $regFeeAddon->setting('cost_type', 'per person') : 'per person',
            'discounts' => [
                2 => (float) ($discounts['2'] ?? 0),
                3 => (float) ($discounts['3'] ?? 0),
                4 => (float) ($discounts['4'] ?? 0),
                5 => (float) ($discounts['5'] ?? 0),
            ],
        ];

        $already_reg = $event->registrations()->whereIn('user_id', $ids)->pluck('user_id');

        // Stored registrations (with their add-on answers) for the household, so
        // already-registered students can show badges for what they signed up for.
        $registeredRegs = EventRegistration::where('event_id', $event->id)
            ->whereIn('user_id', $ids)
            ->with('addonAnswers.addon')
            ->get()
            ->keyBy('user_id');

        // Which add-ons a registrant can edit (open, per-student), and their
        // types, so the client-side delta only compares those (not the fixed
        // registration fee, which isn't editable).
        $editableAddons = $event->enabledAddons()->filter(fn ($a) => $a->isOpen() && $a->handler()
            && $a->handler()->scope() === \App\EventAddons\AddonHandler::SCOPE_PER_STUDENT
            && $a->handler()->registrantView());
        $editableTypes = $editableAddons->pluck('type')->all();
        $canEditAddons = $editableAddons->isNotEmpty();

        // Pre-fill the add-on controls with each registered student's CURRENT
        // answers (so the edit flow starts from what they already chose), and
        // record their current editable add-on total for the client-side delta.
        $adjustCurrent = [];
        foreach ($registeredRegs as $uid => $r) {
            foreach ($r->addonAnswers as $a) {
                if ($a->type === 'meal_ticket') {
                    $meals[$uid] = ['attending' => (bool) $a->selected, 'additional' => (int) $a->quantity];
                }
                if ($a->type === 'tshirt') {
                    $tshirts[$uid] = $a->value;
                }
                if ($a->type === 'guests') {
                    $guests[$uid] = (int) $a->quantity;
                }
                if ($a->type === 'participation') {
                    $participation[$uid] = $a->value;
                }
            }
            $adjustCurrent[$uid] = (float) $r->addonAnswers->whereIn('type', $editableTypes)->sum('amount');
        }
        $adjustCurrent = collect($adjustCurrent);

        // Total meals this household has purchased for the event — drives the
        // "Download meal ticket" button. Computed from the already-loaded
        // registrations (no extra query).
        $mealVoucherCount = $registeredRegs
            ->flatMap(fn ($r) => $r->addonAnswers)
            ->where('type', 'meal_ticket')
            ->reduce(fn ($c, $a) => $c + ($a->selected ? 1 : 0) + (int) $a->quantity, 0);

        return view('event.register', compact('event', 'intent', 'allRanks', 'tshirts', 'participation', 'ranks', 'guests', 'meals', 'mealPrice', 'regFee', 'already_reg', 'registrations', 'registeredRegs', 'adjustCurrent', 'canEditAddons', 'mealVoucherCount'));
    }

    public function registerProcess(Request $request, $slug = null)
    {

        $all_volunteer_selections = json_decode($request->input('volunteer_selections'), true);
        foreach ($all_volunteer_selections as $k => $v) {
            $all_volunteer_selections[$k] = json_decode($v, true);
        }

        $potluck_item_id = $request->input('potluck_item_id');
        $potluck_open_item = $request->input('potluck_open_item');


        $tshirts = $request->input('tshirts');
        if ($tshirts) {
            $tshirts = json_decode($tshirts, true);
        }

        $guests = $request->input('guests');
        if ($guests) {
            $guests = json_decode($guests, true);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        $ranks = $request->input('ranks');
        if ($ranks) {
            $ranks = json_decode($ranks, true);
        }
        foreach ($ranks as $user_id => $rank_id) {
            if($rank_id !== null) {
                $u = User::find($user_id);
                $u->rank_id = $rank_id;
                $u->save();
            }
        }

        $registered = $request->input('registered');
        if ($registered) {
            $registered = explode(',', $registered);
        }

        // Enforce the event's minimum belt rank requirement server-side so it
        // can't be bypassed by tampering with the disabled toggle on the form.
        if ($event->minimum_rank_id && $registered) {
            foreach ($registered as $userid) {
                $u = User::find($userid);
                if (! $event->userMeetsMinimumRank($u)) {
                    $minRank = Rank::find($event->minimum_rank_id);

                    return back()->with('error', $u->full_name.' does not meet the minimum rank requirement ('.$minRank->rank.' or higher) for this event.');
                }
            }
        }

        $user = $request->user();
        $paymentMethod = $request->input('payment_method');
        $donationAmount = $request->input('donation_amount') ?? 0;

        // Parse add-on answers (meal ticket, etc.) up front so their charges are
        // included in the payment total. Answers are persisted per-registration
        // after each pivot row is attached below.
        $registeredUsers = [];
        foreach ($registered as $userid) {
            $ru = User::find($userid);
            if ($ru) {
                $registeredUsers[] = $ru;
            }
        }
        $addonRegistrar = new \App\EventAddons\AddonRegistrar();
        $addonResult = $addonRegistrar->parse($request, $event, $registeredUsers);

        // Every charge — registration fee, meal, donation — flows through the
        // add-on total.
        $price = $addonResult['total'];

        // Build the fulfillment payload and persist it as a pending registration
        // BEFORE charging, so the outcome can be completed exactly once from
        // either the synchronous response or the payment_intent.succeeded webhook.
        $usersPayload = [];
        foreach ($registeredUsers as $u) {
            $usersPayload[] = [
                'user_id' => $u->id,
                'addons' => array_map(fn ($item) => [
                    'event_addon_id' => $item['addon']->id,
                    'type' => $item['addon']->type,
                    'attrs' => $item['attrs'],
                ], $addonResult['perUser'][$u->id] ?? []),
            ];
        }

        // Per-person base fee (recorded as amount_due); add-on answers carry the rest.
        $baseFee = (float) ($event->addon('registration_fee')?->setting('cost', 0) ?? 0);

        $pending = PendingEventRegistration::create([
            'reference' => (string) Str::uuid(),
            'event_id' => $event->id,
            'registering_user_id' => $user->id,
            'amount' => $price,
            'status' => PendingEventRegistration::STATUS_PENDING,
            'payload' => [
                'event_id' => $event->id,
                'amount_due_each' => $baseFee,
                // Kept only to bump potluck_options.current_count on fulfillment.
                'group' => ['potluck_item_id' => $potluck_item_id],
                'users' => $usersPayload,
            ],
        ]);

        $fulfiller = new RegistrationFulfiller();

        if ($paymentMethod) {
            try {
                Stripe::setApiKey(config('services.stripe.secret'));
                $user->createOrGetStripeCustomer();
                $user->updateDefaultPaymentMethod($paymentMethod);

                // On-session confirm (no off_session): the customer is present, so
                // Stripe can request 3-D Secure authentication (via modal, not a
                // redirect) instead of declining the card. Card-only so Stripe
                // doesn't pull in redirect-based methods that would need a return_url.
                $paymentIntent = \Stripe\PaymentIntent::create([
                    'amount' => (int) round($price * 100),
                    'currency' => 'usd',
                    'customer' => $user->stripe_id,
                    'payment_method' => $paymentMethod,
                    'payment_method_types' => ['card'],
                    'confirm' => true,
                    'metadata' => [
                        'pending_registration_id' => $pending->id,
                        'event_slug' => $event->slug,
                    ],
                ]);

                $pending->stripe_payment_intent_id = $paymentIntent->id;
                $pending->save();

                if ($paymentIntent->status === 'succeeded') {
                    if ($paymentIntent->amount_received > 0) {
                        $pending->amount_paid = $paymentIntent->amount_received / 100;
                        $pending->save();
                        $fulfiller->fulfill($pending);
                    } else {
                        return $this->paymentError($request, 'Payment failed - Amount received invalid');
                    }
                } elseif (in_array($paymentIntent->status, ['requires_action', 'requires_source_action'])) {
                    // Card needs 3-D Secure. Hand the client secret back so the
                    // browser can run the authentication, then finalize below.
                    if ($request->wantsJson()) {
                        return response()->json([
                            'status' => 'requires_action',
                            'client_secret' => $paymentIntent->client_secret,
                        ]);
                    }

                    return back()->with('error', 'This card requires additional verification. Please try again.');
                } else {
                    return $this->paymentError($request, 'Payment could not be completed ('.$paymentIntent->status.').');
                }
            } catch (\Throwable $exception) {
                return $this->paymentError($request, $exception->getMessage());
            }
        } else {
            // Free registration — no payment required.
            $fulfiller->fulfill($pending);
        }

        return $this->registrationSuccess($request, $event);
    }

    /**
     * Complete a registration after the browser finished 3-D Secure. The webhook
     * is the backstop; this makes fulfillment immediate on the happy path.
     */
    public function registerFinalize(Request $request, $slug = null)
    {
        $event = Event::where('slug', '=', $slug)->firstOrFail();
        $paymentIntentId = $request->input('payment_intent_id');

        if (! $paymentIntentId) {
            return response()->json(['status' => 'error', 'message' => 'Missing payment reference.'], 422);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }

        if ($paymentIntent->status !== 'succeeded') {
            return response()->json(['status' => 'error', 'message' => 'Payment was not completed ('.$paymentIntent->status.').'], 422);
        }

        $pendingId = $paymentIntent->metadata->pending_registration_id ?? null;
        (new RegistrationFulfiller())->reconcileSucceeded(
            $paymentIntent->id,
            $pendingId ? (int) $pendingId : null,
            ($paymentIntent->amount_received ?? 0) / 100
        );

        return $this->registrationSuccess($request, $event);
    }

    /**
     * A registered student (in the requester's household) changes their add-ons.
     * The service decides: apply, request a refund, or charge the difference.
     */
    public function registerAdjust(Request $request, $slug = null)
    {
        $event = Event::where('slug', '=', $slug)->firstOrFail();
        $payer = auth()->user();
        $target = User::find($request->input('user_id'));

        // Only the account holder or someone they may act for may be adjusted.
        if (! $target || ! $payer->canManage($target)) {
            abort(403);
        }

        $registration = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $target->id)
            ->first();

        if (! $registration) {
            return response()->json([
                'status' => 'error',
                'message' => 'This registration could not be found — it may have changed. Please refresh the page and try again.',
            ], 422);
        }

        $result = (new \App\Services\AddonAdjustmentService())->submit($request, $registration, $payer);

        if (in_array($result['status'], ['applied', 'refund_requested', 'charged'])) {
            session()->flash('success', $result['message']);
            $result['redirect'] = route('event.register', $event->slug);
        }

        $code = $result['status'] === 'error' ? 422 : 200;

        return response()->json($result, $code);
    }

    /** Complete a top-up charge after in-browser 3-D Secure. */
    public function registerAdjustFinalize(Request $request, $slug = null)
    {
        $event = Event::where('slug', '=', $slug)->firstOrFail();
        $paymentIntentId = $request->input('payment_intent_id');

        if (! $paymentIntentId) {
            return response()->json(['status' => 'error', 'message' => 'Missing payment reference.'], 422);
        }

        try {
            $done = (new \App\Services\AddonAdjustmentService())->finalizeCharge($paymentIntentId);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }

        if (! $done) {
            return response()->json(['status' => 'error', 'message' => 'Could not finalize the change.'], 422);
        }

        session()->flash('success', 'Your add-ons were updated and your card was charged.');

        return response()->json(['status' => 'succeeded', 'redirect' => route('event.register', $event->slug)]);
    }

    private function paymentError(Request $request, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        return back()->with('error', $message);
    }

    private function registrationSuccess(Request $request, Event $event)
    {
        $msg = 'Event registration completed successfully!';
        if ($event->require_ticket) {
            $msg .= '<br><br>You will be receiving a receipt via email.  <a href="#addToWallet">Scroll down</a> for mobile wallet options';
        }
        $msg .= '<br><br>Thank you!';

        if ($request->wantsJson()) {
            session()->flash('success', $msg);

            return response()->json(['status' => 'succeeded', 'redirect' => route('event.register', $event->slug)]);
        }

        return back()->with('success', $msg);
    }

    public function viewRegistrants(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        $regs = $event->users()
            ->withPivot('id')
            ->with(['school', 'rank', 'event_notes'])
            ->leftJoinRelationship('school')
            ->leftJoinRelationship('rank')
            ->orderBy('schools.shortname')
            ->orderBy('rank_id', 'desc')
            ->orderBy('users.lastname')
            ->orderBy('users.firstname')
            ->get();
        $this->attachAddonAnswers($regs);
        $total_count = count($regs);

        $tshirt_breakdown = [];
        $tshirt_total = 0;
        foreach ($regs->filter(fn ($r) => $r->pivot->tshirtSize())->groupBy(fn ($r) => $r->pivot->tshirtSize()) as $size => $rec) {
            $tshirt_breakdown[$size] = $rec->count();
            $tshirt_total += $rec->count();
        }

        $grouped_data = $regs->groupBy('school.shortname');

        return view('event.registrants', compact('grouped_data', 'event', 'total_count', 'tshirt_breakdown', 'tshirt_total'));
    }

    /**
     * Eager-load each registrant's add-on answers onto the pivot so the report
     * accessors ($reg->pivot->tshirtSize() etc.) can read them.
     */
    private function attachAddonAnswers($regs)
    {
        $ids = collect($regs)->pluck('pivot.id')->filter();
        $answers = \App\Models\EventRegistrationAddon::whereIn('event_registration_id', $ids)
            ->get()->groupBy('event_registration_id');

        foreach ($regs as $reg) {
            $reg->pivot->setRelation('addonAnswers', $answers->get($reg->pivot->id, collect()));
        }

        return $regs;
    }

    public function viewRegistrantsByVolunteer(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        $regs = $event->users()
            ->withPivot('id')
            ->with(['school', 'rank'])
            ->leftJoinRelationship('school')
            ->leftJoinRelationship('rank')
            ->orderBy('schools.shortname')
            ->orderBy('rank_id', 'desc')
            ->orderBy('users.lastname')
            ->orderBy('users.firstname')
            ->get();
        $this->attachAddonAnswers($regs);
        $total_count = count($regs);

        $grouped_data = [];
        foreach (($event->addon('volunteer')?->setting('options', []) ?? []) as $opt) {
            $grouped_data[$opt] = [];
        }
        $grouped_data['None'] = [];

        foreach ($regs as $reg) {
            $selection = $reg->pivot->volunteerSelections();
            if (count($selection) < 1) {
                $grouped_data['None'][] = $reg;
            } else {
                foreach ($selection as $sel) {
                    $grouped_data[$sel][] = $reg;
                }
            }
        }

        return view('event.registrants-by-volunteer', compact('grouped_data', 'event', 'total_count'));
    }

    public function downloadRegistrantSpreadsheet(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        return Excel::download(new RegistrantExport($event), $event->slug.'.xlsx');

    }

    public function downloadFinancialsSpreadsheet(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.approveRefunds')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        return Excel::download(new \App\Exports\Event\FinancialsExport($event), $event->slug.'-financials.xlsx');
    }

    public function viewRegistrantsByPotluckItem(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        $regs = $event->users()
            ->withPivot('id', 'registering_user_id')
            ->with(['school', 'rank'])
            ->leftJoinRelationship('school')
            ->leftJoinRelationship('rank')
            ->orderBy('schools.shortname')
            ->orderBy('rank_id', 'desc')
            ->orderBy('users.lastname')
            ->orderBy('users.firstname')
            ->get();
        $this->attachAddonAnswers($regs);

        // Annotate each registration with the others in its household (for the blade).
        foreach($regs as $reg) {
            $reg->other_registrations = $event->users()->where('registering_user_id', $reg->id)->whereNot('users.id', $reg->id)->get();
            $reg->other_registrations_string = $reg->other_registrations
                ->map(fn ($or) => $or->firstname . ' ' . $or->lastname)->implode(', ');
        }

        // Open-signup potluck: no catalog, just free-text dishes. Show a flat list
        // of everyone bringing something, sorted by dish (matches the register page).
        if ($event->addon('potluck')?->setting('open_signup')) {
            $bringing = $regs->filter(fn ($r) => $r->pivot->potluckOpenItem())
                ->sortBy(fn ($r) => strtolower($r->pivot->potluckOpenItem()))
                ->values();
            $total_count = $bringing->count();
            $grouped_data = ['Potluck Items' => [
                'count' => $bringing->count(),
                'records' => ['' => ['count' => $bringing->count(), 'records' => $bringing->all()]],
            ]];

            return view('event.registrants-by-potluck', compact('grouped_data', 'event', 'total_count'));
        }

        // Catalog potluck: group by the chosen catalog item, with a "None" bucket
        // for registering users who didn't pick one.
        $total_count = $regs->filter(fn ($r) => $r->pivot->potluckItemId())->count();

        $grouped_data = [];
        foreach($event->potluck_options->groupBy('category') as $category => $items) {
            $grouped_data[$category] = ['records'=>[], 'count'=>0];
            foreach($items as $item) {
                $grouped_data[$category]['records'][$item['item']] = ['records'=>[], 'count'=>0];
            }
        }
        $grouped_data['None'] = ['records'=>[], 'count'=>0];

        foreach($regs as $reg) {
            $itemId = $reg->pivot->potluckItemId();
            if($itemId && ($plo = PotluckOptions::find($itemId)) && isset($grouped_data[$plo->category]['records'][$plo->item])) {
                $grouped_data[$plo->category]['records'][$plo->item]['records'][] = $reg;
                $grouped_data[$plo->category]['records'][$plo->item]['count'] += 1;
                $grouped_data[$plo->category]['count'] += 1;
            } elseif (! $itemId && $reg->pivot->registering_user_id == $reg->id) {
                // A registering user with no catalog potluck item -> "None".
                $grouped_data['None']['records'][] = $reg;
                $grouped_data['None']['count']++;
            }
        }

        return view('event.registrants-by-potluck', compact('grouped_data', 'event', 'total_count'));
    }

    public function viewRegistrantsByTshirt(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        $regs = $event->users()
            ->withPivot('id')
            ->with(['school', 'rank'])
            ->leftJoinRelationship('school')
            ->leftJoinRelationship('rank')
            ->orderBy('schools.shortname')
            ->orderBy('rank_id', 'desc')
            ->orderBy('users.lastname')
            ->orderBy('users.firstname')
            ->get();
        $this->attachAddonAnswers($regs);
        $regs = $regs->filter(fn ($r) => $r->pivot->tshirtSize())->values();
        $total_count = count($regs);

        $grouped_data = $regs->groupBy(fn ($r) => $r->pivot->tshirtSize());

        $size_order = [
            //"XS" => "Adult XS",
            'S' => 'Adult S',
            'M' => 'Adult M',
            'L' => 'Adult L',
            'XL' => 'Adult XL',
            '2XL' => 'Adult 2XL',
            '3XL' => 'Adult 3XL',
            'YXS' => 'Youth XS',
            'YS' => 'Youth S',
            'YM' => 'Youth M',
            'YL' => 'Youth L',
            'YXL' => 'Youth XL',
        ];

        return view('event.reg_by_tshirt', compact('grouped_data', 'event', 'total_count', 'size_order'));
    }

    /**
     * Registrant breakdown for a single add-on (meal ticket, etc.). Delegates
     * the layout to the add-on handler's report view so each add-on owns its
     * own reporting without a bespoke controller action.
     */
    public function viewRegistrantsByAddon(Request $request, $slug = null, $type = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();
        $addon = $event->addon($type);

        if (! $addon || ! $addon->handler() || ! $addon->handler()->reportView()) {
            abort(404);
        }

        $answers = $addon->answers()
            ->with(['registration.user.school', 'registration.user.rank'])
            ->get()
            ->filter(fn ($a) => $a->registration && $a->registration->user)
            ->values();

        return view($addon->handler()->reportView(), compact('event', 'addon', 'answers'));
    }

    public function viewRegistrantsByDivision(Request $request, $slug = null)
    {

        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $sort = $request->input('sort', 'ras');

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        $users = $event->registrations()
            //->select(['users.*', 'users.age'])
            ->with(['school', 'rank'])
            ->leftJoinRelationship('school')
            ->leftJoinRelationship('rank')
            //->orderBy('schools.shortname')
            //->orderBy('rank_id', 'desc')
            //->orderBy('users.lastname')
            //->orderBy('users.firstname')
            ->get();

        $by_division = [];
        $total_count = 0;
        foreach ($users as $user) {
            $div = \App\Models\EventDivision::find($user->pivot->event_division_id);
            if (! array_key_exists($user->pivot->event_division_id, $by_division)) {
                $by_division[$user->pivot->event_division_id] = ['division_name' => $div->name, 'users' => []];
            }
            $by_division[$user->pivot->event_division_id]['users'][] = $user;
            $total_count++;
        }

        $total_count = count($users);

        ksort($by_division);

        if ($request->input('view') == 'printable') {
            return view('event.reg_by_division_print', compact('by_division', 'event', 'total_count'));
        }

        return view('event.reg_by_division', compact('by_division', 'event', 'total_count'));
    }

    public function viewRegistrantsByNatDiv(Request $request, $slug = null)
    {

        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $sort = $request->input('sort', 'ras');

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        $users = $event->users()
            //->select(['users.*', 'users.age'])
            ->with(['school', 'rank'])
            ->leftJoinRelationship('school')
            ->leftJoinRelationship('rank')
            //->orderBy('schools.shortname')
            //->orderBy('rank_id', 'desc')
            //->orderBy('users.lastname')
            //->orderBy('users.firstname')
            ->get();

        $sortFieldMap = [
            's' => 'sex',
            'a' => 'age',
            'r' => 'rank_id',
        ];
        for ($i = 2; $i > -1; $i--) {
            $users = $users->sortBy($sortFieldMap[$sort[$i]]);
        }
        $grouped_data = $users->groupBy('natural_division_text');

        $total_count = count($users);

        return view('event.reg_by_nat_division', compact('grouped_data', 'event', 'total_count', 'sort'));
    }

    public function printRegistrationCards(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();
        $blanksOnly = $request->input('blanks') == 1;

        $pdf = (new \App\Services\RegistrationCardPdf())->generate($event, $blanksOnly);

        $filename = 'RegCards_'.Str::slug($event->name).($blanksOnly ? '_blank' : '').'.pdf';

        return response()->file($pdf, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ])->deleteFileAfterSend(true);
    }

    /** Print registration cards grouped by the published division arrangement. */
    public function printDivisionCards(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        if (! $event->published_version_id) {
            abort(422, 'Publish a division arrangement before printing division cards.');
        }

        $pdf = (new \App\Services\RegistrationCardPdf())->generateByDivision($event, $request->boolean('covers'));

        $filename = 'RegCards_'.Str::slug($event->name).'_by-division.pdf';

        return response()->file($pdf, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Print the new combined-tournament cards (Forms / Sparring "applications").
     * ?variant=forms|sparring|both, ?blanks=1 for blank cards, ?by=division to
     * group by the published division arrangement (with ?covers=1 for separators).
     */
    public function printTournamentCards(Request $request, $slug = null)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $event = Event::where('slug', '=', $slug)->firstOrFail();

        if (! $event->isCompetition()) {
            abort(422, 'This event is not a Sparring, Forms, or Combined tournament.');
        }

        // Only offer variants this event actually runs.
        $variant = $request->input('variant', 'both');
        $allowed = match (true) {
            $event->hasForms() && $event->hasSparring() => ['forms', 'sparring', 'both'],
            $event->hasForms() => ['forms'],
            default => ['sparring'],
        };
        if (! in_array($variant, $allowed, true)) {
            $variant = $allowed[0];
        }

        $service = new \App\Services\RegistrationCardPdf();

        if ($request->input('by') === 'division') {
            try {
                $pdf = $service->generateTournamentByDivision($event, $variant, $request->boolean('covers'));
            } catch (\RuntimeException $e) {
                abort(422, $e->getMessage());
            }
            $filename = 'RegCards_'.Str::slug($event->name).'_'.$variant.'_by-division.pdf';
        } else {
            $blanksOnly = $request->input('blanks') == 1;
            $pdf = $service->generateTournament($event, $variant, $blanksOnly);
            $filename = 'RegCards_'.Str::slug($event->name).'_'.$variant.($blanksOnly ? '_blank' : '').'.pdf';
        }

        return response()->file($pdf, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ])->deleteFileAfterSend(true);
    }

    public function eventCheckInDetails($slug, $users)
    {
        // A QR badge scan passes registration ids; convert them to user ids.
        if (request()->query('scan') == 1) {
            $user_ids = EventRegistration::whereIn('id', explode(',', $users))->pluck('user_id')->implode(',');

            return redirect()->route('event.check-in-details', [$slug, $user_ids]);
        }

        $event = Event::where('slug', $slug)->firstOrFail();

        // The per-registrant verify/check-in form is a Livewire component.
        return view('event.check-in-details', ['event' => $event, 'slug' => $slug, 'users' => $users]);
    }

    /**
     * Download an Apple Wallet (.pkpass) check-in ticket for the signed-in user's
     * registrations (self + dependents) for this event.
     */
    public function appleWalletPass($slug)
    {
        if (! AppleWalletPass::isConfigured()) {
            abort(503, 'Apple Wallet passes are not configured.');
        }

        $event = Event::where('slug', $slug)->firstOrFail();
        $data = PassData::forUserEvent(auth()->user(), $event);
        if (! $data) {
            abort(404, 'You have no registration for this event.');
        }

        try {
            $pkpass = (new AppleWalletPass())->build($data);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Could not generate the wallet pass.');
        }

        return response($pkpass, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.pkpass"',
            'Content-Transfer-Encoding' => 'binary',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Redirect to Google Wallet's "Save" flow for the signed-in user's
     * registrations for this event.
     */
    public function googleWalletPass($slug)
    {
        if (! GoogleWalletPass::isConfigured()) {
            abort(503, 'Google Wallet passes are not configured.');
        }

        $event = Event::where('slug', $slug)->firstOrFail();
        $data = PassData::forUserEvent(auth()->user(), $event);
        if (! $data) {
            abort(404, 'You have no registration for this event.');
        }

        try {
            $url = (new GoogleWalletPass())->saveUrl($data);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Could not generate the wallet pass.');
        }

        return redirect()->away($url);
    }

    /**
     * Download the printable meal-ticket voucher for the signed-in user's
     * household for this event. Regenerated on demand so it always reflects the
     * current meal quantity. 404s when the household has no meals.
     */
    public function mealVoucher($slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $pdf = (new \App\Services\MealVoucherPdf())->forHousehold($event, auth()->user());
        if (! $pdf) {
            abort(404, 'You have no meal tickets for this event.');
        }

        return response()->download($pdf, Str::slug($event->name).'-meal-ticket.pdf', [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend();
    }

    public function arrangeDivisions(Request $request, $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $discipline = $this->resolveDiscipline($event, $request);

        // Belt ranks present among this event's registrants, high → low, for the
        // filter checkboxes.
        $rankIds = User::whereIn('id', EventRegistration::where('event_id', $event->id)->pluck('user_id'))
            ->pluck('rank_id')->unique()->filter(fn ($r) => $r !== null)->values();
        $ranks = Rank::whereIn('id', $rankIds)->orderByDesc('id')->get(['id', 'rank']);

        $svc = new \App\Services\EventDivisionService();
        $latest = \App\Models\EventDivisionVersion::where('event_id', $event->id)
            ->where('discipline', $discipline)->orderByDesc('id')->first();

        return view('event.arrange-divisions', [
            'event' => $event,
            'slug' => $slug,
            'discipline' => $discipline,
            'disciplines' => $this->allowedDisciplines($event),
            'board' => $svc->board($event, $discipline),
            'ranks' => $ranks,
            'published' => $svc->publishedInfo($event, $discipline),
            'latestVersion' => $latest ? ['id' => $latest->id, 'starred' => (bool) $latest->starred] : null,
        ]);
    }

    /** Compute a fresh auto-arrangement (not persisted) for the board. */
    public function arrangeDivisionsAuto(Request $request, $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $discipline = $this->resolveDiscipline($event, $request);

        return response()->json(['divisions' => (new \App\Services\EventDivisionService())->auto($event, $discipline)]);
    }

    /** Persist the current board arrangement. */
    public function arrangeDivisionsSave(Request $request, $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $discipline = $this->resolveDiscipline($event, $request);

        $divisions = $request->validate([
            'divisions' => 'present|array',
            'divisions.*.id' => 'nullable|integer',
            'divisions.*.label' => 'nullable|string|max:255',
            'divisions.*.members' => 'array',
            'divisions.*.members.*' => 'integer',
        ])['divisions'];

        $svc = new \App\Services\EventDivisionService();
        $versionId = $svc->save($event, $divisions, $discipline);

        return response()->json([
            'ok' => true,
            'board' => $svc->board($event, $discipline),
            'version' => ['id' => $versionId, 'starred' => false],
        ]);
    }

    /** Saved versions for the history panel. */
    public function arrangeDivisionsVersions(Request $request, $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $discipline = $this->resolveDiscipline($event, $request);

        return response()->json(['versions' => (new \App\Services\EventDivisionService())->versions($event, $discipline)]);
    }

    /** Division disciplines this event supports (sparring and/or forms). */
    private function allowedDisciplines(Event $event): array
    {
        $out = [];
        if ($event->hasSparring() || ! $event->hasForms()) {
            $out[] = \App\Models\EventDivision::DISCIPLINE_SPARRING; // legacy/untyped → sparring
        }
        if ($event->hasForms()) {
            $out[] = \App\Models\EventDivision::DISCIPLINE_FORMS;
        }

        return $out ?: [\App\Models\EventDivision::DISCIPLINE_SPARRING];
    }

    /** The requested discipline, clamped to what the event supports. */
    private function resolveDiscipline(Event $event, Request $request): string
    {
        $allowed = $this->allowedDisciplines($event);
        $requested = $request->input('discipline', $allowed[0]);

        return in_array($requested, $allowed, true) ? $requested : $allowed[0];
    }

    /** Board data for a saved version (Restore loads it as a draft). */
    public function arrangeDivisionsRestore($slug, $versionId)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $version = \App\Models\EventDivisionVersion::where('event_id', $event->id)->findOrFail($versionId);

        return response()->json(['divisions' => (new \App\Services\EventDivisionService())->versionBoard($version)]);
    }

    /** Toggle a version's star. */
    public function arrangeDivisionsStar($slug, $versionId)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $version = \App\Models\EventDivisionVersion::where('event_id', $event->id)->findOrFail($versionId);

        return response()->json(['ok' => true, 'starred' => (new \App\Services\EventDivisionService())->toggleStar($version)]);
    }

    /** Set a version's free-text note. */
    public function arrangeDivisionsNote(Request $request, $slug, $versionId)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $version = \App\Models\EventDivisionVersion::where('event_id', $event->id)->findOrFail($versionId);
        $note = $request->validate(['note' => 'nullable|string|max:2000'])['note'] ?? null;

        (new \App\Services\EventDivisionService())->updateNote($version, $note);

        return response()->json(['ok' => true]);
    }

    /** Publish a version as the event's official arrangement. */
    public function arrangeDivisionsPublish($slug, $versionId)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $version = \App\Models\EventDivisionVersion::where('event_id', $event->id)->findOrFail($versionId);

        $svc = new \App\Services\EventDivisionService();
        $svc->publish($event, $version);

        return response()->json(['ok' => true, 'published' => $svc->publishedInfo($event->fresh(), $version->discipline)]);
    }

    public function arrangeDivisionsUnpublish(Request $request, $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $discipline = $this->resolveDiscipline($event, $request);
        (new \App\Services\EventDivisionService())->unpublish($event, $discipline);

        return response()->json(['ok' => true, 'published' => null]);
    }

    public function eventCheckIn($slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        // The registrant list is a Livewire table (App\Livewire\Event\CheckIn);
        // the blade also wires the QR badge scanner.
        return view('event.event-checkin', ['event' => $event, 'slug' => $slug]);
    }

    public function completeRegForm($slug) {
        $event = Event::where('slug', $slug)->first();
        $payment_ids = EventRegistration::where('event_id', $event->id)->pluck('payment_id')->toArray();
        $payment = Payment::whereIn('id', $payment_ids)->where('user_id', auth()->user()->id)->first();
        $regs = [];


        if(!$payment) {
            $intent = [];
            $payment = Payment::where('event_id', $event->id)->where('user_id', auth()->user()->id)->first();
            $regs = EventRegistration::where('payment_id', $payment->id)->with('user.rank')->get();
            $payment_made = $payment->created_at->format('m/d/Y g:i a');
            return Inertia::render('Event/Register/Complete/AlreadySucceeded', ['event' => $event, 'intent' => $intent, 'regs' => $regs, 'payment_made' => $payment_made]);
        } else {
            $regs = EventRegistration::where('payment_id', $payment->id)->with('user.rank')->get();
            $payment_made = $payment->created_at->format('m/d/Y g:i a');
            $payment_intent_id = $payment->stripe_payment_intent_id;

            //Lookup from stripe API
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $intent = $stripe->paymentIntents->retrieve($payment_intent_id, []);

            if($intent->status == 'succeeded') {
                //no issue
                return Inertia::render('Event/Register/Complete/AlreadySucceeded', ['event' => $event, 'intent' => $intent, 'regs' => $regs, 'payment_made' => $payment_made]);
            }  elseif($intent->status == 'requires_payment_method') {
                //show form to accept payment to finish.
                return Inertia::render('Event/Register/Complete/RegForm', ['event' => $event, 'intent' => $intent, 'regs' => $regs, 'stripe_key' => config('app.stripe_key'), 'payment_made' => $payment_made]);
            } else {
                //something else
                return Inertia::render('Event/Register/Complete/Other', ['event' => $event, 'intent' => $intent, 'regs' => $regs, 'payment_made' => $payment_made]);
            }

        }


    }
}
