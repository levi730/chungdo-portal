# Store (merchandise) — design

Selling items that are not tied to an event registration: a design printed on
t-shirts, two colors of sweatshirt, and a gym bag. Written 2026-08-31, before
any code exists, so the decisions are reviewable.

Read `payment-flow-pattern.md` first. Everything here obeys it.

## Build it, don't integrate a shop framework

Evaluated: **Lunar** (`lunarphp/lunar`), **Bagisto**, **Aimeos**.

- **Bagisto** is a standalone storefront application, not a package to embed. It
  owns routing, auth and the admin. Out.
- **Aimeos** is larger than Lunar and ships its own Vue admin panel. Same
  objection, more of it.
- **Lunar** is the only real candidate — an actively maintained package rather
  than an app. It still loses here:
  - It brings ~40 tables and its own customer, channel, currency, tax-zone,
    collection and pricing models. Identity in this portal is already
    `users` + `spatie/permission` + Passport/OIDC; Lunar would run a parallel
    customer record next to it.
  - Its admin is Filament. This portal's admin is Blade + Tabler +
    `rappasoft/laravel-livewire-tables`. Adding Lunar means a second admin UI
    with a different look and a different permission system.
  - **Its Stripe driver assumes one account.** `stripe_account` routing
    (`docs/payment-flow-pattern.md`, "Multiple Stripe accounts") would mean
    forking the driver — which is the single most load-bearing piece of the
    integration.
  - Its order pipeline fulfills from the webhook, the shape this project
    deliberately moved away from.

What we actually need is small and has a template in-repo for every piece:

| Need | Already exists to copy |
| --- | --- |
| Pending row → idempotent fulfiller → webhook backstop | `RegistrationFulfiller` |
| Multi-account Stripe credentials | `App\Services\Stripe\StripeAccounts` |
| Per-account customers | `StripeCustomerResolver` |
| Variant/option answers (size, color) | `App\EventAddons\TshirtAddon` |
| Home-page highlighting | `Event::forHomepage()`, `highlighted` / `highlight_order` |
| Images with focus points | `spatie/laravel-medialibrary` + `EventAdminController::setMediaFocus` |
| Money ledger | `payments` table |
| Admin CRUD + slug + soft delete | `EventAdminController` |

Estimated: 4 migrations, 4 models, 1 fulfiller, 2 controllers, 1 console
command, and the views. Less integration work than adapting Lunar's Stripe
driver alone.

## Scope of v1 (decided 2026-08-31)

- **Buyers:** members *and* guests. Members buy on-page; guests use Stripe
  Hosted Checkout.
- **Cart:** yes, multi-item — one order can hold a shirt and a bag.
- **Delivery:** pickup only. Pickup is at a school; guests choose one from a
  list, members default to their own. Shipping is a later addition and the
  schema should not make it painful.
- **Stock:** no counting. A product has an order window (`orders_close_at`) and
  the print run is sized from the orders afterward.

## The first run's catalogue (from Mike, 2026-08-31)

One design with axes **Item** and **Size**, sold in **print runs**. Each run has
its own window, its own expected arrival, and its own price list. 23 variants per
run:

| Item | Sizes | Price |
| --- | --- | --- |
| Adult T-Shirt (Bella + Canvas, dark gray) | XS S M L XL 2XL 3XL | $20, +$2 at 2XL, +$3 at 3XL |
| Youth T-Shirt (Bella + Canvas, dark gray) | S M L XL | $20 |
| Adult Hoodie (Independent Trading, dark gray) | XS S M L XL 2XL 3XL | $45, +$2 at 2XL, +$3 at 3XL |
| Youth Hoodie (Independent Trading, lighter grey) | S M L XL | $30 |
| Gym Bag (embroidered, dark gray/black) | none | $45 |

Colour is not an axis — each item comes in exactly one, so it belongs in the
description.

**Adult and youth are separate Item values, not a size range on one item.** They
have to be. Youth S and adult S are different garments but would be the same
`Size` value, so folding them into one "T-Shirt" item would give two rows with an
identical Item + Size — indistinguishable to the buyer, ambiguous on the pick
list, and the editor's duplicate check would treat the second as already listed
and drop it. Hoodies are separate anyway (different price and different grey);
splitting tees the same way keeps the whole list parallel.

Four Quick add batches plus one hand-added row for the bag:

```
Adult T-Shirt   XS, S, M, L, XL, 2XL+2, 3XL+3   base 20
Youth T-Shirt   S, M, L, XL                      base 20
Adult Hoodie    XS, S, M, L, XL, 2XL+2, 3XL+3   base 45
Youth Hoodie    S, M, L, XL                      base 30
Gym Bag         (no size, added by hand)         45
```

## Data model

Four tables. Money is `decimal(8,2)` to match `payments` and
`pending_event_registrations`; convert to cents only at the Stripe boundary.

### `products`

The **design**, and nothing time-bound.

```
id, timestamps, deleted_at
name                 string
slug                 string unique          # /store/{slug}
stripe_account       string(50) default 'association'
status               string default 'draft' # draft | active | archived
description          text nullable          # markdown, via MarkdownProcessorService
option_names         json nullable          # ["Item","Size"]
max_per_order        unsigned int nullable
highlighted          boolean default false
highlight_order      unsigned small default 0
sort_order           unsigned int default 0
```

### `product_runs`

One printing of the design. A design is printed more than once, so the ordering
window is not a property of the design.

```
id, timestamps
product_id           foreignId
name                 string                 # "Fall 2026" — staff-facing
opens_at             datetime nullable      # null = already open
closes_at            datetime nullable      # null = no deadline yet
expected_arrival_at  date nullable          # the print shop's estimate
pickup_note          string nullable        # "Pick up at your school after Oct 15"
sort_order           unsigned int default 0
```

**Only one run of a design may be open at a time** (`ProductRunRequest`). Two
live windows would mean asking the buyer which run they were ordering into, and
the pick list could no longer name one arrival date per line. Note the
consequence: a run with *no* dates is open forever, so nothing can follow it
until it is given a close date.

The rule is **per design, not store-wide** — the check starts from
`$product->runs()`, and there is no unique index across products. Different
designs run concurrently, which is the normal case: a shirt design and a bag
design both taking orders in the same weeks. Pinned by "it lets different designs
run at the same time" and "it opens both designs at once" in `ProductRunTest`.

`Product::isOrderable()` is now "status is active **and** `openRun()` is not
null". A design with no run is not for sale — no window, no price list — and
`forHomepage()` skips it.

Images come from medialibrary (`implements HasMedia`), same as events.

### `product_variants`

The buyable SKUs **of one run**, not of the design. Vendor costs move between
runs and each run has to keep the list it actually sold at, so a new run copies
the previous one's variants (`ProductVariantSync::copy`) rather than sharing
them. Without the copy this design would mean retyping 23 rows per run, so the
copy is required, not a nicety.

```
id, timestamps
product_run_id       foreignId
name                 string                 # "Adult Hoodie / L" (display)
options              json nullable          # {"Item":"Adult Hoodie","Size":"L"}
sku                  string nullable
price                decimal(8,2)
enabled              boolean default true
sort_order           unsigned int default 0
```

One product with an `options` map covers both shapes of this launch: the four
items can be one product ("2026 Chung Do design") whose variants span
item × color × size, or four products each with size variants. The admin form
should not force the choice. **Recommendation for this launch:** one product,
because it's one design, one order window, one home-page card, and one pickup —
and the pick list then sorts by item within a school.

### `product_orders`

This *is* the pending row. There is no separate pending table — the order is
written before the card is charged and its `status` carries the lifecycle, the
same as `pending_event_registrations`.

```
id, timestamps
reference                    uuid unique          # public order number
status                       string default 'pending'   # pending | paid | failed | cancelled
fulfillment_status           string default 'awaiting'  # awaiting | ready | collected
stripe_account               string(50)           # snapshot; the cart's account
stripe_payment_intent_id     string nullable index
stripe_checkout_session_id   string nullable index
payment_id                   unsignedBigInteger nullable
user_id                      unsignedBigInteger nullable   # null for guests
email                        string
name                         string
phone                        string nullable
pickup_school_id             unsignedBigInteger nullable
subtotal                     decimal(8,2)
total                        decimal(8,2)         # == subtotal in v1; shipping later
amount_paid                  decimal(8,2) nullable
refunded_amount              decimal(8,2) default 0
stripe_refund_id             string nullable
payload                      json                 # full snapshot: everything needed to fulfill
paid_at, fulfilled_at, collected_at   datetime nullable
admin_note                   text nullable
```

### `product_order_items`

Line items are written **when the order is created**, before the charge — not
at fulfillment. That makes the pending order self-describing: the Stripe-account
lock, the pick list and the financials export all read from `product_order_items`
rather than parsing `payload`, and fulfillment only has to flip a status. They
snapshot the name and price so a later price edit never rewrites history.
`payload` remains the raw snapshot of what the buyer submitted.

```
id, timestamps
product_order_id     foreignId
product_id           unsignedBigInteger
product_run_id       unsignedBigInteger nullable   # which printing this line is for
product_variant_id   unsignedBigInteger nullable   # nullable: variant may be deleted later
product_name         string        # snapshot
variant_name         string        # snapshot
unit_price           decimal(8,2)  # snapshot
quantity             unsigned int
amount               decimal(8,2)  # unit_price * quantity
```

**The run pointer is on the line, not on the order.** Runs are per-product and a
cart can hold two products whose open runs differ — a shirt arriving 15 October
and a bag arriving 1 November. On the order, that order could not describe
itself; on the line it can, the pick list groups by run directly, and the
confirmation email can give a per-line arrival date instead of one date that is
wrong for half the order.

The variant implies the run, but `product_variant_id` is nullable and the pick
list should not have to reach through a row that may be gone.

Watch the `$fillable`: `product_run_id` missing from `ProductOrderItem` meant
mass assignment silently dropped it, and the pick list would have had nothing to
group by. Caught by the "refuses to delete an ordered variant" test.

### `payments`

Reuse it — it is the ledger, and reusing it is what keeps store money and event
money reconcilable against Stripe in one place.

Two changes needed:

1. `payments.user_id` is currently non-nullable (no FK constraint, so this is a
   cheap change). Guest orders have no user. Make it nullable.
2. Add `product_order_id` nullable, mirroring how registrations link back.

`FinancialsExport` is event-scoped and will not pick these up; the store needs
its own export (below). That is fine — the *table* is shared, the *report* is
not.

## Stripe account routing

`StripeAccounts` currently speaks `Event`. Generalize it rather than adding a
parallel set of `*ForProduct` methods:

```php
interface ChargedToStripeAccount { public function stripeAccountSlug(): ?string; }
```

`Event` and `Product` implement it; add `StripeAccounts::for($model)`,
`secretFor($model)`, `publishableKeyFor($model)`. Keep `forEvent()` /
`secretForEvent()` as one-line wrappers so nothing existing has to change.

Two rules carry over from events, and one is new:

- **Never** call `config('services.stripe.secret')` for store money.
- **Lock the account once money has moved** for a product — refunds must be
  issued on the account that took the charge. Enforce it in the same three
  places events do: disable the select, reject in the form request, and refuse
  the overwrite in the controller's `fill()`.
- **New: one cart, one account.** A charge lands in exactly one account, so a
  cart may not mix products whose `stripe_account` differs. Validate on add-to-
  cart and refuse with a plain message ("This item is sold by a different
  account — check out separately"). In practice everything will be
  `association`, but the check has to exist or the first mixed cart silently
  charges the wrong account for half the order.

## Payment flows

One fulfiller, two front doors.

### Member (authenticated) — PaymentIntent + Elements

Same as `EventController::register()`: `StripeCustomerResolver` makes a
SetupIntent on the product's account, the page gets that account's publishable
key from the server (never `VITE_STRIPE_KEY`), `requires_action` → browser
`confirmCardPayment` → a `finalize` route completes it server-side.

### Guest — Hosted Checkout Session

Needed because `createSetupIntent()` requires `auth()->user()`. The order row
(with email, name and pickup school) is written on **our** page first; Checkout
only takes the card.

**The critical detail:** metadata on a Checkout Session does not appear on the
PaymentIntent it creates. The webhook backstop keys on
`payment_intent.succeeded`, which already works and is subscribed on both
accounts. So set the pointer in *both* places:

```php
Session::create([
    'mode' => 'payment',
    'client_reference_id' => $order->reference,
    'metadata' => ['product_order_id' => $order->id],
    'payment_intent_data' => [
        'metadata' => ['product_order_id' => $order->id],   // <-- the one that matters
    ],
    'line_items' => [...],      // price_data inline; no Stripe Product catalog to keep in sync
    'success_url' => route('store.complete', ['reference' => $order->reference]) . '?session_id={CHECKOUT_SESSION_ID}',
    ...
]);
```

Prices stay local and go out as `price_data`. Creating Stripe Products/Prices
would mean maintaining a catalog per account and re-creating it if the account
changes.

Both doors then call the same fulfiller — the `success_url` handler retrieves
the session, confirms `payment_status === 'paid'`, and fulfills synchronously.

## `ProductOrderFulfiller`

A near-copy of `RegistrationFulfiller::fulfill()`. Row lock + status check, not
`exists()`. Returns whether *this* call did the work, so the confirmation email
goes out exactly once.

```php
DB::transaction(function () use ($order) {
    $o = ProductOrder::whereKey($order->id)->lockForUpdate()->first();
    if (! $o || $o->status === ProductOrder::STATUS_PAID) {
        return null;                       // someone else got here first
    }
    $payment = Payment::firstOrCreate(
        ['stripe_payment_intent_id' => $o->stripe_payment_intent_id],
        ['user_id' => $o->user_id, 'amount_paid' => $o->amount_paid ?? 0, 'product_order_id' => $o->id]
    );
    // Items already exist — they were written with the order, before the charge.
    $o->payment_id = $payment->id;
    $o->status = ProductOrder::STATUS_PAID;
    $o->paid_at = now();
    $o->save();
    return [...];
});
// email only if this call did the work
```

Plus `reconcileSucceeded($intentId, $orderId, $amount)` with the same signature
shape as the registration one, for the webhook to call.

## Webhook

`StripeWebhookController` needs three edits:

1. `payment_intent.succeeded` — after the registration reconcile, also check
   `$intent->metadata->product_order_id` and call the product fulfiller. Both
   lookups tolerate a null pointer and fall back to
   `where('stripe_payment_intent_id', ...)`.
2. `payment_intent.payment_failed` — mark the order `failed`, same as pending
   registrations.
3. `checkout.session.completed` — currently logged and ignored with the comment
   "nothing in the portal creates Checkout Sessions." That stops being true.
   Handle it as a *secondary* backstop: look up
   `metadata.product_order_id`, and ignore anything else (still 200). The
   comment must be updated or it will mislead the next reader.

Response codes are unchanged: 4xx only for a failed signature check.

## Reconcile sweep — ship it this time

`payment-flow-pattern.md` says every new flow should ship the sweep that
neither existing flow has. Build it here as
`php artisan store:reconcile-orders`, scheduled every 15 minutes:

1. `product_orders` still `pending` and older than ~15 minutes that actually
   reached Stripe (`ProductOrder::stalePending()`),
2. ask Stripe (on that order's account) what happened to the intent or session,
3. fulfill or fail.

Write it against an interface (`reference`, `stripe_account`, intent/session id,
status) so `pending_event_registrations` can be swept by the same command later.
That is the cheapest path to closing the gap for events too.

## Pickup at a school

- `pickup_school_id` on the order, from `schools`.
- Members: default to `auth()->user()->school_id`, still changeable — a family
  may collect at a relative's school.
- Guests: required select on the checkout page, before the Checkout redirect,
  so the choice is in our DB rather than in Stripe custom fields.
- Admin needs a **pick list**: orders grouped by school, then by variant, with a
  collected checkbox. That view is the actual operational deliverable of this
  feature — printing it is how the shirts get handed out.
- Shipping later drops in as `fulfillment_method` (`pickup` | `ship`) plus an
  address block and a `shipping` amount on the order; nothing above changes.

## Home page highlighting

Mirror events exactly:

```php
Product::HOMEPAGE_LIMIT = 2;   // events use 3
Product::forHomepage()          // highlighted first by highlight_order desc, then sort_order
```

Ordered `highlight_order` **descending** so the default 0 stays the resting
baseline — same reasoning as `Event::forHomepage()`. Difference from events:
products have no start date, so featuring nothing shows **nothing** rather than
falling back to "soonest". A store section that appears unbidden on the home
page is a surprise; opt-in is right here. `GeneralController::dashboard()` gets
`$featured_products` alongside `$next_events`, and `dashboard.blade.php` gets a
store row that renders only when the collection is non-empty.

A product is only eligible if `status === 'active'` and the order window is
open.

## Admin

Routes mirror `admin/events` (`admin/products`, create/edit/update/destroy/
restore + `media` delete and focus). Permission: a new `store.manage`, granted
to the same roles that hold event admin, so the store isn't gated on
`manage-users`.

Screens:

1. Products index (livewire table, with Featured badge like the events index).
2. Product form: details, Stripe account select (locked once money moves),
   order window, highlight toggle + order, images, and a variants repeater
   (option values, price, enabled, sort).
3. Orders list: filter by product, school, status. Row → order detail.
4. Pick list by school (printable).
5. Financials export for the store: one row per order, dollars by product, plus
   refunds — the same shape as `FinancialsExport` so the two reconcile the same
   way, and it must print which Stripe account the money is in.

## Refunds

v1: refund from the order detail screen using
`StripeAccounts::secret($order->stripe_account)`, and record
`refunded_amount` + `stripe_refund_id` on the order.

Do **not** extend the `refunds` table yet — it is event-scoped
(`event_id` non-nullable) and wired to `AddonChangeRequest`. Generalizing it is
a separate change; making it nullable in passing would put an untested branch
under the event refund flow, which is live money.

## Open questions

- **Sales tax.** A nonprofit selling merchandise usually still collects sales
  tax on it. If yes, this needs Stripe Tax (a per-account setting) or a fixed
  rate on the product, decided before the first sale — it is painful to add
  after money has moved. Not a code question; needs an answer from whoever
  handles the association's filings.
- **Which Stripe account** this first run charges to.
- **Order window close date**, and the pickup date/wording for `pickup_note`.
- Whether members should see the same page as guests (one public page with a
  logged-in fast path) or a separate one. One page is less code; the design
  above assumes it.

## Progress

- **Step 1 done (2026-08-31, branch `feature/store`):** the four migrations,
  the `payments` change, `Product` / `ProductVariant` / `ProductOrder` /
  `ProductOrderItem`, the `ChargedToStripeAccount` interface with
  `StripeAccounts::for()` / `secretFor()` / `publishableKeyFor()` (the
  `*ForEvent` methods are now wrappers), and Pest coverage in
  `tests/Feature/Store`.
- **Verified 2026-08-31:** migrations applied to the dev and test databases,
  `tests/Feature/Store` green (17 tests), full suite green (152 tests) — so the
  `StripeAccounts` generalization did not disturb the event payment flow.
- **Step 2 done (2026-08-31):** admin CRUD. `ProductAdminController` (mirroring
  `EventAdminController`, including the three-layer Stripe account lock),
  `ProductRequest`, `ProductVariantSync`, the `App\Livewire\Admin\ProductsTable`
  index, the product form with a variants repeater, product images on the shared
  focal-point picker, the `store.manage` permission, and
  `tests/Feature/Store/ProductAdminTest` (16 tests). Nothing public yet.

  Two decisions made while building it:

  - **Option axes are a comma-separated field**, not a second repeater. They are
    set once when the product is created; a repeater for three words was more UI
    than the job needed. The variant rows re-label themselves live as the axes
    are edited. `ProductVariantSync` drops submitted option values that aren't
    one of the declared axes, so renaming an axis doesn't leave an orphan key on
    every variant.
  - **An axis is only what the buyer picks.** For the first run every item comes
    in exactly one colour (tees and adult hoodies dark gray, youth hoodies a
    lighter grey, the bag dark gray/black), so colour is description text, not an
    axis — an axis with one value is a dropdown with one entry. The real axes
    are **Item** and **Size**.
  - **Quick add generates the size rows.** A row per size is what the schema
    wants — price lives on the row and orders point at a specific row so the pick
    list can group by it — but the first run is 19 rows and nobody should type
    those by hand. The editor takes a value per axis, expands whatever is
    comma-separated, and skips combinations already listed. A `+n` suffix on any
    option value adds to that row's price, which is how the 2XL/3XL upcharges are
    entered: `Size: XS, S, M, L, XL, 2XL+2, 3XL+3`.

    It expands what you type rather than multiplying all axes blindly, because
    the size sets differ per item (adult 7, youth 4, the bag none) and a blind
    cross-product would invent rows that don't exist. The whole 2026 catalogue is
    four Quick add batches plus one hand-added row for the bag — 23 variants.

- **Step 2a (2026-08-31): print runs.** A design is printed more than once, so
  the ordering window moved off `products` onto a new `product_runs` table, and
  variants moved with it. `ProductRun`, `ProductRunController`,
  `ProductRunRequest` (with the one-open-run-at-a-time rule),
  `ProductVariantSync::copy`, and `tests/Feature/Store/ProductRunTest`.

  Runs get their own pages (`/admin/products/{product}/runs/{run}/edit`) rather
  than nesting inside the product form — a run owns a whole price list, and
  several of those on one screen would be unreadable. The product form lists the
  runs with their state (Open / Scheduled / Closed) and links out.

  The migrations were edited in place rather than adding a "drop the columns
  again" pair, since nothing was deployed. That meant surgically unwinding the
  five store migrations on both databases — batch 53 also held the committed
  events-highlight migration, so a plain `migrate:rollback` would have dropped
  live event data.

- **Step 3a done (2026-08-31): the public storefront and cart.** `/store`,
  `/store/{slug}`, and a session cart — `StoreController`,
  `App\Services\Store\{Cart,CartLine}`, `resources/views/store/`, and
  `tests/Feature/Store/StorefrontTest` (20 tests). **Checkout is not built yet.**

  Three things worth knowing:

  - **These are the portal's first public pages.** Every other member-facing
    route is behind `auth`+`verified`; the store routes sit deliberately outside
    that group (`routes/web.php`, above the group) because the store sells to
    guests. `layouts.dashboard` already degrades for signed-out visitors
    (`@if(Auth::user())` around the avatar, `@can` throughout the nav) and
    `/glide/public/{path}` was already public, so images needed nothing.
  - **The cart lives in the session, not in a `pending` order row.** Guests have
    no user to hang a cart on, and a pending `ProductOrder` written at
    add-to-cart time would be picked up by `scopeStalePending`, leaving the
    reconcile sweep interrogating Stripe about abandoned carts. The order row is
    written at checkout, immediately before the charge. Nothing else in this
    portal keeps session state between requests, so `Cart` sets that convention.
  - **The cart stores only `[variant_id => qty]`.** Prices and names are read
    live from the variant on every request, so a cart left open across a price
    edit shows and charges the new price; the snapshot happens on
    `product_order_items` when the order is written. Lines whose run has closed,
    or whose product was archived, are dropped on read rather than carrying a
    price that can no longer be honoured.

  The cart enforces the **one cart, one Stripe account** rule at add-to-cart
  time, and `max_per_order` across all of a product's variants.

  The public product page cascades its selects down the option axes: choose
  Item, and only the sizes that exist for that item are offered. That is what
  keeps a 23-variant design usable, and it means the differing size sets (adult
  7, youth 4, the bag none) never show the buyer a combination that wasn't
  printed.

- **Step 4a done (2026-08-31): the money backbone.**
  `App\Services\Store\ProductOrderFulfiller`, the `payment_intent.succeeded` /
  `payment_intent.payment_failed` / `checkout.session.completed` handling in
  `StripeWebhookController`, the `ProductOrderPlaced` confirmation, and
  `tests/Feature/Store/ProductOrderFulfillerTest` (14 tests). Built before any
  checkout UI, deliberately: it is the part that must be right.

  It is a near-copy of `RegistrationFulfiller`, as this document instructed —
  row lock plus status check inside the transaction, `Payment::firstOrCreate`
  keyed on the intent id, the transaction returning data so `fulfill()` can
  report whether *this* call did the work, and the email sent outside the
  transaction only by that call. Tests assert one payment row and one email when
  the synchronous path and the webhook both run.

  `markFailed()` will not downgrade an order that already paid — a late
  `payment_failed` for an intent that did succeed must not undo fulfillment.

  The webhook now runs **both** fulfillers for every `payment_intent.succeeded`.
  Each tolerates a null pointer, falls back to a lookup by intent id, and is
  idempotent, so at most one finds a row and neither can be upset by the other's
  traffic. Response codes are unchanged: 4xx only for a failed signature.

  **Sales tax: undecided, so `product_orders.tax` ships at zero** (decided with
  Mike, 2026-08-31). Adding the column later would mean altering a table holding
  financial records; adding it now, before anything is deployed, makes the answer
  a configuration change instead. `total = subtotal + tax`.

- **Step 4b done (2026-08-31): checkout, both doors, and the sweep.**
  `StoreCheckoutController`, `App\Services\Store\OrderBuilder`,
  `App\Console\Commands\ReconcileStoreOrders`, `resources/views/store/
  {checkout,complete}.blade.php`, and `tests/Feature/Store/{CheckoutTest,
  ReconcileStoreOrdersTest}` (23 tests).

  `OrderBuilder` writes the order **and its line items** from the cart before
  anything reaches Stripe, so the row is self-describing while still pending.
  Prices are snapshotted onto the items at that moment — a test asserts that
  editing a variant's price afterwards does not rewrite the order.

  Members: PaymentIntent confirmed server-side with `metadata.product_order_id`,
  the intent id saved immediately so the webhook can find the row even if the
  response is lost, `requires_action` → browser `confirmCardPayment` →
  `finalize`, which **re-fetches the intent from Stripe** and never trusts the
  browser's claim.

  Guests: Hosted Checkout, with the pointer in **both** `metadata` and
  `payment_intent_data.metadata` — a session's own metadata does not reach the
  PaymentIntent it creates, and the webhook backstop keys on
  `payment_intent.succeeded`. `success_url` fulfils synchronously; the webhook is
  the backstop, not the mechanism.

  The completion page tells a still-pending buyer that confirmation is in
  progress and **not** to pay again, rather than reporting a failure the webhook
  or sweep is about to resolve.

  `store:reconcile-orders` runs every 15 minutes (`routes/console.php`). It only
  looks at pending orders that actually reached Stripe — `stalePending()` requires
  an intent or session id — so abandoned carts are never interrogated. It survives
  a per-account Stripe failure and carries on, has `--dry-run`, and a test asserts
  it is actually on the schedule, because an unscheduled sweep is worthless.

### Still to build

Steps 6–8 of the build order: home page highlighting, the pick list + orders
admin + financials export, and refunds. Nothing below is required to take money.

**Local `.env` warning:** `STRIPE_MAIN_SCHOOL_SECRET` is a **live** key while the
association's is `sk_test_`. Anything that charges `main_school` from a developer
machine moves real money. Test against `association` only.
  - **An ordered variant can be disabled but not deleted.** Order items snapshot
    the name and price, so history would survive — but `product_variant_id` is
    what the pick list groups by, and removing it would leave whoever is handing
    out shirts with nothing to sort on. `ProductVariantSync::sync()` returns what
    it refused to delete and the controller says so in the flash message, the
    same shape as `PotluckCatalog`.

  The focal-point picker's Alpine component moved from
  `partials/event/slideshow-focus` into `partials/focus-picker-script` so both
  pickers share it; crop-preview ratios are now passed in per caller.
  `tests/Feature/FocusPickerRenderTest` renders both admin forms with an image
  attached, which nothing covered before.

## Build order

1. Migrations + models + `StripeAccounts` generalization.
2. Admin CRUD (products, variants, images) — nothing public yet.
3. Public product page + cart + member checkout (Elements).
4. Fulfiller + webhook edits + guest Checkout.
5. Reconcile sweep command + schedule.
6. Home page highlighting.
7. Pick list + orders admin + financials export.
8. Refunds.

Steps 1–4 are the sellable minimum; 5 ships with them, not after.
