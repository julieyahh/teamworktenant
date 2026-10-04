# Billing & Collections

How money is tracked, what happens when it does not all arrive, and what the
system does — and pointedly does not do — when a tenant will not pay.

Read this before changing anything in `app/Services/BillingService.php`,
`app/Services/CollectionsService.php`, `config/billing.php` or
`config/collections.php`.

---

## 1. Why a bill is separate from rent

The old model had one number per lease: `utility_balance`. A tenant either paid
it or they did not. That cannot express "rent paid, utilities partial", which is
the ordinary state of a tenant who is doing their best.

A utility charge is now its own row with its own status, due date and payment
history. Rent stays on the lease. They move independently.

| Charge | Lives on | Partial payment |
|---|---|---|
| Rent | `leases.rent_balance` | Allowed |
| Penalty | `leases.penalty_fee` | Allowed (paid before rent) |
| Utilities | `utility_billing_proofs` (one row per bill) | Allowed |
| Repairs | `utility_billing_proofs`, type `Repair` | Allowed |
| Deposit | `leases.deposit_balance` | **Not allowed** |

The deposit is the exception on purpose: it is security against damage, so a
half-funded deposit protects nobody, and it is what gates maintenance and
renovation access.

### The table is called `utility_billing_proofs`

It is a bill. The name is historical and was not changed because renaming would
break the `migrations` table key, every existing call site and all three copies
of the report filters. `App\Models\UtilityBillingProof` **is** the bill model.
The uploaded photo is one column on it.

---

## 2. The money model

```
amount              what we billed, at issue. NEVER edited once seen.
+ adjustments       signed corrections (BillAdjustment rows)
= totalDue()        what the tenant actually owes
- amount_paid       sum of live BillPaymentAllocation rows
= outstanding()     what is still owed, floored at 0
```

Two rules hold this together:

1. **`amount` is never rewritten.** Corrections are entries. A tenant who
   queries a charge can see the original figure, the correction and the reason.
2. **`amount_paid` is a cache of the allocations, not a source of truth.** Only
   `BillingService` writes it, via `syncPaidTotals()`. If the two ever
   disagree, the allocations are right.

### Statuses

`unpaid` → `partial` → `paid`, plus `void`.

A bill goes `partial` the moment any money lands on it and a balance remains.

---

## 3. Partial payments and arrears

A payment is allocated across open bills **oldest due date first**. This stops a
tenant paying the current month forever while a bill from March quietly ages
into something a lawyer has to look at.

One exception: if the payment names a utility type (Electricity/Water), only
bills of that type are touched. A tenant paying their water bill should not have
it swallowed by an older electricity arrear nobody told them about.

**Arrears carry forward implicitly.** There is no "carry forward" job. A bill
that is not fully paid simply stays open; anything past its due date is arrears.
`BillingService::arrearsFor($lease)` is the single query for this.

Rent: a partial payment reduces the balance but does **not** advance
`due_date`. The due date moves only when rent and penalty both reach zero. A
partial payment buys goodwill, not a month.

---

## 4. Splitting a shared bill

One provider bill across several rooms creates a `utility_bill_batches` row plus
one bill per lease. The batch records how it was split, so months later you can
still show a tenant exactly how their share was worked out.

### Split rules

| Rule | Weight per lease |
|---|---|
| `equal` | 1.0 each |
| `headcount` | `units.no_of_occupants` (minimum 1) |
| `unit_size` | `units.room_price` — our available stand-in for floor area |

If every weight is zero (a headcount split where no lease records occupants),
it falls back to an equal split rather than dividing by zero. A bill that cannot
be issued is worse than one split evenly.

### The rounding rule

**Shares always round DOWN.** 1000 ÷ 3 = 333.333… → 333.33 each, leaving one
centavo unassigned. Rounding down guarantees the shares can never sum to more
than the total, so no tenant is ever billed a centavo they do not owe.

The leftover goes somewhere explicit, set by `config('billing.rounding_remainder')`:

- **`landlord`** (default) — nobody is charged it. The shortfall is recorded on
  the batch as `rounding_absorbed`. Safest: no tenant can claim they were
  overcharged.
- **`designated_tenant`** — added to one participant's share. Chosen
  **deterministically**: largest share first, ties broken by lowest lease id.
  Never random, so the same input always produces the same bills.

Either way the remainder is recorded. It never silently disappears.

> If you switch the default policy after bills have been issued, existing
> batches keep the policy they were created with. That is intentional — a bill
> already shown to a tenant should not change because a setting moved.

---

## 5. Overpayment

Overpayment is never rejected and never dropped. Turning away a tenant handing
over a round PHP 1,000 against a PHP 987.50 bill helps nobody.

`config('billing.overpayment')`:

- **`credit`** (default) — the excess goes to `users.user_credit` and is applied
  to their next bill.
- **`refund`** — left on the payment, flagged for manual refund, and logged.

The same applies in reverse: reconciling an estimate downward can leave a bill
overpaid. The surplus is released back out as credit rather than sitting on a
settled bill.

---

## 6. Estimated bills

When the provider's bill has not arrived by the time rent is due, issue an
estimate (`is_estimated = true`). The tenant sees it marked as an estimate.

When the real bill lands, reconcile it. Reconciliation writes a
**`bill_adjustments` entry**, it does not edit the bill:

```
Estimate issued          1,200.00   (bills.amount — stays 1,200.00 forever)
Reconciled to actual     1,340.00   (adjustment: +140.00, reason recorded)
Total due                1,340.00
```

Unreconciled estimates are surfaced on the bills screen and are flagged as a
**blocker on the collections screen** — demanding payment on a figure you have
not verified is the fastest way to lose an otherwise sound case.

`config('billing.estimates.stale_after_days')` controls when an unreconciled
estimate starts being nagged about.

---

## 7. QA cases

Covered in `tests/Feature/BillingTest.php` and `tests/Unit/BillSplitTest.php`:

| Case | Test |
|---|---|
| Exact payment | `test_an_exact_payment_settles_the_bill` |
| Underpayment | `test_an_underpayment_leaves_the_bill_partial_not_unpaid` |
| Overpayment → credit | `test_an_overpayment_becomes_credit` |
| Overpayment → refund | `test_an_overpayment_can_be_flagged_for_refund_instead` |
| Zero-amount bill | `test_a_zero_amount_bill_is_settled_on_creation` |
| Zero-amount payment | `test_a_zero_amount_payment_does_nothing_and_does_not_crash` |
| Bill arriving after due date | `test_a_bill_issued_after_its_due_date_registers_as_arrears_immediately` |
| Estimate too low | `test_an_underestimated_bill_reconciles_with_an_extra_charge` |
| Estimate too high | `test_an_overestimated_bill_refunds_the_difference_as_credit` |
| Proration + remainder | `test_a_shared_bill_splits_with_the_landlord_absorbing_the_remainder` |
| Remainder determinism | `test_designation_is_deterministic_on_a_tie` |
| Oldest-first allocation | `test_a_payment_clears_the_oldest_bill_first` |
| Reversal | `test_reversing_a_payment_puts_the_balance_back` |
| Double-apply | `test_applying_the_same_payment_twice_does_nothing_the_second_time` |

Run them with `php artisan test`.

---

## 8. Collections — what this is and is not

**This is a filing cabinet, not an enforcement engine.**

The system records what the landlord did and when, and flags when a tenant has
crossed a threshold the landlord configured. A person makes every decision.

### What it will not do, by design

There is no route, button or scheduled job that:

- evicts a tenant or terminates a lease
- disconnects a utility
- changes a lock
- disables a tenant's login
- sends a demand on its own

Cutting utilities or changing locks to force payment is generally unlawful
regardless of how far behind a tenant is. `config('collections.allow_self_help_remedies')`
exists so that nobody can quietly switch such a thing on: no code path honours
it, and `CollectionsService` throws if it is ever set to `true`. There is a test
for this.

### Stages

| Stage | Meaning |
|---|---|
| `current` | Nothing owed, or not yet due |
| `overdue` | Past due — a reminder is usually enough |
| `demand_due` | Old enough that a formal written demand is appropriate |
| `grace_expired` | A served demand's notice period ran out with no payment |
| `eligible_for_formal_action` | **Refer to counsel.** The system has no next step. |

`minimum_actionable_arrears` keeps a stray centavo from a rounding remainder
from ever putting a tenant into collections.

### The clock starts at service, not at drafting

This is the single most important rule in the collections code.

A demand that was drafted but never recorded as delivered **never expires**,
however old it is. An undelivered notice proves nothing, and treating it as
expired would push a tenant into a later stage on the strength of a document
they may never have received. `PaymentDemand::graceHasExpired()` returns false
without a `delivered_at`, and `comply_by` is recomputed from the delivery date
when service is recorded.

Undelivered notices are counted on the collections dashboard for exactly this
reason.

### What gets recorded

`payment_demands` freezes the amounts at issue — the record must still read the
same in six months after the balance has moved. It stores the type, the notice
period, the compliance date, the delivery channel and reference (the field that
proves service), a scan of the served copy, and the outcome.

The system drafts the wording. **It does not send anything.** Send it yourself
through a channel you can prove, then record the service details.

---

## 9. Legal configuration — needs sign-off

`config/collections.php` contains grace periods and thresholds that are
**placeholders**. They are not legal advice and were not drafted by a lawyer.

Before this goes live, have Philippine counsel confirm and then record who
confirmed them in the header of that file:

- the demand/notice period required before filing — this is the one that gets a
  case thrown out when it is too short
- whether unpaid **utilities** are a ground for action under your lease, or only
  unpaid rent
- whether the unit falls under the Rent Control Act's coverage — the rent
  threshold has been revised more than once, so no figure is hard-coded anywhere
  in this codebase on purpose
- the correct venue and form for an unlawful detainer filing

The usual starting points are the Civil Code lease provisions (Arts. 1642–1688)
and RA 9653 as extended, but coverage and periods change. Confirm before
encoding.

---

## 10. Maintenance costs

`maintenance_work_items` holds labour and materials as line items. Totals roll
up onto the request via `MaintenanceCostService::recalculate()` — the only thing
that writes `labor_cost`, `material_cost`, `total_cost` and
`tenant_charge_amount`.

### Nothing defaults

`cost_responsibility` starts as `undetermined` and stays there until a person
chooses. Whether a repair is fair wear and tear or tenant damage is a judgement
call with a lease contract behind it; getting it wrong in software means
silently billing someone for something that was never theirs.

Charging a tenant any part of a repair **requires a written reason**. That
reason is what the tenant sees if they query the charge.

| Setting | Tenant pays |
|---|---|
| `landlord` | nothing |
| `tenant` | the whole cost |
| `shared` | `tenant_share_percent` of it (1–99) |
| `undetermined` | nothing, and it is flagged for a decision |

Once charged, the costs lock. The tenant has been shown that figure.

### The charge becomes a normal bill

Not a rent adjustment. It gets its own due date and status, the tenant can pay
it in parts like anything else, and it reads as `Repair` on their ledger rather
than as unexplained rent. The itemised breakdown goes out in the notification.

---

## 11. Where to look when something is wrong

| Symptom | File |
|---|---|
| A bill's paid total looks wrong | `UtilityBillingProof::syncPaidTotals()` — check the allocations |
| A payment went to the wrong bill | `BillingService::applyToUtilityBills()` |
| Overpayment vanished | `config('billing.overpayment')`, then `BillingService::grantCredit()` |
| Shares do not add up | `BillingService::splitAmount()` and the batch's `rounding_absorbed` |
| A reconciliation changed the original amount | It should not. Check `bill_adjustments` |
| Rent due date moved on a partial payment | `BillingService::applyToRent()` |
| `lease.utility_balance` disagrees with the bills | `BillingService::syncLeaseUtilityBalance()` — the column is a cache |
| A tenant reached a stage they should not have | `CollectionsService::assess()`, then check `delivered_at` on the demand |
| Repair total does not match the line items | `MaintenanceCostService::recalculate()` |

---

## v19 additions

- **Payment days.** Rent periods are calendar months: raised on the 1st, due
  the 10th, penalised from the 11th. Short first periods are prorated. Logic in
  `App\Support\RentCalendar`; settings in `config/billing.php → payment_days`.
- **Bill uniqueness.** One Electricity/Water/Other bill per tenant, room and
  month (`BillingService::findDuplicateBill`). Repair and voided bills excluded.
- **Payable vs open.** `UtilityBillingProof::payable()` = open minus estimates
  with no final amount. Allocation, utility_balance, arrears, the due monitor
  and move-out settlement all use `payable()`. `open()` still includes them.
- **Invoices.** `App\Services\InvoiceService` builds lines from the payment's
  live allocations / advance fields, never from current balances.
