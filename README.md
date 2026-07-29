# Chung Do Association Portal

A production web application for running a multi-school martial arts association:
member management, tournament registration, division seeding, online payments,
and mobile check-in. Built with Laravel and an Inertia + Vue single-page
frontend.

> This repository is shared for portfolio/evaluation purposes. See [LICENSE](LICENSE).

---

## What it does

**Members & schools**
- Household accounts: a "responsible user" manages themselves plus family members
  (children), each with their own belt rank, school, and profile.
- Belt-rank tracking and rank history across multiple affiliated schools.
- Role-based access control (super-admin, event admins, instructors) via
  Spatie Permissions; admin impersonation for support.

**Tournaments & events**
- Event registration with configurable divisions for **sparring**, **forms**,
  and **combined** tournaments; automatic division assignment by age, rank,
  weight, and sex, with versioned division sets that can be published/reorganized.
- Paid add-ons (meals, apparel) with an admin-approved **refund request** flow,
  a refund ledger, and a per-event **financials export**.
- Registration cards generated as print-ready PDFs (Typst / pdftk).

**Payments**
- Stripe integration via Laravel Cashier, including 3-D Secure (SCA) handling
  and webhook signature verification.

**Check-in & mobile**
- Event tickets as **Apple Wallet** and **Google Wallet** passes.
- QR-code / barcode scanning for on-site check-in.

**Identity & integrations**
- Acts as an **OpenID Connect provider** (Laravel Passport) so members can SSO
  into external services (e.g. a Zulip chat community) with belt-rank and group
  claims mapped into the token.
- Bulk email campaigns via SendPortal; CSV/Excel import & export.

---

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | Inertia.js + Vue 3, Vite, Tailwind CSS, Alpine.js |
| Auth | Laravel Fortify (2FA), Sanctum, Passport (OAuth2 / OIDC) |
| Payments | Stripe + Laravel Cashier |
| Data | MariaDB / MySQL, Eloquent |
| PDF / passes | Typst, pdftk, Apple PKPass, Google Wallet API |
| Media | Spatie Media Library, Glide image transforms |
| Tables/UI | Livewire tables, Tabler UI |
| Testing | Pest |
| Local dev | DDEV (Docker) |

---

## Local development

This project uses [DDEV](https://ddev.readthedocs.io/) for a containerized dev
environment (PHP 8.3, nginx, MariaDB).

```bash
# 1. Clone and enter the project
git clone <repo-url> chungdo && cd chungdo

# 2. Environment
cp .env.example .env
ddev start
ddev composer install
ddev npm install

# 3. App key, database, assets
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
ddev npm run dev            # Vite dev server

# 4. Open it
ddev launch
```

Seeded demo accounts use `@example.com` addresses with the password `password`.

### Configuration notes

Several features are **off until configured** and degrade gracefully:

- **Stripe** — set `STRIPE_KEY` / `STRIPE_SECRET` (+ webhook secret) in `.env`.
- **Wallet passes** — Apple and Google Wallet buttons only appear when their
  respective credentials are present. See `config/wallet.php` and the
  `WALLET_*` keys in `.env.example`. Signing material is injected via
  base64/env and is never committed.
- **Refund notifications** — `EVENT_REFUND_NOTIFY_EMAILS` (comma-separated).
- **PDF cards** — requires the `typst` binary (`TYPST_BIN`).

---

## Testing

```bash
ddev exec ./vendor/bin/pest              # full suite
ddev exec ./vendor/bin/pest --filter=Addon
```

Tests run against the containerized MariaDB and use MySQL-specific schema
features, so Docker/DDEV must be running.

---

## Project layout

```
app/
  Http/Controllers/   Event, payment, admin, and OIDC controllers
  Services/           Domain logic (division seeding, add-on adjustments, …)
  Models/             Eloquent models (User, Event, Registration, School, …)
  Entities/           OpenID Connect identity/claim mapping
config/               App + feature config (wallet, events, …)
database/             Migrations, seeders (demo data)
resources/js/         Inertia + Vue pages and components
routes/               web.php, api.php
tests/                Pest feature/unit tests
```

---

## License

Proprietary — © 2026 Mike Lester, all rights reserved. See [LICENSE](LICENSE).
Bundled third-party packages retain their original licenses.
