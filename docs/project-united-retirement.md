# Retiring Project United

Project United (donations, t-shirts, hoodies) is finished. This is what it
touches and the order to unwind it in. Nothing here is urgent — the safe first
phase is small and reversible.

**Keep the data forever.** `project_united_transactions` and the exports that
read it are financial records. Do not drop the table or delete the export
classes, whatever else happens.

## Phase 1 — stop taking money (reversible, do this first)

Remove or comment the four POST routes in `routes/web.php`:

- line 40 `/project-united/donate`
- line 43 `/project-united/tshirt`
- line 44 `/project-united/hoodie`

(there is no separate hoodie success/cancel route; hoodies reuse the tshirt ones)

Leave the GET routes alone for now so existing links and any in-flight
`success_url` / `cancel_url` redirects still land somewhere sensible.

Leave the webhook branch alone too. Stripe Checkout Sessions stay open for up to
24 hours, so a session created just before the cutover can still complete. Wait
at least a day past the last possible purchase before touching
`StripeWebhookController`.

## Phase 2 — remove the entry points

Once no one can start a purchase:

- `resources/views/dashboard.blade.php` lines ~53-61 — the Project United card
- `resources/views/auth/login.blade.php` lines ~58-60 — the login-page promo

That is what actually makes it "gone" for users. Everything after this is
housekeeping.

## Phase 3 — remove the code

Only after the webhook has been quiet for a while.

**`app/Http/Controllers/StripeWebhookController.php`** — drop the five
`project_united_*` cases from the `checkout.session.completed` switch. Since the
`default:` branch now logs and returns 200, removing the cases makes stray PU
sessions harmless rather than fatal. If nothing else in the portal creates
Checkout Sessions at that point, the whole `checkout.session.completed` branch
can go, leaving the two `payment_intent.*` branches.

**Purchase-side code, safe to delete:**

- `app/Jobs/ProcessProjectUnitedPayment.php`
- `app/Mail/ProjectUnitedReceipt.php`
- `resources/views/emails/project-united/{donation,tshirt,hoodie}_receipt.blade.php`
- `resources/js/Pages/General/ProjectUnited/{Index,DonationSuccess,DonationCancel,TshirtOrder,TshirtSuccess,TshirtCancel}.vue`
- `ProjectUnitedController` methods: `index`, `processDonation`, `donationSuccess`,
  `donationCancel`, `processTshirt`, `processHoodie`, `tshirtSuccess`, `tshirtCancel`
- the image assets under `public/img/2025/project_united/` and
  `public/img/2026/project_united/`

**Reporting-side code, KEEP:**

- `app/Models/ProjectUnitedTransaction.php`
- `database/migrations/2025_03_27_194229_create_project_united_transactions_table.php`
- `app/Exports/ProjectUnited/{FinalExport,FinalExportBySchool,FinalExportMailing}.php`
- `resources/views/general/project-united/exports/final-export-by-school.blade.php`
- `resources/js/Pages/General/ProjectUnited/Report.vue`
- `ProjectUnitedController::report()` and `::finalReport()`, and their routes at
  `routes/web.php` lines 189-190 (admin-gated)

If you want the controller to shrink to just the two report methods, rename it
to something like `ProjectUnitedReportController` in the same pass and update
those two routes.

## Already dead

`GeneralController::projectUnited()` (line 38) renders
`General/ProjectUnited` — a Vue page path that does not exist (the real one is
`General/ProjectUnited/Index`), and no route points at this method. It is
orphaned regardless of this retirement; delete it whenever convenient.

## Note for next time

Project United is the "webhook-only fulfillment" pattern that
`docs/payment-flow-pattern.md` warns against. If a similar one-off store or
donation drive comes up, start from that document, not from this code.
