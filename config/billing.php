<?php

/*
|--------------------------------------------------------------------------
| Billing rules
|--------------------------------------------------------------------------
| Every rule that decides "who owes what centavo" lives here, in one place,
| so it is consistent and testable. Nothing in app/ should hard-code these.
|
| Tests: tests/Feature/BillingTest.php
| Docs:  docs/BILLING-AND-COLLECTIONS.md
*/

return [

    /*
    | The smallest unit of currency we will ever charge. PHP centavo = 0.01.
    | Every per-tenant share is rounded to this before it is written to a bill.
    */
    'currency_precision' => 2,

    /*
    | How a shared bill (one meter, several tenants) is divided.
    | Used as the default when the manager does not pick one on the form.
    |
    |   equal      — every participating lease pays the same
    |   headcount  — proportional to occupants on the lease
    |   unit_size  — proportional to the unit's room_price (our stand-in for size)
    */
    'default_split_rule' => 'equal',

    'split_rules' => ['equal', 'headcount', 'unit_size'],

    /*
    | Rounding leaves a remainder. 1000.00 / 3 = 333.333... → 333.33 each,
    | leaving 0.01 unassigned. Somebody has to take it.
    |
    |   landlord          — the landlord absorbs it. The batch records the
    |                       shortfall in `rounding_absorbed`. Tenants are never
    |                       charged more than their rounded share. (default —
    |                       safest, no tenant can claim they were overcharged)
    |   designated_tenant — the remainder is added to one participant's share.
    |                       Which one is chosen deterministically: largest share
    |                       first, then lowest lease id. Never random, so the
    |                       same input always produces the same bills.
    |
    | Either way the remainder is recorded, never silently dropped.
    */
    'rounding_remainder' => 'landlord',

    /*
    | A tenant pays more than they owe. Never let the excess vanish.
    |
    |   credit — add it to users.user_credit, applied to the next bill
    |   refund — leave it on the payment flagged for manual refund
    */
    'overpayment' => 'credit',

    /*
    | Order in which a utility payment is applied across a tenant's open bills.
    | 'oldest_first' clears arrears before the current month, which is what
    | keeps a tenant from paying this month forever while an old bill ages.
    */
    'allocation_order' => 'oldest_first',

    /*
    | Utility bill due dates now follow payment_days above (the 10th of the
    | month after the billing month). This is kept only as a fallback for
    | code that still reads it; nothing in app/ does any more.
    */
    'default_bill_due_days' => 15,

    /*
    | One bill per tenant, per room, per type, per month (added v19).
    |
    | Issuing a second Electricity bill for the same tenant, room and month is
    | refused — including when the first one is an estimate. An estimate is
    | corrected by setting its final amount on the bill, not by issuing another
    | bill on top of it, which would charge the tenant twice.
    |
    | Repair is left out on purpose: those come from maintenance requests, and
    | two separate repairs in one month are two legitimate charges.
    */
    'one_bill_per_month_types' => ['Electricity', 'Water', 'Other'],

    /*
    | Payment days (added v19).
    |
    | Every month runs on the same calendar for every tenant:
    |
    |   opens_on_day   the day each month's rent is raised and the payment
    |                  window opens. 1 = the 1st.
    |   due_on_day     the last day to pay without a late penalty. 10 = the
    |                  10th. The penalty is assessed from the day AFTER this.
    |   min_notice_days
    |                  a charge is never due sooner than this many days after
    |                  it is raised. A tenant who moves in on the 8th is not
    |                  told their first rent is due on the 10th; it rolls to
    |                  the next payment day instead. Applies to rent and to
    |                  the default due date of utility bills.
    |
    | Utility bills default to the payment day of the month AFTER the month
    | they bill for (a "September 2026" electricity bill is due October 10),
    | or the next payment day after that if it has already passed. A manager
    | can still type a different due date on the form.
    |
    | Logic: App\Support\RentCalendar. Tests: tests/Feature/V19ChangesTest.php
    */
    'payment_days' => [
        'opens_on_day'    => 1,
        'due_on_day'      => 10,
        'min_notice_days' => 5,
    ],

    /*
    | Monthly rent accrual.
    |
    | Rent periods follow the calendar month: each month is raised on the 1st
    | (payment_days.opens_on_day) and due on the 10th (payment_days.due_on_day).
    |
    |   auto_charge         master switch. Off means no rent is ever raised
    |                       automatically and the manager does it by hand.
    |   prorate_partial_month
    |                       a lease that starts mid-month (move-in on the 17th)
    |                       is charged for the days up to the 1st, not a whole
    |                       month. Off charges that first part-month at the
    |                       full monthly rate. Also covers leases created before
    |                       v19, whose schedule ran from their start date: their
    |                       next charge is one prorated part-month up to the
    |                       1st, then they join the calendar like everyone else.
    |   max_catchup_months  the most a single run will raise for one lease.
    |                       A server whose cron has been dead for six months
    |                       must not silently bill six months in one night —
    |                       it stops at the cap and says so in the log, so a
    |                       person decides what to do about the rest.
    |
    | penalty: the 10% late fee, assessed by the nightly `rent:charge` command.
    |
    |   grace_days          extra days AFTER the due day before a penalty
    |                       applies. 0 = penalised from the 11th.
    |   once_per_cycle      one missed due date, one penalty.
    */
    'rent' => [
        'auto_charge'           => true,
        'prorate_partial_month' => true,
        'max_catchup_months'    => 3,

        'penalty' => [
            'enabled'        => true,
            'rate'           => 0.10,
            'grace_days'     => 0,
            'once_per_cycle' => true,
        ],
    ],

    /*
    | Advance rent — paying next month's rent (or the next six) before it is
    | due. Held on the lease, ring-fenced to rent, never spent on utilities.
    |
    |   max_months        the most a tenant may pay forward in one go. A cap
    |                     exists because prepaid rent is the landlord holding
    |                     the tenant's money: if the lease ends early it has to
    |                     be given back, and an uncapped balance turns a small
    |                     dispute into a large one. 12 keeps it inside the
    |                     lease term.
    |   settle_arrears_first
    |                     an advance payment clears what is already owed before
    |                     any of it buys a future month. Paying March forward
    |                     while February is unpaid is not a thing — the tenant
    |                     would still be in arrears and still be accruing the
    |                     10% penalty, having just handed over the money.
    */
    'advance_rent' => [
        'enabled'              => true,
        'max_months'           => 12,
        'settle_arrears_first' => true,
    ],

    /*
    | Estimated bills. When the provider's bill has not arrived by the time
    | rent is due, the manager can issue an estimate and reconcile it later.
    | Reconciliation NEVER edits the original bill — it writes an adjustment
    | row (see bill_adjustments) so the history stays intact.
    */
    'estimates' => [
        'enabled' => true,
        // Warn on the collections screen if an estimate is this old and still
        // not reconciled against a real provider bill.
        'stale_after_days' => 45,
    ],

    /*
    | Electricity billing by meter reading.
    |
    | The "Issue a bill" (single room) form takes a kWh reading instead of a
    | peso amount for Electricity and multiplies it by rate_per_kwh here —
    | the manager no longer does that math by hand, and it can't drift from
    | what this file says the rate is.
    |
    | Bed-Spacer rooms share one meter. When the billed lease's unit is type
    | 'Bed-Spacer', the computed total is divided evenly across every lease
    | currently active on that unit (see UtilityBillController::store()), so
    | issuing the same room reading against each tenant's lease charges each
    | of them their share rather than the full room total.
    */
    'electricity' => [
        'rate_per_kwh' => 18.00,
    ],
];
