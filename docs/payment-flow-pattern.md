# Payment flow pattern

How to take money in this portal. Two generations of payment code exist today;
this documents which one to copy and why.

## The rule

**Write a row in our database before charging the card. Put only a pointer in
Stripe metadata. Fulfill from both the synchronous return and the webhook,
through one idempotent fulfiller.**

Everything below is the reasoning and the mechanics.

## The two existing flows

### Event registration — the pattern to copy

`EventController::register()` → `App\Services\RegistrationFulfiller`

```
PendingEventRegistration::create([...payload])      # record FIRST, before charging
PaymentIntent::create([
    'metadata' => ['pending_registration_id' => $pending->id, 'event_slug' => ...],
])                                                  # metadata is a POINTER
$fulfiller->fulfill($pending)                       # synchronous happy path
payment_intent.succeeded → reconcileSucceeded()     # webhook BACKSTOP
```

Properties that matter:

- The full order (`payload` JSON: event_id, users, amounts) lives in our
  `pending_event_registrations` row, not in Stripe.
- Fulfillment is idempotent by construction: `DB::transaction` +
  `lockForUpdate()` + a status check, and `Payment::firstOrCreate` keyed on the
  payment intent id. `fulfill()` returns whether *this* call did the work, so
  the confirmation email is sent exactly once.
- 3-D Secure is handled explicitly: `requires_action` → browser runs
  `confirmCardPayment` → `registerFinalize` completes it server-side.
- A lost synchronous response costs nothing; the webhook finishes the job.

### Project United — the pattern to avoid (retiring; see project-united-retirement.md)

`ProjectUnitedController::processDonation()` → `App\Jobs\ProcessProjectUnitedPayment`

```
Session::create(['metadata' => ['raw_items' => json_encode(...),
                                'mailing_address' => json_encode(...), ...]])
→ redirect to Stripe's hosted page
→ success_url renders a thank-you page and records NOTHING
→ checkout.session.completed webhook is the ONLY thing that creates a record
```

What goes wrong:

1. **Webhook-only fulfillment is a single point of failure.** Miss the webhook —
   endpoint not subscribed, misconfigured, or auto-disabled by Stripe after
   repeated failures — and the customer is charged with no record anywhere in
   the portal. This is exactly the "charged but not registered" gap that
   `pending_event_registrations` was introduced to close.
2. **Metadata is not a database.** Stripe caps metadata at 50 keys and 500
   characters per value. `raw_items` and `mailing_address` are JSON-stuffed into
   those slots; a long address or a large order truncates or errors, and you
   find out at fulfillment time.
3. **No pre-payment record means no reconciliation.** "Who paid but wasn't
   fulfilled?" is unanswerable — there is nothing to compare Stripe against.
4. **`exists()` is not idempotency.** `ProcessProjectUnitedPayment` guards with
   `ProjectUnitedTransaction::where('stripe_id', ...)->exists()`. Two concurrent
   deliveries can both pass that check. A row lock cannot.

## Hosted Checkout vs. on-page Elements

This is a UX choice, not an architecture choice — the rule above applies either
way.

Reach for **Checkout Session** when the buyer may not be logged in. The event
registration flow cannot do guest checkout as written, because
`EventController::register()` (line ~176) calls
`auth()->user()->createSetupIntent()`. Checkout also keeps card entry on
Stripe's page and provides a hosted receipt.

Reach for **PaymentIntent + Elements** when the buyer is authenticated and you
want them to stay on our page.

Either way: create the pending row first, and pass only its id in metadata.

## Skeleton for the next one

```php
// 1. Record intent before money moves.
$pending = PendingThing::create([
    'reference' => (string) Str::uuid(),
    'status'    => 'pending',
    'amount'    => $total,
    'payload'   => [...],           // everything needed to fulfill
]);

// 2. Charge, passing only a pointer.
$session = Session::create([
    ...,
    'metadata' => ['pending_thing_id' => $pending->id],
]);

// 3. Fulfill on the synchronous return (success_url handler / JSON response).
$fulfiller->fulfill($pending);

// 4. Fulfill again from the webhook. Same fulfiller, idempotent.
//    See RegistrationFulfiller::reconcileSucceeded().

// 5. Sweep anything neither path finished (see below).
```

The fulfiller must look like `RegistrationFulfiller::fulfill()`:

```php
DB::transaction(function () use ($pending) {
    $p = PendingThing::whereKey($pending->id)->lockForUpdate()->first();
    if (! $p || $p->status === 'fulfilled') {
        return null;                 // someone else got here first
    }
    // ... do the work, set status ...
});
// send email only if this call did the work
```

## The reconcile sweep (missing from both flows today)

Neither flow can currently self-heal from a webhook that never arrives. Any new
payment flow should ship with a scheduled command that:

1. finds pending rows older than ~15 minutes still in `pending`,
2. asks Stripe what actually happened to the referenced session or intent,
3. fulfills or fails them accordingly.

This is the only backstop that does not depend on a webhook being delivered at
all — which matters, because webhook endpoints can be disabled by Stripe without
notice when deliveries fail (see stripe-webhooks.md notes in project memory).

## Webhook response codes

`StripeWebhookController` answers **4xx only for a failed signature check**.
Anything recognized-but-not-ours is acknowledged with 200. Stripe retries
non-2xx responses for days and disables endpoints with sustained failures, so
returning an error for an event we can never process would eventually take the
endpoint down. Do not "fix" an unhandled event type by returning 400.

## Multiple Stripe accounts

Each event names the Stripe account its money lands in, in
`events.stripe_account` — a key in `config('services.stripe.accounts')`.
New events default to `association`; existing events were backfilled to it.

`App\Services\Stripe\StripeAccounts` is the only place credentials are read.
**Never call `config('services.stripe.secret')` for event money** — that
constant is the association's and would silently charge the wrong account. Use
`secretForEvent($event)` / `publishableKeyForEvent($event)` instead.

The publishable key must match the account the SetupIntent and PaymentIntent
were created on, so it is passed from the server to the page (`$stripeKey` in
`event/register.blade.php`, the `stripe_key` prop in `RegForm.vue`) rather than
baked in at build time from `VITE_STRIPE_KEY`.

Stripe customer ids do not cross accounts. Cashier's `users.stripe_id` is only
valid on the association's account, so customers are tracked per account in
`stripe_customers` and resolved through
`App\Services\Stripe\StripeCustomerResolver`. Association rows are seeded
lazily from `users.stripe_id`, so existing customers carry over.

The account is locked once an event has taken a payment: refunds must be issued
on the account that took the charge. The lock is enforced in three places — the
select is disabled in the form, `EventRequest::withValidator()` rejects a
change, and `EventAdminController::fill()` won't overwrite it.

One webhook URL serves every account, so `StripeWebhookController` checks the
signature against each configured signing secret until one verifies. Register
the same endpoint URL in each Stripe account and put its signing secret in that
account's `*_WEBHOOK_SECRET`.
