# v20 — Bill actual amount rewrites the bill · Void cascades · Maintenance saves fixed

## Installing over your current project

```bash
cp -r /path/to/tenants-v20/{app,database,resources,tests} .
cp /path/to/tenants-v20/CHANGES-v20.md .
php artisan migrate            # 1 new migration — adds columns + converts old reconciled bills
php artisan view:clear && php artisan route:clear
php artisan test               # expect 152 passing (137 + 15 new)
```

Back up the database before migrating: the migration rewrites `amount` on
bills that were already reconciled (see 1). `migrate:rollback` puts them back.

---

## 1. Bills — setting the actual amount rewrites the bill
`BillingService::reconcileEstimate()`, `UtilityBillController@reconcile`, `manager/bills/show`

- **`amount` is now the actual figure.** For a metered Electricity bill the
  **kWh reading** is rewritten too, and the "Electricity:" line in the notes is
  replaced with the final reading (`Electricity (final): 120 kWh x PHP 18.00 …
  Estimate was 100.00 kWh / PHP 1,800.00.`).
- **The estimate is kept** in new columns `estimated_amount` / `estimated_kwh`
  (first estimate only — a second correction never overwrites it). Shown on the
  bill as "estimate was …" and on the ledger as "est. …".
- **No double counting.** The reconciliation row stays under Corrections as an
  "Estimate → actual" history line, marked "already in bill amount", and
  `totalDue()` no longer adds it. Manual corrections (goodwill etc.) still stack.
- **kWh amount is computed on the server.** Before, the controller trusted the
  hidden peso value from the browser. Now: reading × the bill's own rate,
  split the same number of ways as the estimate (so someone moving into a
  Bed-Spacer room between estimate and reading doesn't change the split).
- A metered bill now *requires* the kWh reading (the old rule never fired).
- Tenant notification says the final figure and what the estimate was.
- **Migration:** existing reconciled bills are converted —
  `estimated_amount = amount`, `amount = amount + reconciliation entries` —
  so balances don't move.

## 2. Bills — voiding updates everything tied to the bill
`BillingService::voidBill()`, `MaintenanceCostService::releaseCharge()`, `UtilityBillController@void`

One action, in one transaction:

| Tied to the bill | What happens |
|---|---|
| Lease utility balance | Re-synced from the remaining bills |
| Tenant | "Bill Cancelled" notification with the reason |
| **Repair bill → maintenance request** | Request goes back to "not yet billed": costs unlock, who-pays can change, it can be charged again. Logged in the request's history. (Before: voided repair = request locked forever.) |
| **Shared bill** | Checkbox (on by default) voids every other unpaid share. Shares with money on them are skipped and listed. Untick to void just this share. |
| Open demand notices on the lease | Internal note added: the utilities claimed included a now-voided charge — recheck before escalating. Not closed or rewritten. |
| Pending payments of that type | Counted and flagged in the success message before you accept them |

- Still refused if any money is on the bill (checks live allocations, not just the cached column).
- Repair bills are now linked by `maintenance_request_id`; old ones are
  backfilled from their "Maintenance request #N" note (and found by note at void time as a fallback).
- The maintenance request page links to its live Repair bill: "void it to reverse the charge".
- Double-click on Void is locked.

## 3. Report › Maintenance requests — edits not saving (fixed)
`resources/views/manager/requests/show.blade.php`, `partials/single-submit.blade.php`

**Cause:** the page loaded the double-submit guard a second time, on top of the
copy the layout already loads. Copy 1 locked the form; copy 2 saw it locked and
cancelled the submit. So **Update Status, Repair Details, Labour & Materials and
Who Pays** all showed "Please wait…" and never reached the server. The
controllers and database were fine.

- Removed the extra include (same bug removed from `manager/collections/show` —
  demand notices there were affected too).
- The guard now refuses to load twice on any page, so this can't recur.
- The request page now shows error/info messages and validation errors —
  previously a refused save looked identical to a silent failure.

## Files

New: `database/migrations/2026_10_02_000001_v20_bill_actual_amount_and_void_links.php`,
`tests/Feature/V20ChangesTest.php`, `CHANGES-v20.md`

Changed: `app/Services/BillingService.php`, `app/Services/MaintenanceCostService.php`,
`app/Http/Controllers/Manager/UtilityBillController.php`,
`app/Http/Controllers/Manager/MaintenanceCostController.php` (message text),
`app/Models/UtilityBillingProof.php`, `app/Models/BillAdjustment.php`,
`app/Models/MaintenanceRequest.php`, `resources/views/manager/bills/{show,index}.blade.php`,
`resources/views/manager/requests/show.blade.php`, `resources/views/manager/collections/show.blade.php`,
`resources/views/partials/single-submit.blade.php`, `tests/Feature/BillingTest.php` (one assertion)

## Not verified here
No PHP/Composer in this container: every PHP file is parse-checked and Blade
directives balance-checked, but nothing was executed. Run `php artisan test`
after migrating.
