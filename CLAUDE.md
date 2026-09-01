# chungdo portal

Laravel 12 portal for the Chung Do Association: membership, event/tournament
registration, payments, mobile check-in. Blade + Tabler for most screens,
Livewire tables for admin lists, some Inertia/Vue (the registration form).

## Read these before touching money

- `docs/payment-flow-pattern.md` — **the** rule for taking payments here. Record
  a pending row before charging, put only a pointer in Stripe metadata, fulfill
  from both the synchronous return and the webhook through one idempotent
  fulfiller. Copy `App\Services\RegistrationFulfiller`; do not copy the retired
  Project United flow.
- `docs/store-design.md` — the merchandise store (selling outside event
  registration). Build-vs-buy reasoning, schema, and build order.
- `docs/project-united-retirement.md` — what is being removed and what must be
  kept (the transactions, model, migration, exports and report routes are
  financial records).
- `docs/zulip-13-oidc-sync.md` — Zulip/OIDC sync.

## Things that are easy to get wrong

- **Two Stripe accounts.** Credentials come only from
  `App\Services\Stripe\StripeAccounts`. Never read `config('services.stripe.secret')`
  for event or store money — that constant is the association's and would
  silently charge the wrong account. A model names its account through the
  `ChargedToStripeAccount` interface (`Event`, `Product`).
- **The local `.env` holds a LIVE key for `main_school`** (`sk_live_…`) while the
  association's is `sk_test_…`. Charging `main_school` from a dev machine moves
  real money. Test against `association` only, and check the prefix before
  running anything that charges.
- The account is **locked once money has moved** for an event or product —
  refunds must be issued on the account that took the charge.
- Stripe customer ids do not cross accounts. Cashier's `users.stripe_id` is only
  valid on the association's account; use `StripeCustomerResolver`.
- The publishable key is passed from the server per account, never baked in from
  `VITE_STRIPE_KEY`.
- `StripeWebhookController` returns 4xx **only** for a failed signature check.
  Recognized-but-not-ours events get a 200 — Stripe retries non-2xx for days and
  disables endpoints with sustained failures.
- One webhook URL (`/api/stripe/webhook`) serves every account; the controller
  tries each configured signing secret until one verifies.
- Neither existing payment flow has a reconcile sweep. Any new flow ships one.
- The Zulip sync treats the portal as canonical and **removes** as well as adds.
  Dry-run first.

## Store — work in progress

Branch `feature/store`. Step 1 of the build order in `docs/store-design.md` is
done and **verified against a database** (2026-08-31): the four migrations, the
`payments` change (user_id nullable + product_order_id), the `Product` /
`ProductVariant` / `ProductOrder` / `ProductOrderItem` models, the
`ChargedToStripeAccount` generalization of `StripeAccounts`, and
`tests/Feature/Store` (17 tests). Full suite green at 152.

Step 2 (admin CRUD) is also done and verified: `ProductAdminController`,
`ProductRunController`, `ProductRequest`, `ProductRunRequest`,
`ProductVariantSync`, `App\Livewire\Admin\ProductsTable`, the product form, the
per-run price-list editor, and `tests/Feature/Store/{ProductAdminTest,
ProductRunTest}`. Nothing public yet — next is step 3, the public product page
and cart.

**A product is the design; a `product_run` is one printing of it.** The ordering
window, the expected arrival, the pickup note and the *variants* all belong to a
run, not the product — prices move between runs and each run keeps the list it
sold at. Only one run of a design may be open at a time. `product_order_items`
carries `product_run_id` on the **line**, because a cart can span two products
whose open runs have different arrival dates.

Step 3a (public storefront + cart) is done: `/store`, `/store/{slug}`,
`StoreController`, `App\Services\Store\Cart`, `tests/Feature/Store/StorefrontTest`.
**Checkout is not built** — that is the next piece, and it needs the sales-tax
answer first.

- **The store routes are the portal's only public pages.** They sit outside the
  `auth`+`verified` group on purpose. Adding anything there exposes it to
  signed-out visitors — check before you put a route above that group.
- **The cart is session state, keyed `[variant_id => qty]`.** Nothing else in
  this portal keeps session state between requests. Do not write a `pending`
  `ProductOrder` at add-to-cart time: `scopeStalePending` would hand abandoned
  carts to the reconcile sweep. The order row belongs at checkout, right before
  the charge.

**The store is gated on a new `store.manage` permission.** It is in
`PermissionSeeder` and granted to the `event.admin` role, but a permission only
exists once the seeder runs — `php artisan db:seed --class=PermissionSeeder`.
Without that, the store admin is invisible and 403s for everyone. It has been
run on the local dev database, not anywhere else.

Steps 3–5 are done: the public storefront, the cart, both checkout doors, the
idempotent `ProductOrderFulfiller`, the store branches of
`StripeWebhookController`, and `store:reconcile-orders` (scheduled every 15
minutes). The store can take money. Remaining: home page highlighting, the pick
list / orders admin / financials export, and refunds.

**No end-to-end Stripe purchase has been made yet.** Everything up to the Stripe
boundary is tested, and the member checkout page renders with Elements mounted
against the association test account, but no card has been run through it.

Answered 2026-08-31: this first run charges to the **association** account, and
**sales tax is undecided** — `product_orders.tax` exists and is always 0, so the
answer stays a config change rather than a migration over financial records.
Still open: the order-window close date and the pickup wording (both live on the
run now, not the product).

## Testing

Pest, `tests/Feature` uses `DatabaseTransactions` against MySQL (`phpunit.xml`).

Everything runs inside DDEV — `ddev exec php artisan …`, `ddev exec ./vendor/bin/pest`.
From the host, `DB_HOST=db` does not resolve.

Because the suite uses `DatabaseTransactions` rather than `RefreshDatabase`, the
test database must already be migrated, and it is a **different database** from
the dev one: `phpunit.xml` overrides `DB_DATABASE` to `laravel`. A new migration
has to be run against both, or the failure looks like a missing table/column in
code that is actually fine:

```
ddev exec php artisan migrate
ddev exec env DB_DATABASE=laravel php artisan migrate
```
