# Pinikitan Rental — Tenant Management System

A Laravel 12 app for running a rental property: tenants apply for units, pay
rent/deposits/utilities and file maintenance and renovation requests; the manager
issues bills, records payments, tracks arrears and repair costs, and runs reports.

Utility charges are tracked as individual bills that can be paid in parts, so a
tenant can be "rent paid, utilities partial" rather than all-or-nothing. See
[`docs/BILLING-AND-COLLECTIONS.md`](docs/BILLING-AND-COLLECTIONS.md).

Reports cover active tenants, payment history, leases, maintenance, **lease
renewals** and **renovation requests** — the last two added in v14. See
[`CHANGES-v14.md`](CHANGES-v14.md).

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install && npm run dev
php artisan serve
```

For the scheduled jobs (tenant status, 5% lease renewals) to run, add the cron
entry on the server:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Check what's registered with `php artisan schedule:list`.

## Finding your way around

**Read [`docs/PROJECT-MAP.md`](docs/PROJECT-MAP.md) first.** It has:

- a directory map
- a "where to look when X breaks" table
- the full route → controller → view list
- the column-name traps that cause silent, no-error bugs
- the places where logic is duplicated and can drift apart

For anything touching money, arrears or repair costs, read
[`docs/BILLING-AND-COLLECTIONS.md`](docs/BILLING-AND-COLLECTIONS.md) as well.

## Tests

```bash
php artisan test
php artisan test --filter=BillingTest
```

Covers the billing QA cases (exact / under / over payment, zero amounts, late
bills, estimate reconciliation, proration rounding), the collections stages, the
maintenance cost rules and the renovation deposit gate.

## Health check

```bash
php artisan app:audit           # broken routes, missing methods, missing views
php artisan app:audit --views   # also scan Blade files
```

Run it after touching routes or renaming anything. It exits 1 on problems, so it
can go in a deploy script.

## Change history

- [`CHANGES-v13.md`](CHANGES-v13.md) — current release
- [`CHANGES-v12.md`](CHANGES-v12.md) — previous release
- [`CHANGES-v11.md`](CHANGES-v11.md)

## Before going live

- Delete `routes/web/dev.php` and its `require` in `routes/web.php` — those
  endpoints have no authentication.
- Move the PhilSMS API token out of `routes/console.php` and into `.env`.
- Set `APP_DEBUG=false` and a real `APP_KEY` in `.env`.
- **Have a lawyer sign off `config/collections.php`.** The notice periods there
  are placeholders. There is a sign-off line in the file header — fill it in.
- Confirm the billing rules in `config/billing.php` match how you actually want
  shared bills split and rounded. They are easy to change now and a data
  migration later.
