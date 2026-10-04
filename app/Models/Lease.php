<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lease extends Model
{
    use HasFactory;

    /**
     * Rent goes up by this much on every yearly renewal. 0.05 = 5%.
     */
    public const RENT_INCREASE_RATE = 0.05;

    protected $fillable = [
        'user_id',
        'unit_id',
        'lea_start_date',
        'lea_end_date',
        'lea_status',
        'room_no',
        'bed_number',
        'lea_terms',
        'rent_balance',
        'advance_rent_balance',
        'rent_paid_through',
        'next_rent_charge_on',
        'last_penalty_due_date',
        'utility_balance',
        'rental_payment_status',
        'utility_payment_status',
        'move_out_requested',
        'move_out_reason',
        'deposit_balance',
        'paid_date',
        'penalty_fee',
        'due_date',
        'damage_details',
        'damage_cost',
        'refund_amount',
        'renewal_requested',
        'renewal_requested_at',
        'monthly_rent',
        'renewal_count',
        'last_renewed_at',
    ];

    // Make sure date columns are cast to Carbon
    protected $casts = [
        'lea_start_date'  => 'date',
        'lea_end_date'    => 'date',
        'paid_date'       => 'date',
        'due_date'        => 'date',
        'last_renewed_at' => 'date',
        'renewal_requested' => 'boolean',
        'renewal_requested_at' => 'datetime',
        'rent_paid_through' => 'date',
        'next_rent_charge_on' => 'date',
        'last_penalty_due_date' => 'date',
        'advance_rent_balance' => 'decimal:2',
    ];

    // Lease belongs to a tenant
    public function tenant()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Lease belongs to a unit
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    // Lease can have many payments
    public function payments()
    {
        return $this->hasMany(Payment::class, 'lease_id');
    }

    // Lease can have many maintenance requests
    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'tenant_id', 'user_id');
    }

    public function renovationRequests()
    {
        return $this->hasMany(RenovationRequest::class, 'lease_id');
    }

    /**
     * Every time this lease was rolled over for another year.
     *
     * `renewal_count` says how many times; this says when, by whom, and what
     * the rent went from and to.
     */
    public function renewals()
    {
        return $this->hasMany(LeaseRenewal::class, 'lease_id');
    }

    /**
     * Every month of rent ever charged on this lease.
     *
     * rent_balance is the running total; this is what it is made of. Only
     * BillingService::chargeDueRent() writes it.
     */
    public function rentCharges()
    {
        return $this->hasMany(RentCharge::class, 'lease_id');
    }

    /**
     * Utility bills raised against this lease.
     *
     * The model is UtilityBillingProof and the table is utility_billing_proofs
     * for historical reasons — read both as "bill". See that class's docblock.
     */
    public function utilityBills()
    {
        return $this->hasMany(UtilityBillingProof::class, 'lease_id');
    }

    /** Every demand for payment made against this lease. */
    public function paymentDemands()
    {
        return $this->hasMany(PaymentDemand::class, 'lease_id');
    }

    /* ==================================================================
     |  RENT / BALANCE HELPERS
     * ================================================================== */

    /**
     * The rent this lease is actually charged. Falls back to the unit list
     * price for legacy leases that predate the monthly_rent column.
     */
    public function currentRent(): float
    {
        if ($this->monthly_rent !== null && (float) $this->monthly_rent > 0) {
            return (float) $this->monthly_rent;
        }

        return (float) ($this->unit->room_price ?? 0);
    }

    /** What the rent becomes at the next renewal. */
    public function nextRenewalRent(): float
    {
        return round($this->currentRent() * (1 + self::RENT_INCREASE_RATE), 2);
    }

    /** Total money still owed on this lease. */
    public function outstandingBalance(): float
    {
        return (float) ($this->deposit_balance ?? 0)
             + (float) ($this->rent_balance ?? 0)
             + (float) ($this->utility_balance ?? 0)
             + (float) ($this->penalty_fee ?? 0);
    }

    public function hasOutstandingBalance(): bool
    {
        return $this->outstandingBalance() > 0;
    }

    /* ==================================================================
     |  RENT CHARGES
     * ================================================================== */

    /**
     * Which charged months are settled, which are not, and by how much.
     *
     * Settlement is DERIVED rather than stored. Rent payments come off
     * `rent_balance` as a lump, not allocated to a specific month the way
     * utility payments are allocated to bills, so there is no per-charge paid
     * flag to read. Instead: everything charged, less what is still owed, is
     * what has been paid — and that is applied to the charges oldest first,
     * which is the same order BillingService uses everywhere else.
     *
     * Always right, where a stored flag would be one more thing to keep in
     * step with the balance.
     *
     * @return array<int,array{charge:RentCharge, settled:float, outstanding:float}>
     */
    public function rentChargeLedger(): array
    {
        $charges = $this->rentCharges()->oldestFirst()->get();

        $charged = round((float) $charges->sum('amount'), 2);
        $owed    = max(0.0, round((float) ($this->rent_balance ?? 0), 2));
        $paid    = max(0.0, round($charged - $owed, 2));

        $ledger = [];

        foreach ($charges as $charge) {
            $amount  = round((float) $charge->amount, 2);
            $settled = round(min($amount, $paid), 2);
            $paid    = round($paid - $settled, 2);

            $ledger[] = [
                'charge'      => $charge,
                'settled'     => $settled,
                'outstanding' => round($amount - $settled, 2),
            ];
        }

        return $ledger;
    }

    /** The oldest charged month that is not fully paid, if any. */
    public function oldestUnsettledRentCharge(): ?RentCharge
    {
        foreach ($this->rentChargeLedger() as $line) {
            if ($line['outstanding'] >= 0.01) {
                return $line['charge'];
            }
        }

        return null;
    }

    /**
     * Is this lease on the automatic monthly schedule?
     *
     * A terminated lease, or one the backfill skipped, is not — and must not
     * be quietly picked up by the nightly command.
     */
    public function isOnRentSchedule(): bool
    {
        return $this->next_rent_charge_on !== null
            && in_array(strtolower((string) $this->lea_status), ['active', 'pending'], true);
    }

    /** Months of rent that have fallen due to be raised and have not been. */
    public function rentChargesOutstanding(?Carbon $asOf = null): int
    {
        if (! $this->isOnRentSchedule()) {
            return 0;
        }

        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $next = Carbon::parse($this->next_rent_charge_on)->startOfDay();

        // Walked period by period rather than counted in months, because
        // periods are calendar-aligned (v19): a lease due on the 17th has a
        // short period to the 1st and then a whole month, and both may have
        // started by today. Bounded so bad data can never loop forever.
        $count = 0;

        while ($next->lte($asOf) && $count < 240) {
            $count++;
            $next = \App\Support\RentCalendar::periodEnd($next);
        }

        return $count;
    }

    public function hasUnpaidDeposit(): bool
    {
        return (float) ($this->deposit_balance ?? 0) > 0;
    }

    /* ==================================================================
     |  ADVANCE RENT
     |
     |  Money paid ahead of the month it covers. Ring-fenced to rent: it is
     |  held on the lease rather than in users.user_credit precisely so a
     |  utility bill can never eat it. See the migration
     |  2026_08_29_000001_add_advance_rent_to_leases_table.php.
     * ================================================================== */

    /** Rent money on account that has not yet been consumed by a due month. */
    public function advanceRentBalance(): float
    {
        return round((float) ($this->advance_rent_balance ?? 0), 2);
    }

    public function hasAdvanceRent(): bool
    {
        return $this->advanceRentBalance() >= 0.01;
    }

    /**
     * How many whole months of rent the balance on account still covers.
     *
     * Computed against the CURRENT rent, not the rent at the time of payment.
     * If the rent went up at a renewal, three months paid at the old rate no
     * longer buys three months, and telling the tenant otherwise would be a
     * promise the system cannot keep.
     */
    public function advanceMonthsCovered(): int
    {
        $rent = $this->currentRent();

        if ($rent <= 0) {
            return 0;
        }

        return (int) floor($this->advanceRentBalance() / $rent);
    }

    /** Is the rent covered past today? */
    public function isRentPaidInAdvance(): bool
    {
        return $this->rent_paid_through
            && Carbon::parse($this->rent_paid_through)->endOfDay()->isFuture();
    }

    /**
     * Draw the advance balance down against rent and penalties that are now
     * actually due.
     *
     * Call this whenever rent is charged to the lease — the point of an advance
     * is that the tenant does not have to do anything when the next month comes
     * round. Returns what was consumed.
     *
     * Deliberately pure balance arithmetic with no service dependency, so
     * Lease::renewForAnotherYear() can call it without reaching out of the
     * model. BillingService::consumeAdvanceRent() is a thin wrapper for
     * callers that already hold the service.
     */
    public function consumeAdvanceRent(): float
    {
        if ($this->advanceRentBalance() < 0.01) {
            return 0.0;
        }

        $p = $this->settlementPreview();

        $this->penalty_fee          = $p['unpaid_penalty'];
        $this->rent_balance         = $p['unpaid_rent'];
        $this->advance_rent_balance = $p['advance_refund'];

        return $p['advance_used'];
    }

    /**
     * What consumeAdvanceRent() WOULD do, without doing it.
     *
     * Added in v17 because the two move-out modals were showing the manager a
     * settlement the server was never going to produce. They read
     * `rent_balance` and `penalty_fee` straight off the lease, but
     * ManagerController draws the advance down against both before it reads
     * them — so a tenant three months paid ahead with one month of arrears was
     * displayed as owing that month AND getting a smaller refund, then the
     * server quietly wiped the arrears and refunded the rest of the advance on
     * top. The manager approved one number and the tenant was told another.
     *
     * consumeAdvanceRent() is now a thin applier over this, so the preview and
     * the real thing cannot drift apart — there is only one copy of the
     * arithmetic and one order of precedence (penalty, then rent).
     *
     * Utilities are deliberately absent from the draw-down: advance rent is
     * ring-fenced to rent. They are returned here only so callers can total
     * the settlement in one read.
     *
     * Pure. Touches nothing, saves nothing.
     *
     * @return array{
     *   advance_before: float, advance_used: float, advance_refund: float,
     *   unpaid_rent: float, unpaid_penalty: float, unpaid_utilities: float,
     *   unpaid_total: float
     * }
     */
    public function settlementPreview(): array
    {
        $available = $this->advanceRentBalance();
        $penalty   = round((float) ($this->penalty_fee ?? 0), 2);
        $rent      = round((float) ($this->rent_balance ?? 0), 2);
        $utilities = round($this->openUtilityTotal(), 2);

        $takePenalty = 0.0;
        if ($penalty > 0 && $available >= 0.01) {
            $takePenalty = round(min($penalty, $available), 2);
            $available   = round($available - $takePenalty, 2);
        }

        $takeRent = 0.0;
        if ($rent > 0 && $available >= 0.01) {
            $takeRent  = round(min($rent, $available), 2);
            $available = round($available - $takeRent, 2);
        }

        $rentLeft    = round($rent - $takeRent, 2);
        $penaltyLeft = round($penalty - $takePenalty, 2);

        return [
            'advance_before'   => $this->advanceRentBalance(),
            'advance_used'     => round($takePenalty + $takeRent, 2),
            'advance_refund'   => $available,
            'unpaid_rent'      => $rentLeft,
            'unpaid_penalty'   => $penaltyLeft,
            'unpaid_utilities' => $utilities,
            'unpaid_total'     => round($rentLeft + $penaltyLeft + $utilities, 2),
        ];
    }

    /**
     * Total still owed across open utility bills.
     *
     * Prefer this over reading `utility_balance` directly. That column is a
     * cache refreshed by BillingService::syncLeaseUtilityBalance(); this sums
     * the bills themselves and is always right.
     */
    public function openUtilityTotal(): float
    {
        $total = 0.0;

        // payable(): an estimate still waiting for its final amount is not
        // owed yet and cannot be paid (v19). See estimatedUtilityTotal().
        foreach ($this->utilityBills()->payable()->get() as $bill) {
            $total = round($total + $bill->outstanding(), 2);
        }

        return $total;
    }

    /**
     * Estimated bills the manager has not yet set the final amount on. Shown
     * to the tenant as "coming", never added to what they owe.
     */
    public function estimatedUtilityTotal(): float
    {
        $total = 0.0;

        foreach ($this->utilityBills()->awaitingFinalAmount()->get() as $bill) {
            $total = round($total + $bill->outstanding(), 2);
        }

        return $total;
    }

    public function hasUnfinalisedEstimates(): bool
    {
        return $this->utilityBills()->awaitingFinalAmount()->exists();
    }

    /** Bills past their due date and still open — the carried-forward balance. */
    public function utilityArrearsTotal(): float
    {
        $total = 0.0;

        foreach ($this->utilityBills()->arrears()->get() as $bill) {
            $total = round($total + $bill->outstanding(), 2);
        }

        return $total;
    }

    /** Bills with some money on them but not enough. */
    public function hasPartialUtilityBills(): bool
    {
        return $this->utilityBills()
            ->where('status', UtilityBillingProof::STATUS_PARTIAL)
            ->exists();
    }

    /**
     * A one-line status per charge type, so the UI never has to show a single
     * all-or-nothing "paid/unpaid" flag for a tenant who has paid their rent
     * and half their electricity.
     *
     * @return array{rent:string, advance:?string, utilities:string, deposit:string}
     */
    public function chargeStatuses(): array
    {
        $utilityOpen    = $this->openUtilityTotal();
        $utilityPartial = $this->hasPartialUtilityBills();

        return [
            // "Advance" is deliberately distinct from "Paid". Both mean nothing
            // is owed today, but only one of them means the tenant is also
            // covered for next month — and the office needs to see which.
            'rent' => match (true) {
                (float) ($this->rent_balance ?? 0) > 0 => 'Unpaid',
                $this->hasAdvanceRent()                => 'Advance',
                default                                => 'Paid',
            },
            'advance' => $this->hasAdvanceRent()
                ? number_format($this->advanceRentBalance(), 2)
                : null,
            'utilities' => match (true) {
                $utilityOpen <= 0 => 'Paid',
                $utilityPartial   => 'Partial',
                default           => 'Unpaid',
            },
            'deposit' => $this->hasUnpaidDeposit() ? 'Unpaid' : 'Paid',
        ];
    }

    public function isLive(): bool
    {
        return in_array(strtolower((string) $this->lea_status), ['active', 'approved', 'pending'], true);
    }

    /**
     * Is this lease at or past its end date and therefore due for renewal?
     */
    public function isDueForRenewal(): bool
    {
        if (strtolower((string) $this->lea_status) !== 'active') {
            return false;
        }

        if ($this->move_out_requested) {
            return false;
        }

        if (!$this->lea_end_date) {
            return false;
        }

        return Carbon::parse($this->lea_end_date)->startOfDay()->lte(Carbon::today());
    }

    /**
     * Can the tenant ask for this lease to be renewed now?
     *
     * Active, no move-out pending, not already asked, and inside the request
     * window before the end date (config leases.renewal_request_window_days)
     * — or already past it.
     */
    public function canRequestRenewal(): bool
    {
        if (strtolower((string) $this->lea_status) !== 'active') {
            return false;
        }

        if ($this->move_out_requested || $this->renewal_requested) {
            return false;
        }

        if (! $this->lea_end_date) {
            return false;
        }

        $window = max(0, (int) config('leases.renewal_request_window_days', 60));

        return Carbon::parse($this->lea_end_date)->startOfDay()
            ->lte(Carbon::today()->addDays($window));
    }

    /** The date from which the tenant may ask to renew. */
    public function renewalRequestOpensOn(): ?Carbon
    {
        if (! $this->lea_end_date) {
            return null;
        }

        $window = max(0, (int) config('leases.renewal_request_window_days', 60));

        return Carbon::parse($this->lea_end_date)->startOfDay()->subDays($window);
    }

    /**
     * Has the tenant asked to renew, so the manager may act on it? (v19)
     *
     * Renewal is a contract change with a 5% rent increase, so it starts with
     * the tenant. The manager's Renew action stays locked until they ask.
     */
    public function isAwaitingRenewal(): bool
    {
        return (bool) $this->renewal_requested
            && strtolower((string) $this->lea_status) === 'active'
            && ! $this->move_out_requested;
    }

    /* ==================================================================
     |  RENEWAL
     * ================================================================== */

    /**
     * Roll this lease over for another year and apply the 5% increase.
     *
     * Returns a summary array so the caller can build the notification
     * message, or null if the lease was not renewable.
     *
     * Every successful renewal also writes a LeaseRenewal row. That write
     * lives here rather than in the two callers (ManagerController@renewLease
     * and the leases:renew command) on purpose: if it sat in the callers, a
     * third caller added later would silently renew without leaving a trace,
     * and the history would be quietly incomplete rather than obviously
     * broken.
     *
     * @param  string  $source  LeaseRenewal::SOURCE_MANUAL or SOURCE_SCHEDULED
     * @param  int|null $renewedBy  User id of the manager who clicked Renew
     */
    public function renewForAnotherYear(
        ?Carbon $startFrom = null,
        string $source = LeaseRenewal::SOURCE_MANUAL,
        ?int $renewedBy = null
    ): ?array {
        if (strtolower((string) $this->lea_status) !== 'active') {
            return null;
        }

        $oldRent = $this->currentRent();
        $newRent = round($oldRent * (1 + self::RENT_INCREASE_RATE), 2);

        // Captured before the dates are overwritten below — this is the end
        // date the renewal is extending FROM, and it is the single most useful
        // column in the history when a tenant disputes a term.
        $previousEnd = $this->lea_end_date ? Carbon::parse($this->lea_end_date) : null;

        $newStart = $startFrom
            ? $startFrom->copy()->startOfDay()
            : ($this->lea_end_date ? Carbon::parse($this->lea_end_date)->startOfDay() : Carbon::today());

        $newEnd = $newStart->copy()->addYear();

        $this->monthly_rent   = $newRent;
        $this->lea_start_date = $newStart;
        $this->lea_end_date   = $newEnd;
        $this->lea_terms      = sprintf(
            '1 Year Lease (%s - %s)',
            $newStart->format('M d, Y'),
            $newEnd->format('M d, Y')
        );
        $this->renewal_count   = (int) ($this->renewal_count ?? 0) + 1;
        $this->last_renewed_at = Carbon::today();

        // A renewal no longer bills a month of rent.
        //
        // It used to: `rent_balance += newRent`, because renewal was the ONLY
        // recurring rent charge in the system. Now that `rent:charge` raises
        // every month, doing it here as well would bill the renewal month
        // twice — once by the renewal and once by the scheduler, at the same
        // new rate, a fortnight apart.
        //
        // A renewal is a contract event: it changes the rate, the term and the
        // dates, and leaves billing to the thing whose job billing is. The
        // next scheduled charge picks up the new rate automatically, because
        // chargeDueRent() reads currentRent() at the moment it runs.
        //
        // Advance rent is still drawn down against anything already owed, at
        // the NEW rate — so three months bought at the old rate may now only
        // cover two and a bit. That is the honest outcome; pretending
        // otherwise would mean the increase never actually applied.
        $advanceUsed = $this->consumeAdvanceRent();

        $this->renewal_requested    = false;
        $this->renewal_requested_at = null;

        $this->save();

        $record = LeaseRenewal::create([
            'lease_id'          => $this->id,
            'tenant_id'         => $this->user_id,
            'unit_id'           => $this->unit_id,
            'room_no'           => $this->room_no,
            'renewal_no'        => $this->renewal_count,
            'previous_rent'     => $oldRent,
            'new_rent'          => $newRent,
            'increase_amount'   => round($newRent - $oldRent, 2),
            'increase_rate'     => self::RENT_INCREASE_RATE,
            'previous_end_date' => $previousEnd,
            'new_start_date'    => $newStart,
            'new_end_date'      => $newEnd,
            'source'            => $source,
            'renewed_by'        => $renewedBy,
        ]);

        return [
            'old_rent'      => $oldRent,
            'new_rent'      => $newRent,
            'increase'      => round($newRent - $oldRent, 2),
            'advance_used'  => $advanceUsed,
            'start_date'    => $newStart,
            'end_date'      => $newEnd,
            'renewal_count' => $this->renewal_count,
            'record'        => $record,
        ];
    }
}
