# Project Map

A navigation aid for this codebase. Start here when something breaks: find the
symptom in "Where to look when X breaks", then jump to the file.

---

## 1. The 30-second version

```
routes/
  web.php               ← table of contents only, no real routes
  web/public.php        ← landing page
  web/auth.php          ← login, register, password reset
  web/manager.php       ← everything behind ['auth','manager']
  web/tenant.php        ← everything behind ['auth']
  web/dev.php           ← debug endpoints, delete before launch
  console.php           ← artisan commands defined inline
  schedule.php          ← every cron task, loaded by console.php

app/Http/Controllers/
  Auth/                 ← login / register / password
  Manager/              ← manager side
  Tenant/               ← tenant side
  LandingController     ← public landing page
  TenantLeaseController ← tenant lease list + apply (NOT in Tenant/, see §5)

app/Models/             ← one file per table
app/Services/           ← BillingService, CollectionsService,
                          MaintenanceCostService — all money/escalation logic
app/Console/Commands/   ← artisan commands (auto-discovered)
app/Mail/               ← one class per outgoing email
app/Services/, app/Helpers/  ← SMS sending

resources/views/
  layouts/              ← page shells (@extends targets)
  partials/             ← reusable fragments (@include targets)
  manager/              ← manager pages
  tenant/               ← tenant pages
  reports/pdf/          ← PDF templates (dompdf)
  emails/               ← email templates

database/migrations/    ← schema history, oldest first
docs/                   ← this file, plus change notes
```

---

## 2. Where to look when X breaks

| Symptom | Look here first |
|---|---|
| A page 500s with "View [x] not found" | The controller method that renders it — run `php artisan app:audit` |
| A link 404s or 500s | `routes/web/*.php`, then check the controller method actually exists |
| `php artisan route:cache` fails | Two routes share a `->name()`. Run `php artisan app:audit` |
| A route "does nothing" / hits the wrong code | Same METHOD + URI registered twice — the **last** one wins |
| Paid/Unpaid shows wrong | `Manager/ManagerController@tenants` + `resources/views/partials/tenants_table.blade.php` |
| A payment saved but a field is empty | `Payment::$fillable` — anything not listed is discarded silently (see §4) |
| Manager-recorded payment looks wrong | `Manager/PaymentRecordController@paymentStore` |
| Tenant-submitted payment looks wrong | `Tenant/PaymentController@store` |
| Accept/Reject payment misbehaves | `Manager/PaymentRecordController@updatePaymentStatus` |
| Report table on screen is wrong | `Manager/ReportsController@show` |
| Report **PDF** is wrong but the screen is right | `Manager/ReportExportController` — the filters are a separate copy (§3) |
| Approving a tenant errors or duplicates | `Manager/ManagerController@approve` → `performApprove()` |
| Approving an *additional* unit errors | `Manager/UnitController@approveAdditionalUnit` |
| Move-out stuck as "pending" | `Manager/ManagerController@moveOut` |
| Rent didn't increase 5% | `Lease::renewForAnotherYear()`, `app/Console/Commands/RenewLeases.php`, `routes/schedule.php` |
| A scheduled job never ran | `routes/schedule.php` + `php artisan schedule:list` + the server cron entry |
| Duplicate rows from a double-click | `resources/views/partials/single-submit.blade.php` + the `Cache::lock()` in the controller |
| Utility Electricity/Water label missing | Column is `utility_type` on `payments`, `billing_type` on `utility_billing_proofs` (§4) |
| A bill's paid total looks wrong | `UtilityBillingProof::syncPaidTotals()` — allocations are the truth, `amount_paid` is a cache |
| A payment landed on the wrong bill | `BillingService::applyToUtilityBills()` — allocation is oldest-due-first |
| Overpayment disappeared | `config('billing.overpayment')`, then `BillingService::grantCredit()` |
| Shared bill shares don't add up | `BillingService::splitAmount()` + the batch's `rounding_absorbed` (§10) |
| Rent due date moved on a partial payment | `BillingService::applyToRent()` — it should only move at zero balance |
| A tenant is at the wrong collections stage | `CollectionsService::assess()`, then check `delivered_at` on the demand |
| Repair total ≠ the line items | `MaintenanceCostService::recalculate()` |
| Renovation request blocked/missing | Deposit gate — `Lease::hasUnpaidDeposit()`, enforced in 3 places (§11) |
| SMS not sending | `app/Services/SmsService.php`, `app/Helpers/SmsHelper.php` |
| Bed-spacer applicants wrongly auto-rejected | `Manager/ManagerController@performApprove` — the capacity/same-bed logic (§13) |
| A lease was created with zero rent/deposit | `Manager/ManagerController@performApprove` — rent comes from `units.room_price` (§13) |
| Renewal missing from the report | `Lease::renewForAnotherYear()` — it writes the `lease_renewals` row itself |
| Force move-out didn't collect arrears | `Manager/ManagerController@forceMoveOut` — arrears are recomputed server-side (§14) |
| Renovation records not in Tenant Management | Moved to Reports → Renovation Requests in v14 (§15) |
| Rent not charged for a month | `rent:charge` — `BillingService::chargeDueRent()` (§20) |
| A tenant billed twice for one month | Can't happen: unique index on `rent_charges` (lease_id, period_start) (§20) |
| Late penalty missing / charged twice | `BillingService::assessLatePenalty()` + `leases.last_penalty_due_date` (§20) |
| `due_date` stuck on a month already paid | `BillingService::refreshRentDueDate()` (§20) |
| Advance rent missing or wrong | `BillingService::applyToAdvanceRent()` — the only thing that writes it (§17) |
| Prepaid rent vanished at move-out | `ManagerController@forceMoveOut` / `@moveOut` — it is consumed then refunded (§17) |
| A repair's schedule/technician/diagnosis "does nothing" | It saves fine — the question is who can SEE it (§18) |
| Tenant says nobody told them a technician was coming | `MaintenanceCostController@updateDetails` — notifies on material changes only (§18) |
| `utility_balance` right on one screen, stale on another | `BillingService::syncLeaseUtilityBalance()` — stale-instance trap (§4) |
| Dashboard due-date list wrong / missing a tenant | `app/Services/DueDateMonitor.php` — read-only; live = `active`/`approved` leases, 30-day window (v18) |
| Dashboard scrolls on a PC | `manager/dashboard.blade.php` — the `@media (min-width:1200px) and (min-height:700px)` height calc (v18) |
| Camera won't open on registration | Needs https:// or localhost — `partials/id-camera.blade.php` falls back to file/camera app (v18) |
| Registration rejected for privacy consent | `RegisterController@register` (`data_privacy` accepted) + `config/privacy.php` (v18) |
| Login "Please wait" stuck | `auth/login.blade.php` — own submit lock, 30s release; form opts out of single-submit (v18) |

---

## 3. Known duplication (the biggest bug source)

**Search filters are written three times.** `active-tenants`, `payment-history`,
`lease-summary` and `maintenance-requests` each have filter logic in:

1. `Manager/ReportsController@show` — the on-screen table
2. `Manager/ReportExportController@export` — the PDF download
3. `Manager/ReportExportController@viewReportPdf` — the PDF preview

**These three copies are not identical.** For `payment-history` in particular,
the on-screen version searches `account_no` while the preview version searches
`utility_type` and uses raw `DATE_FORMAT` SQL. If a search works in one place
and not another, that is why — you have to fix all three.

**Manager vs tenant payments.** `Manager/PaymentRecordController@paymentStore`
and `Tenant/PaymentController@store` both write to `payments` and both adjust
lease balances, with slightly different validation. Change one, check the other.

**`DATE_FORMAT(...)` is MySQL-only.** Those raw queries will throw on SQLite.

**The two v14 reports deliberately do NOT repeat this.** `renewal-history` and
`renovation-requests` have their filter logic in exactly two places: once in
`ReportsController@show` for the screen, and once in a private query builder on
`ReportExportController` (`renewalHistoryQuery()` / `renovationRequestsQuery()`)
that BOTH PDF entry points call. Fix a filter there and both PDFs follow. They
also use `whereMonth`/`whereYear` instead of raw `DATE_FORMAT()`, so they work
on SQLite.

---

## 4. Column-name traps

These have caused silent, no-error bugs in this project:

| You might write | The real column is | Where |
|---|---|---|
| `paid_at` | `pay_date` | `payments` |
| `billing_type` | `utility_type` | `payments` |
| `billing_type` | `billing_type` ✔ | `utility_billing_proofs` |

The migration `2026_03_14_102957_add_billing_type_to_payments_table.php` is
misnamed — it actually adds `utility_type`. The filename can't be changed
safely because it's the key in the `migrations` table.

**Why these fail silently:** Eloquent's `create()`/`fill()` throw away any key
not listed in the model's `$fillable`. No exception, no warning — the value
just never reaches the database.

**A second silent-write trap, same symptom.** `$model->save()` only persists
attributes Eloquent thinks are DIRTY. If you hold a *stale* model instance and
assign it the value it already has in memory, `save()` writes nothing — even
though the real row says something else because another instance changed it.
This bit `syncLeaseUtilityBalance()`: a move-out closed every bill, recomputed
the balance as 0, assigned 0 to a stale Lease whose in-memory copy was already
0, and saved nothing, leaving the row showing the old balance. It now does a
targeted `Lease::whereKey(...)->update()` and re-syncs the in-memory original.
Refreshing instead is NOT safe there — callers hold unsaved changes on the
same instance.

**And a third.** `$model->refresh()` reloads every attribute from the database,
discarding anything you assigned but have not saved. `reconcileEstimate()` set
`is_estimated = false` and `reconciled_at`, then called `refresh()` before
`save()`, so a reconciled bill stayed flagged as an unreconciled estimate for
ever. Save first, then refresh.

---

## 5. Naming inconsistencies (not bugs, but confusing)

- `TenantLeaseController` lives in `app/Http/Controllers/` while every other
  tenant controller is in `app/Http/Controllers/Tenant/`. `Tenant/LeaseController`
  is a *different, mostly unused* class — only its `cancelMoveOut()` and
  `cancelAddUnit()` are routed.
- Lease status is compared case-insensitively all over (`['active','Active']`)
  because both casings exist in the data.
- Payment status uses `Accepted`, not `Paid`, despite the `$fillable` comment.
- Some methods are marked `@deprecated NOT ROUTED` — they exist but nothing can
  reach them. Safe to delete once you're confident.

---

## 5b. Money lives in services, not controllers

Since v13, `Manager\PaymentRecordController` and `Tenant\PaymentController`
both delegate to `App\Services\BillingService`. That is deliberate: the two
sides used to hold separate copies of the balance-deduction logic and had
already drifted apart. Change the service, not the controllers.

The same pattern applies to `CollectionsService` (arrears, demand stages) and
`MaintenanceCostService` (repair costs, who pays).

**Only `BillingService` writes** `utility_billing_proofs.amount_paid`,
`utility_billing_proofs.status`, or any `bill_payment_allocations` row. If you
find code elsewhere touching those, that is the bug.

Full detail: [`docs/BILLING-AND-COLLECTIONS.md`](BILLING-AND-COLLECTIONS.md).

---

## 6. Full route table

### Public — `routes/web/public.php`

| Method | URI | Controller | Name |
|---|---|---|---|
| GET | `/` | `LandingController@index` | `landing` |

### Auth — `routes/web/auth.php`

| Method | URI | Controller | Name |
|---|---|---|---|
| GET | `/login` | `LoginController@index` | `login` |
| POST | `/login` | `LoginController@login` | `login.submit` |
| POST | `/logout` | `LoginController@logout` | `logout` |
| GET | `/register` | `RegisterController@index` | `register` |
| POST | `/register` | `RegisterController@register` | `register.submit` |
| POST | `/register/check-email` | `RegisterController@checkEmail` | `register.check-email` |
| GET | `/forgot-password` | `ForgotPasswordController@showForgotForm` | `password.request` |
| POST | `/password/email` | `ForgotPasswordController@sendResetLinkEmail` | `password.email` |
| GET | `/reset-password/{token}` | `ResetPasswordController@showResetForm` | `password.reset` |
| POST | `/reset-password` | `ResetPasswordController@reset` | `password.update` |

### Manager — `routes/web/manager.php` (`auth` + `manager`)

| Method | URI | Controller | Name |
|---|---|---|---|
| GET | `/manager/dashboard` | `DashboardController@dashboard` | `manager.dashboard` |
| GET | `/manager/settings` | `DashboardController@settings` | `manager.settings` |
| GET | `/manager/reports` | `ReportsController@index` | `manager.reports` |
| GET | `/manager/reports/{report}` | `ReportsController@show` | `manager.reports.show` |
| GET | `/manager/reports/{report}/export` | `ReportExportController@export` | `manager.reports.export` |
| GET | `/manager/reports/export/{report}` | `ReportExportController@viewReportPdf` | `manager.reports.viewReportPdf` |

`{report}` is one of: `active-tenants`, `payment-history`, `lease-summary`,
`maintenance-requests`, `renewal-history` (v14), `renovation-requests` (v14).
No new routes were added for the two v14 reports — they are extra `case`
branches on the existing three endpoints.
| PATCH | `/manager/payments/{payment}/status` | `PaymentRecordController@updatePaymentStatus` | `manager.payments.updateStatus` |
| PATCH | `/manager/payments` | `PaymentRecordController@paymentStore` | `manager.payments.store` |
| GET | `/manager/tenant/{tenant}/leases` | `LeaseBalanceController@forTenant` | `manager.tenant.leases` |
| GET | `/manager/tenants` | `ManagerController@tenants` | `manager.tenants` |
| GET | `/manager/tenants/export` | `ManagerController@exportTenantsPdf` | `manager.tenants.export` |
| GET | `/manager/tenants/{id}/view-ids` | `ManagerController@viewIds` | `manager.tenants.viewIds` |
| POST | `/manager/tenants/notify/{id}` | `ManagerController@notifyTenant` | `manager.tenants.notify` |
| POST | `/manager/tenant/approve/{id}` | `ManagerController@approve` | `manager.tenant.approve` |
| POST | `/manager/tenant/reject` | `ManagerController@reject` | `manager.tenant.reject` |
| POST | `/manager/leases/{lease}/move-out` | `ManagerController@moveOut` | `manager.leases.approveMoveOut` |
| POST | `/manager/leases/{lease}/reject-move-out` | `ManagerController@rejectMoveOut` | `manager.leases.rejectMoveOut` |
| PATCH | `/manager/tenant/{id}/force-move-out` | `ManagerController@forceMoveOut` | `manager.tenant.forceMoveOut` |
| POST | `/manager/leases/{lease}/renew` | `ManagerController@renewLease` | `manager.leases.renew` |
| GET | `/manager/requests/{id}` | `MaintenanceRequestController@show` | `manager.requests.show` |
| PATCH | `/manager/requests/{id}/status` | `MaintenanceRequestController@updateStatus` | `manager.requests.updateStatus` |
| POST | `/manager/renovations/{id}/decide` | `RenovationRequestController@decide` | `manager.renovations.decide` |
| GET | `/manager/units` | `UnitController@index` | `manager.units.index` |
| POST | `/manager/units` | `UnitController@store` | `manager.units.store` |
| GET | `/manager/units/{unit}/edit` | `UnitController@edit` | `manager.units.edit` |
| PUT | `/manager/units/{unit}` | `UnitController@update` | `manager.units.update` |
| DELETE | `/manager/units/{unit}` | `UnitController@destroy` | `manager.units.destroy` |
| POST | `/manager/approve-unit/{user}/{unit}/{lease}` | `UnitController@approveAdditionalUnit` | `manager.approve-unit` |
| POST | `/manager/reject-unit/{user}/{unit}/{lease}` | `UnitController@rejectAdditionalUnit` | `manager.reject-unit` |
| GET | `/manager/utilities` | `UtilityController@index` | `manager.utilities.index` |
| PUT | `/manager/utilities/{id}` | `UtilityController@updateUtilityBalance` | `manager.utilities.update` |
| POST | `/manager/requests/{id}/details` | `MaintenanceCostController@updateDetails` | `manager.requests.details` |
| POST | `/manager/requests/{id}/work-items` | `MaintenanceCostController@addWorkItem` | `manager.requests.workItems.store` |
| DELETE | `/manager/requests/{id}/work-items/{item}` | `MaintenanceCostController@removeWorkItem` | `manager.requests.workItems.destroy` |
| POST | `/manager/requests/{id}/responsibility` | `MaintenanceCostController@setResponsibility` | `manager.requests.responsibility` |
| POST | `/manager/requests/{id}/charge` | `MaintenanceCostController@charge` | `manager.requests.charge` |
| GET | `/manager/bills` | `UtilityBillController@index` | `manager.bills.index` |
| POST | `/manager/bills` | `UtilityBillController@store` | `manager.bills.store` |
| POST | `/manager/bills/shared` | `UtilityBillController@storeShared` | `manager.bills.storeShared` |
| GET | `/manager/bills/{bill}` | `UtilityBillController@show` | `manager.bills.show` |
| POST | `/manager/bills/{bill}/reconcile` | `UtilityBillController@reconcile` | `manager.bills.reconcile` |
| POST | `/manager/bills/{bill}/adjust` | `UtilityBillController@adjust` | `manager.bills.adjust` |
| POST | `/manager/bills/{bill}/void` | `UtilityBillController@void` | `manager.bills.void` |
| GET | `/manager/collections` | `CollectionsController@index` | `manager.collections.index` |
| GET | `/manager/collections/{lease}` | `CollectionsController@show` | `manager.collections.show` |
| POST | `/manager/collections/{lease}/demand` | `CollectionsController@issue` | `manager.collections.issue` |
| POST | `/manager/collections/demands/{demand}/deliver` | `CollectionsController@deliver` | `manager.collections.deliver` |
| POST | `/manager/collections/demands/{demand}/close` | `CollectionsController@close` | `manager.collections.close` |

### Tenant — `routes/web/tenant.php` (`auth`)

| Method | URI | Controller | Name |
|---|---|---|---|
| GET | `/tenant/home` | `Tenant\TenantController@dashboard` | `tenant.home` |
| GET | `/tenant/application` | `Tenant\TenantApplicationController@show` | `tenant.application` |
| POST | `/tenant/application/submit` | `Tenant\TenantApplicationController@submit` | `tenant.application.submit` |
| GET | `/tenant/leases` | `TenantLeaseController@index` | `tenant.leases` |
| POST | `/tenant/leases/apply` | `TenantLeaseController@store` | `tenant.leases.store` |
| POST | `/tenant/leases/{id}/request-move-out` | `TenantLeaseController@requestMoveOut` | `tenant.leases.requestMoveOut` |
| PATCH | `/tenant/cancel-move-out/{leaseId}` | `Tenant\LeaseController@cancelMoveOut` | `tenant.cancelMoveOut` |
| PATCH | `/tenant/cancel-add-unit/{leaseId}` | `Tenant\LeaseController@cancelAddUnit` | `tenant.cancelAddUnit` |
| GET | `/tenant/payments` | `Tenant\PaymentController@index` | `tenant.payments` |
| POST | `/tenant/payments/store` | `Tenant\PaymentController@store` | `tenant.payments.store` |
| GET | `/tenant/requests` | `Tenant\TenantRequestController@index` | `tenant.requests` |
| POST | `/tenant/requests/store` | `Tenant\TenantRequestController@store` | `tenant.requests.store` |
| GET | `/tenant/requests/{id}` | `Tenant\TenantRequestController@show` | `tenant.requests.show` |
| PATCH | `/tenant/requests/{id}/cancel` | `Tenant\TenantRequestController@cancel` | `tenant.requests.cancel` |
| GET | `/tenant/renovations` | `Tenant\RenovationRequestController@index` | `tenant.renovations` |
| POST | `/tenant/renovations/store` | `Tenant\RenovationRequestController@store` | `tenant.renovations.store` |
| PATCH | `/tenant/renovations/{id}/cancel` | `Tenant\RenovationRequestController@cancel` | `tenant.renovations.cancel` |
| GET | `/tenant/notifications` | `Tenant\NotificationController@index` | `tenant.notifications` |

### Dev — `routes/web/dev.php` (no auth — delete before launch)

`POST /alert/show`, `GET /run-scheduler`, `GET /test-mail`, `GET /clear-mail-cache`

---

## 7. Scheduled tasks

Defined in `routes/schedule.php`, which is required from `routes/console.php`.
Laravel 12 does **not** auto-load `schedule.php` — if that `require` is ever
removed, every scheduled job silently stops.

| Command | When | Log |
|---|---|---|
| `update:tenant-status` | daily 00:00 | `storage/logs/tenant_status.log` |
| `rent:charge` | daily 00:30 | `storage/logs/rent_charges.log` |
| `leases:renew` | daily 01:00 | `storage/logs/lease_renewals.log` |

Server cron entry required:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Verify with `php artisan schedule:list`.

---

## 8. The audit command

```bash
php artisan app:audit           # routes + controllers
php artisan app:audit --views   # also scan Blade files
```

It reports:

- routes pointing at a controller class that doesn't exist
- routes pointing at a method that doesn't exist
- `view('x.y')` where the Blade file is missing
- duplicate route names (these break `route:cache`)
- duplicate METHOD + URI (the earlier one is dead code)

Exit code 1 when problems are found, so it can go in a deploy script.
Run it after touching routes or renaming anything.

---

## 9. Useful one-liners

```bash
php artisan route:list --path=manager    # what actually resolves
php artisan schedule:list                # what is actually scheduled
php artisan route:cache                  # fails loudly on duplicate names
php artisan config:clear && php artisan view:clear && php artisan route:clear
grep -rn "route('some.name')" resources/views/   # who links to a route
```

---

## 10. The billing tables (added v13)

`utility_billing_proofs` **is the bill table.** The name is historical — it
started as "a photo of the bill" and grew into the bill itself. It was not
renamed because that would break the `migrations` table key, every existing call
site and all three copies of the report filters.

| Table | Holds |
|---|---|
| `utility_billing_proofs` | one bill: amount, amount_paid, due date, status, estimate flag |
| `utility_bill_batches` | one provider bill split across rooms: total, rule, rounding remainder |
| `bill_payment_allocations` | which payment paid which bill, and how much |
| `bill_adjustments` | signed corrections to a bill (+ charge, − credit) |
| `payment_demands` | every demand issued, and proof it was served |
| `maintenance_work_items` | labour and material lines on a repair |
| `maintenance_status_logs` | repair history — every status change |

Two invariants worth memorising:

1. **A bill's `amount` is never edited** once the tenant has seen it.
   Corrections are `bill_adjustments` rows. `totalDue()` = amount + adjustments.
2. **`amount_paid` is a cache** of the live allocations. Allocations win.

Shares in a split **always round down**, so tenants can never be billed more
than the provider charged. The leftover centavo goes where
`config('billing.rounding_remainder')` says — landlord absorbs by default — and
is always recorded on the batch.

---

## 11. Collections: what the system deliberately cannot do

There is no route, button or scheduled job that evicts a tenant, terminates a
lease, disconnects a utility, changes a lock, disables a login, or sends a
demand by itself. This is not an oversight.

`config('collections.allow_self_help_remedies')` exists so nobody can quietly
add one — no code path honours it and `CollectionsService` throws if it is set
to `true`. `tests/Feature/CollectionsTest.php` asserts that.

The furthest stage is `eligible_for_formal_action`, which reads "refer to
counsel". The system has no step after it.

**The clock starts at service, not at drafting.** A demand with no
`delivered_at` never expires, however old. An undelivered notice proves nothing.

The periods in `config/collections.php` are placeholders and still need a
lawyer's sign-off — see the header of that file.

### Renovation deposit gate

A room with an unpaid deposit cannot be renovated. Enforced in three places
because the form is client-side and a stale tab could post a blocked lease id:

1. `Tenant\RenovationRequestController@index` — filters the dropdown
2. `Tenant\RenovationRequestController@store` — re-checks inside the transaction
3. `Manager\RenovationRequestController@decide` — re-checks at approval, since
   the deposit position can change while a request sits in the queue

Denying is always allowed; only approval is gated.

---

## 12. Tests

The project had no `tests/` directory before v13 — `phpunit.xml` pointed at
`tests/Unit` and `tests/Feature` and neither existed.

```bash
php artisan test
php artisan test --filter=BillingTest
```

| File | Covers |
|---|---|
| `tests/Unit/BillSplitTest.php` | proration arithmetic, rounding, determinism (no DB) |
| `tests/Feature/BillingTest.php` | exact/under/over payment, zero amounts, estimates, arrears, reversal |
| `tests/Feature/CollectionsTest.php` | stages, service-starts-the-clock, self-help guard |
| `tests/Feature/MaintenanceCostTest.php` | work items, responsibility, charging |
| `tests/Feature/RenovationDepositGateTest.php` | the deposit gate at all three layers |

Tests run on SQLite in memory. Note that the report filters use raw
`DATE_FORMAT()`, which is MySQL-only and will throw under SQLite — that is the
pre-existing issue in §3, and it is why these tests exercise the services
directly rather than going through the report screens.

---

## 13. Approval: capacity, beds and rent (changed in v14)

`Manager/ManagerController@performApprove` does three things that are easy to
get wrong, so they are spelled out here.

**Rent comes from the unit, not the application.** It used to be a `match()` on
the applicant's chosen `unit_type` listing Studio / 1-Bedroom / 2-Bedroom /
Bed-Spacer with `default => 0`. **Commercial hit that default**, so commercial
leases were created with zero rent and zero deposit — and because the Record
Payment dropdown only lists tenants who owe something, the manager then could
not record a deposit or rent payment against them at all. Every branch returned
`$unit->room_price` anyway. It now reads that directly, which also covers any
unit type added later.

**A bed spacer can be full.** Approving past `units.capacity` is refused, and so
is assigning a `bed_number` that another live lease already holds. Those are two
separate checks: a room can have a free bed *and* a clash, when two applicants
both asked for bed 2 while bed 3 sits empty.

**Auto-rejection is scoped to who is actually shut out.** Approving one
applicant used to reject *every* other pending applicant for the unit. That is
right for a whole room — one room, one tenant — and wrong for a bed spacer,
where it was throwing away applicants for beds that were still vacant. The rule
now:

| Unit type | Who gets auto-rejected |
|---|---|
| Anything but Bed-Spacer | every other pending applicant |
| Bed-Spacer, beds remain | only applicants for the **same bed** |
| Bed-Spacer, now full | everyone still pending |

---

## 14. Force move-out settlement (changed in v14)

`forceMoveOut` used to set `rent_balance` and `penalty_fee` to `0` without
collecting them, so everything the tenant still owed vanished at move-out.

It now itemises the arrears — unpaid rent, open utility bills, penalty — into
`damage_details` alongside the manager's damage lines, and deducts the total
from the refund.

Two things worth knowing:

1. **The figures are recomputed from the lease on submit.** The modal posts a
   copy for display, but the server never trusts it: a stale tab or an edited
   hidden field must not be able to change what a tenant is charged. The posted
   `damage_cost` carries *only* the manager-entered damages — the server adds
   the arrears itself, so posting them too would double-count them.
2. **The deposit balance is not part of the arrears.** An unpaid deposit does
   not mean money comes out of the refund; it means there is no refund.

When damages plus arrears exceed the deposit, the difference is reported as a
shortfall in the UI and the success message. It stays owed — nothing here
writes it off, and pursuing it is a Collections matter (§11).

`data-deposit` in the modal also had a real bug: it read `rent_balance * 2`,
which is *outstanding* rent, so a tenant who was paid up showed a deposit of
₱0.00 and got no refund. It now uses `currentRent() * 2`.

---

## 15. Where renovation records live (moved in v14)

Renovation requests used to be a modal per tenant inside
`resources/views/manager/tenants.blade.php`. They are now a report:
**Reports → Renovation Requests**, searchable and exportable across all tenants.

The approve/deny form moved with them — it is in the report row, not in Tenant
Management. The dropdown item on the tenants page is now a deep link into that
report pre-filtered to the tenant. `ManagerController@tenants` no longer
eager-loads `renovationRequests`.

The deposit gate still applies at approval
(`Manager\RenovationRequestController@decide`), unchanged.

### The removed Title field

The tenant form no longer asks for a Title — the type plus the description
already say what the request is. **The `title` column still exists and is
nullable.** Dropping it would destroy the titles on requests filed before the
change, and those are the only record of what some of them were about.

So anything displaying a request has to handle both: use
`RenovationRequest::summaryLabel()`, which prefers the title where one exists
and falls back to the type. Views show the title only `@if($item->title)`.

Picking **Others** in the type dropdown reveals a free-text box, and what the
tenant types is stored *as* `renovation_type` — the literal string "Others" is
never saved, or the report would be full of rows labelled Others with the real
answer buried in the description. The option list lives in
`RenovationRequest::TYPES`; don't hard-code it in views.

---

## 16. The renewal history table (added in v14)

`leases.renewal_count` and `leases.last_renewed_at` only say "renewed N times,
most recently on X". The renewal report has to answer "which tenants renewed,
when, and what did the rent go from and to" — that needs a row per event.

| Table | Holds |
|---|---|
| `lease_renewals` | one renewal event: rents before/after, term, source, who |

**Only `Lease::renewForAnotherYear()` writes it.** The write is inside the model
rather than in the two callers (`ManagerController@renewLease` and the
`leases:renew` command) on purpose: in the callers, a third caller added later
would renew silently and leave the history quietly incomplete rather than
obviously broken.

`previous_rent` / `new_rent` are stored as numbers, not recomputed from
`RENT_INCREASE_RATE`. If that rate ever changes, history must keep showing what
was actually charged at the time.

`source` distinguishes `manual` (a manager clicked Renew) from `scheduled` (the
cron rolled it over) from `legacy`. **Legacy rows are reconstructed**, not
recorded: the migration backfills one row per already-renewed lease by dividing
the current rent by 1.05, because that is all the information that survived.
They are labelled "Reconstructed" in the report and footnoted in the PDF so
nobody mistakes them for a real event log.

---

## 17. Advance rent (added v16)

Rent paid before the month it covers. `payment_for = 'Advance Rent'` on both
sides; everything else is the normal payment pipeline.

| Column | Where | Holds |
|---|---|---|
| `leases.advance_rent_balance` | lease | rent money on account, not yet consumed |
| `leases.rent_paid_through` | lease | the last day rent is covered to |
| `payments.advance_months` | payment | whole months this payment bought |
| `payments.advance_amount` | payment | the part that was held rather than clearing arrears |

**It is NOT `users.user_credit`, and that is the whole point.** Credit is a
general pot that `BillingService` spends on whichever bill comes next,
including electricity. A tenant who pays three months of rent up front has
bought three months of RENT — if that money quietly went on a water bill they
would be back in rent arrears having done nothing wrong. So it is held on the
lease and drawn down only against rent and penalties.

**Arrears are settled first.** Buying April while March is unpaid would leave
the tenant still behind and still accruing the 10% penalty, having just handed
over the money. Config: `billing.advance_rent.settle_arrears_first`.

**Only whole months move `due_date`.** A part month sits on the balance. Months
are granted on the rounded-down TOTAL, not per payment, so two half-month
payments buy one month between them rather than zero.

**The split is recorded, not recomputed.** `advance_months` / `advance_amount`
are written when the payment is applied, because a renewal can change the rent
in between — reversing with "amount ÷ current rent" would give back a
different number of months than were granted.

**A renewal draws the advance down at the NEW rent** (`Lease::consumeAdvanceRent()`,
called from `renewForAnotherYear()`). Three months bought at the old rate may
only cover two and a bit afterwards. That is the honest outcome — pretending
otherwise would mean the increase never actually applied.

**Move-out returns what is left.** Both `@forceMoveOut` and `@moveOut` consume
the advance against arrears first, then add the remainder to the refund and
zero the columns. It is not security against damage; it is rent for months that
are not going to happen. The cap (`billing.advance_rent.max_months`, 12) exists
for the same reason: prepaid rent is the landlord holding the tenant's money.

Tests: `tests/Feature/AdvanceRentTest.php`.

---

## 18. Maintenance: the fields nobody could see (fixed v16)

`assigned_to`, `scheduled_date`, `started_at`, `completed_at`, `diagnosis`,
`resolution_notes` and the whole cost/responsibility block were all being
written correctly and shown to almost nobody. Three separate causes:

**1. The manager's own screen had no link to it.** `GET /manager/requests/{id}`
is where all of those fields are edited, and nothing in the entire UI linked to
it — a manager had to type the URL by hand. Reports → Maintenance Requests now
has an **Open** button per row, plus a "Repair" column summarising schedule,
technician, cost and who pays (with a red *who pays?* when a costed job has
nobody assigned to pay for it).

**2. The tenant had no detail view at all.** `GET /tenant/requests/{id}` now
shows the full lifecycle, the tenant's own uploaded photo (which they
previously could never see again), and a status timeline from
`maintenance_status_logs`.

**3. The list lied about the date.** The tenant table showed `supposed_date`
under a column headed "Scheduled Date". `supposed_date` is the tenant's
PREFERRED date; `scheduled_date` is what the office booked. Both now appear,
separately labelled.

**What the tenant is deliberately NOT shown.** Cost figures appear only when
`cost_responsibility` is `tenant` or `shared`. When the landlord is absorbing
the repair, what it cost the landlord is not the tenant's business and showing
it invites an argument about a bill nobody is sending — they see "no charge to
you" instead. See `$showCosts` in `Tenant\TenantRequestController@show`.

**Notifications are for material changes only.** `updateDetails()` compares
before/after and notifies on a new schedule or assignment, and on resolution
notes once the job is complete. Re-saving the form unchanged, or fixing a typo
mid-repair, stays silent — an alert the tenant learns to ignore is worse than
no alert.

`started_at` is set automatically on the first move to In Progress and is now
displayed on both sides; without it `turnaroundDays()` was unanswerable.

---

## 19. The test suite (repaired v16)

It was failing 53 of 63 before this version, and had been since it was written:
every lease fixture omitted `lea_start_date`, which is NOT NULL, so the first
insert in almost every test threw. Fixed in all four feature test files.

With the suite actually running, it found three real bugs, all now fixed and
described in §4: the `refresh()`-before-`save()` bug in `reconcileEstimate()`,
the stale-instance no-op in `syncLeaseUtilityBalance()`, and a fixture using a
`renovation_type` that no longer exists in `RenovationRequest::TYPES` (the list
changed in v14 and the test was never updated, so it was failing validation
before it ever reached the deposit gate it exists to check).

**92 passing as of v16.** Run it before shipping — it is load-bearing now.

| File | Covers |
|---|---|
| `tests/Feature/AdvanceRentTest.php` | holding, arrears-first, part months, reversal, renewal draw-down |
| `tests/Feature/RentAccrualTest.php` | monthly accrual, idempotency, catch-up cap, penalties, due-date tracking |
| `tests/Feature/MaintenanceCostTest.php` | costs and responsibility, plus what the tenant may and may not see |

---

## 20. Monthly rent accrual (added v16)

**The bug.** Rent was charged exactly twice in a lease's entire life: once at
approval, and again at each yearly renewal. `leases.rent_balance` was set to
one month at approval, and `due_date` rolled forward whenever the balance
reached zero — but nothing ever put the NEXT month on the balance. A tenant who
paid their first month owed nothing for the following eleven, and the system
agreed with them.

`rent:charge` now raises one month per lease per month.

| Table / column | Holds |
|---|---|
| `rent_charges` | one row per month charged: period, due date, amount, source |
| `leases.next_rent_charge_on` | when the next month falls due to be raised |
| `leases.last_penalty_due_date` | which overdue cycle was already penalised |

**The unique index is the real safety net.** `(lease_id, period_start)` is
unique, so an overlapping cron run, a crash between writing the charge and
stepping the date, or a double-clicked manual charge cannot bill the same month
twice. The date arithmetic is the intent; the constraint is the guarantee.
Money is worth a constraint.

**The backfill deliberately does not collect back rent.** Every existing lease
has months that were never billed. Starting the clock at the lease start date
would have made the first run raise a year of arrears against tenants who were
never told they owed it — turning a system bug into a debt-collection problem
overnight. The migration starts each lease at the LATER of its current due_date
and today, so the un-billed gap is written off. Anything genuinely owed from
that period is already on `rent_balance` and is untouched.

**Renewal stopped charging rent.** `Lease::renewForAnotherYear()` used to do
`rent_balance += newRent` because renewal was the only recurring charge in the
system. With a monthly charger, doing both would bill the renewal month twice.
A renewal is now purely a contract event — rate, term, dates, history — and the
next scheduled charge picks up the new rate on its own, because
`chargeDueRent()` reads `currentRent()` when it runs. The same line was removed
from both callers (`ManagerController@renewLease`, `RenewLeases`).

**`openRentSchedule()` owns the first month.** Approval used to set
`rent_balance` to one month by hand; it now passes zero and lets the service
raise a proper charge, so the first month has a row behind it like every month
after it. Do not set both — that bills day one twice. (Caught by a test.)

**`due_date` is derived, on every rent payment.** It points at the oldest month
not yet covered (`refreshRentDueDate()`). It used to be bumped by a month only
when the balance hit zero, which was wrong in both directions: a tenant two
months behind who cleared the older month kept a due date on a month they had
just paid, and one three months behind who cleared the lot moved forward by
only one. `rentChargeLedger()` derives settlement oldest-first from
charged-minus-owed — there is no per-charge paid flag to fall out of step.

**Catch-up is capped** (`billing.rent.max_catchup_months`, 3). A server whose
cron has been dead for six months must not silently bill six months in one
night. It raises the cap's worth, logs an error naming the leases, and leaves
the rest to a person.

**The late penalty moved out of a GET request.** The 10% fee was applied inside
`Tenant\PaymentController@index` — so a tenant who never opened their payments
page was never penalised, and one who did was penalised on page load. It is
assessed by the same nightly command now, once per missed due date.

**Run `php artisan rent:charge --dry-run` first after deploying.** Nothing
should be more than one month behind on the first real run. If it says
otherwise, stop and find out why before charging anyone.

Tests: `tests/Feature/RentAccrualTest.php`.
