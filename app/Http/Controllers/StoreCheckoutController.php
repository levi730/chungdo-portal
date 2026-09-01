<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\School;
use App\Services\Store\Cart;
use App\Services\Store\OrderBuilder;
use App\Services\Store\ProductOrderFulfiller;
use App\Services\Stripe\StripeAccounts;
use App\Services\Stripe\StripeCustomerResolver;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * Store checkout — one fulfiller, two front doors (docs/store-design.md).
 *
 * Members pay on-page with PaymentIntent + Elements, the same shape as
 * EventController::register(). Guests go to Stripe Hosted Checkout, because
 * StripeCustomerResolver::createSetupIntent() needs a User.
 *
 * Either way the order row and its items are written on OUR page first, and
 * Stripe only ever carries a pointer to it.
 */
class StoreCheckoutController extends Controller
{
    public function __construct(
        private Cart $cart,
        private OrderBuilder $builder,
        private ProductOrderFulfiller $fulfiller,
    ) {}

    public function show()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('store.index')->with('error', 'Your cart is empty.');
        }

        $user = auth()->user();
        $account = $this->cart->stripeAccount();

        // Members collect their card on this page, so the SetupIntent and the
        // publishable key must both come from the account that will be charged.
        $intent = $user
            ? app(StripeCustomerResolver::class)->createSetupIntent($user, $account)
            : null;

        return view('store.checkout', [
            'cart' => $this->cart,
            'user' => $user,
            'schools' => School::orderBy('name')->get(),
            'intent' => $intent,
            'stripeKey' => app(StripeAccounts::class)->publishableKey($account),
        ]);
    }

    /**
     * Write the order, then charge. Members are charged here; guests are handed
     * a Checkout Session URL to redirect to.
     */
    public function store(Request $request)
    {
        if ($this->cart->isEmpty()) {
            return $this->error($request, 'Your cart is empty.');
        }

        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'pickup_school_id' => 'required|integer|exists:schools,id',
            'payment_method' => 'nullable|string',
        ]);

        // Record intent before money moves.
        $order = $this->builder->build([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'pickup_school_id' => (int) $data['pickup_school_id'],
        ], $user);

        return $user
            ? $this->chargeMember($request, $order, $data['payment_method'] ?? null)
            : $this->sendToHostedCheckout($request, $order);
    }

    /** Member: PaymentIntent confirmed server-side, 3-D Secure handled in-page. */
    private function chargeMember(Request $request, ProductOrder $order, ?string $paymentMethod)
    {
        if (! $paymentMethod) {
            return $this->error($request, 'Please enter your card details.');
        }

        try {
            $account = $order->stripe_account;
            Stripe::setApiKey(app(StripeAccounts::class)->secret($account));

            // Customer ids don't cross accounts.
            $customerId = app(StripeCustomerResolver::class)
                ->resolve(auth()->user(), $account, $paymentMethod);

            $intent = PaymentIntent::create([
                'amount' => (int) round($order->total * 100),
                'currency' => 'usd',
                'customer' => $customerId,
                'payment_method' => $paymentMethod,
                'payment_method_types' => ['card'],
                'confirm' => true,
                // A pointer, never the order itself.
                'metadata' => ['product_order_id' => $order->id],
            ]);

            // Saved immediately, so the webhook can find this row even if the
            // response below never reaches us.
            $order->stripe_payment_intent_id = $intent->id;
            $order->save();

            if ($intent->status === 'succeeded') {
                $order->amount_paid = ($intent->amount_received ?? 0) / 100;
                $order->save();
                $this->fulfiller->fulfill($order);

                return $this->success($request, $order);
            }

            if (in_array($intent->status, ['requires_action', 'requires_source_action'], true)) {
                // The browser runs 3-D Secure, then calls finalize().
                return response()->json([
                    'status' => 'requires_action',
                    'client_secret' => $intent->client_secret,
                ]);
            }

            return $this->error($request, 'Payment could not be completed ('.$intent->status.').');
        } catch (\Throwable $e) {
            return $this->error($request, $e->getMessage());
        }
    }

    /** Guest: Stripe Hosted Checkout. The order already exists on our side. */
    private function sendToHostedCheckout(Request $request, ProductOrder $order)
    {
        try {
            Stripe::setApiKey(app(StripeAccounts::class)->secret($order->stripe_account));

            $session = Session::create([
                'mode' => 'payment',
                'customer_email' => $order->email,
                'client_reference_id' => $order->reference,
                'metadata' => ['product_order_id' => $order->id],
                // THE one that matters: a session's own metadata does not reach
                // the PaymentIntent it creates, and the webhook backstop keys on
                // payment_intent.succeeded.
                'payment_intent_data' => [
                    'metadata' => ['product_order_id' => $order->id],
                ],
                'line_items' => $order->items->map(fn ($item) => [
                    'quantity' => $item->quantity,
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => (int) round($item->unit_price * 100),
                        'product_data' => [
                            'name' => $item->product_name.' — '.$item->variant_name,
                        ],
                    ],
                ])->all(),
                'success_url' => route('store.complete', $order->reference).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('store.cart'),
            ]);

            $order->stripe_checkout_session_id = $session->id;
            $order->save();

            if ($request->wantsJson()) {
                return response()->json(['status' => 'redirect', 'redirect' => $session->url]);
            }

            return redirect()->away($session->url);
        } catch (\Throwable $e) {
            return $this->error($request, $e->getMessage());
        }
    }

    /**
     * Complete a member order after the browser finished 3-D Secure. The intent
     * is re-fetched from Stripe and verified server-side — the browser only
     * supplies an id, never a claim we trust.
     */
    public function finalize(Request $request)
    {
        $paymentIntentId = $request->input('payment_intent_id');

        if (! $paymentIntentId) {
            return response()->json(['status' => 'error', 'message' => 'Missing payment reference.'], 422);
        }

        $order = ProductOrder::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $order) {
            return response()->json(['status' => 'error', 'message' => 'Unknown order.'], 422);
        }

        try {
            Stripe::setApiKey(app(StripeAccounts::class)->secret($order->stripe_account));
            $intent = PaymentIntent::retrieve($paymentIntentId);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        if ($intent->status !== 'succeeded') {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment was not completed ('.$intent->status.').',
            ], 422);
        }

        $this->fulfiller->reconcileSucceeded(
            $intent->id,
            $order->id,
            ($intent->amount_received ?? 0) / 100
        );

        return $this->success($request, $order->fresh());
    }

    /**
     * Where Hosted Checkout returns the guest. Fulfils synchronously so the
     * order is complete before the page renders; the webhook is the backstop,
     * not the mechanism.
     */
    public function complete(Request $request, string $reference)
    {
        $order = ProductOrder::where('reference', $reference)->firstOrFail();

        if ($order->isPending() && $order->stripe_checkout_session_id) {
            try {
                Stripe::setApiKey(app(StripeAccounts::class)->secret($order->stripe_account));
                $session = Session::retrieve($order->stripe_checkout_session_id);

                if (($session->payment_status ?? null) === 'paid') {
                    if (! $order->stripe_payment_intent_id && ($session->payment_intent ?? null)) {
                        $order->stripe_payment_intent_id = $session->payment_intent;
                        $order->save();
                    }

                    $this->fulfiller->reconcileSucceeded(
                        (string) ($order->stripe_payment_intent_id ?? $session->id),
                        $order->id,
                        ($session->amount_total ?? 0) / 100
                    );
                }
            } catch (\Throwable $e) {
                // The webhook and the sweep will finish it; don't fail the page
                // the buyer is looking at.
                report($e);
            }
        }

        $order->refresh();

        if ($order->isPaid()) {
            $this->cart->clear();
        }

        return view('store.complete', ['order' => $order->load('items')]);
    }

    /* ------------------------------------------------------------------ *
     * Responses
     * ------------------------------------------------------------------ */

    private function success(Request $request, ProductOrder $order)
    {
        $this->cart->clear();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'succeeded',
                'redirect' => route('store.complete', $order->reference),
            ]);
        }

        return redirect()->route('store.complete', $order->reference);
    }

    private function error(Request $request, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        return back()->with('error', $message);
    }
}
