# v16 — Monthly Rent + Advance Rent + Maintenance Fields

## Installing over your v15

This zip is **source only**. `vendor/`, `node_modules/`, `public/storage` and
everything under `storage/app` were stripped — they are unchanged and they are
270MB of your tenants' uploaded proofs and issue photos.

```bash
# from your existing v15 project root
cp -r /path/to/tenants-v16/{app,config,database,docs,resources,routes,tests} .
php artisan migrate
php artisan config:clear && php artisan view:clear && php artisan route:clear
php artisan test          # expect 92 passing
php artisan app:audit --views

# THEN, before letting the scheduler touch anyone's balance:
php artisan rent:charge --dry-run
```

Four new migrations, all additive, all with working `down()` methods — nothing
is dropped or rewritten.

**Add the scheduler cron entry if you have not already**, or the new nightly
rent run never happens:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Check with `php artisan schedule:list`.

---

## 1. Monthly rent — the big one

**Rent was only ever charged twice in a lease's whole life:** once at approval,
and again at each yearly renewal. `rent_balance` was set to one month at
approval, and `due_date` rolled forward whenever the balance hit zero — but
nothing ever put the *next* month on the balance. A tenant who paid their first
month owed nothing for the following eleven, and your system agreed with them.

`php artisan rent:charge` now runs nightly at 00:30 and raises one month per
lease per month, writing a `rent_charges` row for each.

**On deploying, nobody gets a surprise bill.** Every existing lease has months
that were never billed. Starting the clock at the lease start date would have
raised a year of arrears overnight against tenants who were never told they
owed it — turning a software bug into a debt-collection problem. The migration
starts each lease at the *later* of its current due date and today, so the
un-billed gap is written off. Anything genuinely owed is already on
`rent_balance` and is untouched.

I tested exactly that case: a lease 8 months old with a due date 7 months in
the past raised **one** month on the first run, not eight.

**Other decisions**

- **Double-billing is impossible at the database level** — a unique index on
  `rent_charges (lease_id, period_start)`. Overlapping cron runs, a crash
  mid-write, a double-clicked manual charge: all safe. Run the command as many
  times as you like.
- **Catch-up is capped at 3 months per run.** If your cron dies for six months,
  it raises three, logs an error naming the leases, and leaves the rest to you
  rather than silently billing half a year in one night.
- **Renewal no longer charges rent.** It used to, because it was the only
  recurring charge in the system — doing both would now bill the renewal month
  twice. Renewal is a contract event: rate, term, dates. The next scheduled
  charge picks up the new rate automatically.
- **The 10% late penalty moved out of a GET request.** It was being applied
  inside the tenant's payments *page load* — so a tenant who never opened that
  page was never penalised, and one who did was. It is assessed by the same
  nightly command now, once per missed due date.
- **`due_date` now points at the oldest month actually unpaid** instead of
  being bumped by a month. The old way was wrong in both directions: someone
  two months behind who cleared the older month kept a due date on a month they
  had just paid; someone three months behind who cleared everything moved
  forward by only one month and still looked overdue.
- **Tenants can now see which months they owe.** New "Rent By Month" table on
  the payments page. A balance of ₱15,000 was previously a number they had to
  take on trust.

Turn it all off with `billing.rent.auto_charge => false` if you want to charge
by hand.

---

## 2. Advance rent (tenant + manager)

`payment_for = 'Advance Rent'` in both payment modals, with a months dropdown
that fills the amount. Everything else is the existing payment pipeline.

**New columns**

| Column | Holds |
|---|---|
| `leases.advance_rent_balance` | rent money on account, not yet consumed |
| `leases.rent_paid_through` | the last day rent is covered to |
| `payments.advance_months` | whole months this payment bought |
| `payments.advance_amount` | the part held rather than clearing arrears |

**Decisions you should know about**

- **Held on the lease, not in `user_credit`.** Credit gets spent on whichever
  bill comes next, including electricity. Three months of prepaid rent that
  quietly pays a water bill leaves the tenant in rent arrears through no fault
  of their own.
- **Arrears settle first.** Paying April forward while March is unpaid would
  leave them still behind and still accruing the 10% penalty, having just
  handed over the money.
- **Only whole months move `due_date`.** A part month sits on the balance. Two
  half-month payments buy one month between them, not zero.
- **Months are derived from the money server-side**, never trusted from the
  form. The arrears/advance split is recorded on the payment row, because a
  renewal can change the rent in between — reversing with "amount ÷ current
  rent" would give back a different number of months than were granted.
- **A renewal draws the advance down at the NEW rent.** Three months bought at
  the old rate may only cover two and a bit afterwards.
- **Move-out returns the unused part.** Both move-out paths consume it against
  arrears, then add the remainder to the refund. It is not security against
  damage.
- **Capped at 12 months** (`config/billing.php`). Prepaid rent is you holding
  the tenant's money — if the lease ends early it has to come back.

---

## 3. Maintenance: making those fields real

Every field on your list was already being written correctly. The problem was
who could see it.

**The manager's own screen had no link to it.** `GET /manager/requests/{id}` is
where `assigned_to`, `scheduled_date`, `diagnosis`, `resolution_notes`, work
items, cost responsibility and charging are all edited — and nothing in the
entire app linked to it. You had to type the URL by hand. That is why the data
looked unused.

- Reports → Maintenance Requests now has an **Open** button per row.
- Plus a **Repair** column: schedule, technician, cost, who pays — and a red
  *who pays?* when a finished, costed job has nobody assigned to pay for it.

**The tenant had no detail view at all.** New page at
`GET /tenant/requests/{id}`: the full lifecycle, the photo they uploaded (which
they could never see again after submitting), diagnosis, resolution notes, and
a status timeline.

**The list was lying about the date.** The tenant table showed `supposed_date`
under a column headed "Scheduled Date". That is the tenant's *preferred* date —
the date you actually booked was never shown to them. Both now appear,
separately labelled, and the form field is relabelled "Preferred Date".

**`started_at`** was written on every move to In Progress and displayed
nowhere, which made `turnaroundDays()` unanswerable. Now shown on both sides.

**Notifications** fire when you save a schedule or an assignment — but only on
material changes. Re-saving the form unchanged, or fixing a typo in the
diagnosis mid-repair, stays silent.

**What the tenant deliberately does not see:** cost figures appear only when
they are carrying some of it (`tenant` or `shared`). When you are absorbing the
repair, what it cost you is not their business and showing it invites an
argument about a bill you are not sending — they see "no charge to you".

---

## 4. Bugs the tests found

Your test suite was failing **53 of 63**, and had been since it was written:
every lease fixture omitted `lea_start_date`, which is NOT NULL, so the first
insert in nearly every test threw. Fixed in all four files.

With it actually running, it caught three real bugs:

1. **`reconcileEstimate()` lost its own writes.** It set `is_estimated = false`
   and `reconciled_at`, then called `refresh()` — which reloads every attribute
   from the database — *before* `save()`. Reconciled bills stayed flagged as
   unreconciled estimates for ever, and kept telling tenants "Estimated — will
   be adjusted" after they had been adjusted.

2. **`syncLeaseUtilityBalance()` silently wrote nothing.** `save()` only
   persists attributes Eloquent thinks are dirty. Handed a stale `Lease` whose
   in-memory `utility_balance` was already 0, assigning 0 made it not dirty —
   so a move-out could close every bill, correctly compute a balance of 0, and
   leave the row showing the old figure. Now a targeted `update()`.

3. **A renovation test used a `renovation_type` that no longer exists.** The
   type list changed in v14 and the fixture was never updated, so it failed
   validation before ever reaching the deposit gate it exists to check.

**92 passing now**, including 14 rent-accrual tests, 11 advance-rent tests and
4 covering what the tenant may and may not see on a repair. Two of the rent
tests caught real bugs in my own first cut — a day-one double-bill and a stale
due date — which is the argument for writing them. It is load-bearing — run it before you
ship.

`php artisan app:audit --views` reports the same 4 pre-existing warnings as
v15, both in unrouted dead methods (`DashboardController`, `Tenant\LeaseController`).
Untouched.

---

## Files changed

**New:** 4 migrations, `app/Models/RentCharge.php`,
`app/Console/Commands/ChargeMonthlyRent.php`,
`resources/views/tenant/requests/show.blade.php`,
`tests/Feature/AdvanceRentTest.php`, `tests/Feature/RentAccrualTest.php`

**Modified:** `config/billing.php`, `Lease`, `Payment`, `BillingService`,
`ManagerController`, `PaymentRecordController`, `LeaseBalanceController`,
`MaintenanceCostController`, `Tenant\PaymentController`,
`Tenant\TenantRequestController`, `routes/web/tenant.php`,
`tenant/payments.blade.php`, `tenant/request.blade.php`,
`manager/reports/show.blade.php`, `manager/requests/show.blade.php`,
`UnitController`, `RenewLeases`, `routes/schedule.php`, `docs/PROJECT-MAP.md`,
and the four existing test files.

`docs/PROJECT-MAP.md` §17–20 has the full detail.
