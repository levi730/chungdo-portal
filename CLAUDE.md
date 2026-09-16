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

**The whole flow is proven against a real Stripe test charge** (2026-08-31): a
two-line $75 order, one payment row, one confirmation email, and replaying the
webhook three ways changed nothing.

Deployed and live. `STORE_MENU=false` currently hides the Store nav item while
it is being set up — the menu only; `/store` is still reachable by URL and
`/admin/products` still works.

Decided: charges go to the **association** account, and **tax stays at 0**
(2026-09-01). `product_orders.tax` exists and `total = subtotal + tax`, so
turning it on is a config change rather than a migration over rows holding
money. That decision has **not** been checked with whoever handles the
association's filings — it means "not yet", not "not owed".

Remaining build-order work: the pick list, the orders admin and the financials
export, then refunds. The pick list is the operational one — it is how the
shirts actually get handed out.

## Groups and permissions

Committees are three things at once: a published directory, a Zulip group (via
the slug), and — since this change — a **holder of portal roles**.

- **Membership is the grant.** `committee_role` maps a committee to Spatie
  roles; `User::committeePermissionNames()` flattens those to permission names
  and the `Gate::before` hook in `AppServiceProvider` answers `can()` with them.
  Nothing is written onto the user, so dropping someone from a committee drops
  the access in the same act — there is no sync and nothing to reconcile.
- **That hook must return `null` on a miss, never `false`.** A `before()` hook
  returning false denies outright and short-circuits every role and policy check
  behind it.
- **Authorize on permissions, not role names.** A committee confers permissions
  but not the role *name*, so `hasRole('event.admin')` is false for an event
  admin by committee. `UserNotePolicy` was the one place doing this and now
  checks `event.manage`.
- The Events Committee (`events-committee`) holds `event.admin`. The 16 people
  holding that role directly were **left alone** — both paths work, so who is an
  event admin currently has two sources of truth.

**The coordinator is config, not a role.** It is the top of the organisation
chart and deliberately not the top of the technical one — it does *not* get
super.admin's `Gate::before` bypass, which is what hands out every future
ability automatically.

- **Who** holds it: `portal.coordinator_user_id` (`COORDINATOR_USER_ID` in
  `.env`), a user id rather than an email so a changed address cannot silently
  detach the position. **What** it may do: `portal.coordinator_permissions`, an
  explicit allowlist. Both in `config/portal.php`; `App\Services\Coordinator`
  reads them and the `Gate::before` hook grants from there.
- **There is no `model_has_roles` row, and the `coordinator` role is kept
  permanently EMPTY.** That is the whole point and it is easy to undo by
  accident: Spatie's own `Gate::before` honours a role's permissions directly,
  so the moment that role has grants again, anyone who can write a
  model_has_roles row is coordinator without config saying so. The seeder
  declares it with `'permissions' => []`; leave it that way.
- Nothing can hand it out — not the admin user form, not a committee, not a
  super.admin. `RoleAssignment::NEVER_ASSIGNABLE` withholds it from every
  surface. Moving the position means editing config and deploying.
- The allowlist costs vigilance by design: add a permission and forget
  `coordinator_permissions`, and the coordinator quietly cannot do the new
  thing. Add it to the list; do not widen the list to a wildcard.
- `manage-users` is still the ability name the routes and nav check, but it is
  now backed by a real `users.manage` permission so a role can hold it.
- **Only a super.admin may grant or revoke `super.admin`, and no committee may
  confer it** — `App\Services\RoleAssignment`. Without that guard, giving the
  coordinator user administration is a one-click escalation to the technical
  tier. `UserController::syncRoles()` also carries over roles the editor was
  never shown, so a coordinator saving a super.admin's account does not strip it.
- The coordinator sits in **every committee's Zulip group** without being on any
  roster (`ZulipGroupResolver::coordinatorGroups()`). Reach, not membership. The
  sync removes as well as adds, so changing the config id moves the groups with
  it on the next sync, including removing the previous holder.
- Master David Blevins (user 38) holds it. His `event.admin` is now redundant
  but was left in place. The user edit page shows the position as a read-only
  notice, since it appears on no checkbox.

Needs `php artisan db:seed --class=PermissionSeeder` to exist anywhere — it has
been run on the local dev and test databases only. The seeder still reports
`cms.html`, `cms.manage` and `school.payments` as unmanaged: they are in no role,
on no user, and referenced nowhere in code.

## Member notes

Notes about a member are `user_notes` (`App\Models\UserNote`, `User::notes()`).
The table was `user_event_notes` and the relation `event_notes`; they are notes
about a *person*, and the rename is what makes room for other note subjects
later. Nothing is polymorphic yet — if one arrives it is a `notable` on the
subject, not on the event.

- **A note is permanent or temporary, and `scope` says which.** Permanent
  describes the person and shows everywhere ("hard of hearing"); temporary
  belongs to one event and must not outlive it ("cannot stay for finals").
  `scope` is explicit rather than inferred from `event_id`, because a permanent
  note still records the event it was written at.
- **`UserNote::visibleForEvent()` is the only filtering rule** — everything
  permanent, plus the temporary notes for that event. Apply it in the eager
  load. `RegistrationCardPdf` reads the *loaded* relation, so a card loader that
  skips it prints another event's temporary notes; `eventNotes()` there is the
  shared constraint all three loaders pass through.
- The old form wrote no `event_id` at all, so every note predating this was
  null and the card's `where('event_id', …)` printed nothing. The migration made
  them all permanent (visible is the safe wrong guess); `php artisan
  notes:classify` walks them, `--dry-run` first.
- **Writing and changing are different rights.** Writing needs
  `event.viewAllSchoolRegistrants` — checked in `UserNoteController`, not in the
  route. Editing and deleting are the author or `event.admin`, in
  `UserNotePolicy`; `super.admin` arrives through the `Gate::before` hook in
  `AppServiceProvider` and is not repeated in the policy.
- The modal is rendered per registrant from `event/registrants.blade.php` and
  needs `$event`. It is the only place notes appear — not check-in, and there is
  no member-facing view of them.

## Event map snapshots

Event cards show a **cached still image** of the venue and only load the live
Google map when clicked. The dashboard previously embedded three 600x450 iframes
on every visit — 1,350px of page and three third-party requests before anyone
asked to see a map.

- `App\Services\EventMapSnapshot` reads the coordinates out of the embed URL
  already stored in `events.map_url` (its `pb` parameter carries
  `!2d<lng>!3d<lat>`), fetches one Static Maps image, and stores it on the
  `public` disk keyed by `md5(map_url)`.
- The fetch happens on `Event::created` and on `updated` **only when `map_url`
  changed**, so a venue costs one API call ever, not one per page view. Use
  separate hooks, not `saved`: `performInsert()` never calls `syncChanges()` so
  `wasChanged()` is false on create, while `wasRecentlyCreated` never clears and
  re-fires on every later save of the same instance.
- `php artisan events:cache-maps` backfills existing events.
- Needs `GOOGLE_MAPS_KEY`. Without it nothing breaks — cards fall back to the
  address. The key is used **server-side**, so restrict it by IP, not HTTP
  referrer, and set a quota cap.

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
