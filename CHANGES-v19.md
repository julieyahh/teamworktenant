# v19 — Compact tenant dashboard · Payment days 1st–10th · One bill per month · Estimates not payable · Tenant-requested renewal · Invoices

## Installing over your current project

```bash
# from your existing project root
cp -r /path/to/tenants-v19/{app,config,database,docs,resources,routes,tests} .
cp /path/to/tenants-v19/CHANGES-v19.md .
php artisan migrate            # 1 new migration, additive
php artisan config:clear && php artisan view:clear && php artisan route:clear
php artisan test               # expect 137 passing
php artisan rent:charge --dry-run   # see the first calendar-aligned charges before they happen
```

**Add to `.env` before going live:**

```
APP_TIMEZONE=Asia/Manila          # IMPORTANT — see note below
INVOICE_BUSINESS_NAME="Pinikitan Rental"
INVOICE_ADDRESS="..."
INVOICE_PHONE=09XXXXXXXXX
INVOICE_EMAIL=office@yourdomain.com
INVOICE_TIN=000-000-000-000
```

**Timezone.** The app was on UTC. With rent raised on the 1st and penalties
from the 11th, "today" has to be the Philippine date, otherwise the nightly
run decides the date 8 hours late. `config/app.php` now reads
`APP_TIMEZONE`; set it to `Asia/Manila`. (It is left defaulting to UTC so the
upgrade alone changes nothing until you choose to.)

---

## 1. Tenant dashboard — compact, like the manager's
`Tenant\TenantController@dashboard`, `resources/views/tenant/home.blade.php`

- **Header** with a payment-window badge: "Payment window open · pay by Oct 10
  (9 days left)", or "Next payment window: Nov 1–10".
- **Six tiles:** Rent due · Utilities due (+ any estimate shown separately) ·
  Overdue *or* Next due date · Paid in advance (or Deposit due, when one is
  owed) · Pending payments · Open requests.
- **What's Due** — every rent month, penalty, utility bill and deposit still
  owed, overdue first, each with its due date. Estimates are listed as
  "Not payable yet". When nothing is owed it shows the next rent charge.
  "Make a payment" button with the total payable now.
- **My Lease** — room, rent, start/end, and renewal / move-out status.
- **Recent** — Payments (with Invoice download) / Requests tabs.
- **Fits one screen** at 1366×768 and up; lists scroll inside their panels.
  Below 1200px wide or 700px tall it becomes a normal scrolling page.
- Read-only: the dashboard never writes a balance, charge or penalty.

## 2. Payment days — 1st to 10th, every month
`app/Support/RentCalendar.php` (new), `config/billing.php` → `payment_days`, `BillingService`, `Lease`

- Rent for each month is **raised on the 1st** and **due by the 10th**.
  The 10% late penalty applies **from the 11th**.
- **Move-in mid-month:** the first charge is a prorated part-month up to the
  1st (e.g. Oct 17–31 = 15/31 of the rent), then whole months.
- **Minimum notice:** nothing is due with less than 5 days' notice. Moving in
  on the 8th rolls the first due date to the next 10th, not two days later.
- **Existing leases** were on their own anniversary dates. Their next charge is
  one prorated part-month up to the 1st, then whole months — no gap, no
  overlap, no back-billing. Run `rent:charge --dry-run` first to see it.
- **Utility bills** default to the 10th of the month after the billing month
  (a September bill is due October 10). The manager can still type a date.
- Part-month charges carry a note and show as "Oct 17–31, 2026".
- Settings: `opens_on_day` (1), `due_on_day` (10), `min_notice_days` (5),
  `rent.prorate_partial_month` (true).

## 3. Bills — one per tenant, room, type and month
`BillingService::findDuplicateBill()`, `DuplicateBillException` (new), `UtilityBillController`

- Issuing a second Electricity/Water/Other bill for the **same tenant, same
  room, same month** is refused — **including when the first is an estimate**.
  The error links straight to the existing bill: correct an estimate by
  setting its final amount, not by issuing another bill.
- Billing month is now a month picker; "2026-09", "September 2026" and
  "Sep 2026" all count as the same month (older free-text rows included).
- **Allowed:** a different month, type, room, or tenant; a **voided** bill
  (void the wrong one, then issue the right one); **Repair** bills (two
  repairs in a month are two real charges).
- **Shared bills** are all-or-nothing: if any participant already has that
  month's bill, nobody is billed.
- Checked in the controller *and* in the service, so no other path skips it.
- Setting: `billing.one_bill_per_month_types`.

## 4. Tenant — estimated bills cannot be paid
`UtilityBillingProof::payable()`, `isPayable()`, `Tenant\PaymentController`, `tenant/payments.blade.php`

- The tenant **sees** an estimate (purple, "Not payable yet", "≈ ₱…") so they
  know it is coming, but cannot pay it until the manager sets the final amount.
- Server-side: a Utilities payment when only an estimate is open is refused
  with an explanation. No money is ever allocated to an unfinalised estimate.
- An estimate is **not** overdue, not arrears, not in the utility balance, not
  on the manager's Due Date Monitor, and not demandable in Collections (it is
  flagged as a blocker instead).
- Manager: the bill page button is now **"Set final amount & open for
  payment"**. The tenant is notified either way — including when the final
  amount equals the estimate.
- **Move-out** (normal and forced) is blocked while a lease has an
  unfinalised estimate, so it cannot be left behind unpaid.
- Pay form amounts now come from what is actually payable per type (all open
  bills, less what is paid), instead of the latest bill's full amount.

## 5. Manager — Renew is locked until the tenant requests it
`TenantLeaseController@requestRenewal / @cancelRenewal` (new), `ManagerController@renewLease`, `RenewLeases`, `config/leases.php` (new)

- **Tenant:** "Request Renewal" on Lease Management, from **60 days** before
  the end date (or any time after it). They can withdraw it. Managers are
  notified.
- **Manager:** on Tenant Management, "Renew Lease" shows locked
  ("not requested by tenant") until the request exists, then unlocks with a
  "Requested" tag and the request date. The server refuses a renewal without a
  request too.
- **Nightly `leases:renew`** now only renews leases the tenant asked for. A
  lease that reaches its end date without a request stays active at its
  current rent and shows under "Lease ends" on the manager dashboard.
- The request flag clears when the renewal is done.
- New column: `leases.renewal_requested_at`.

## 6. Tenant — invoice download for accepted payments
`App\Services\InvoiceService` (new), `resources/views/invoices/payment.blade.php` (new), `config/invoice.php` (new)

- Every **Accepted** payment has an **Invoice** button (Payments page and
  dashboard). Pending / rejected payments have none.
- PDF: business header, INV-2026-000123, PAID stamp, billed-to (name, email,
  phone, room), reference, method, account no., and line items built from
  what the payment actually paid:
  - Utilities → one line per bill it landed on (month, kWh, due date) plus
    any part that became credit
  - Advance Rent → arrears settled + months held ahead
  - Rent / Deposit → one line
- Footer: "not a BIR Official Receipt".
- Tenants can only open their own; managers can download any accepted one
  from Reports → Payment History.
- New column `payments.accepted_at` (the invoice date), set when a payment is
  accepted and cleared if it is un-accepted. Existing accepted payments are
  backfilled from `updated_at`.

---

## Tests
`tests/Feature/V19ChangesTest.php` (22 new). Two estimate tests in
`BillingTest` and one in `CollectionsTest` were rewritten for the new rule
(an estimate can no longer be paid). Rent tests that assume whole months now
pin their date to the 1st.

To check nothing depends on today's date:

```bash
FAKE_NOW=2026-10-17 php artisan test
```

137 passing on the real date and on the 9th, 17th and a February date.
