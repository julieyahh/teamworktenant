<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A utility bill.
 *
 * The class is still called UtilityBillingProof and the table is still
 * `utility_billing_proofs`, because renaming either would break the migrations
 * table key, every existing call site and all three copies of the report
 * filters. Read it as "the bill" — the uploaded image is just one column on it.
 *
 * Column-name trap (docs/PROJECT-MAP.md §4): the Electricity/Water column is
 * `billing_type` HERE, but `utility_type` on `payments`. Two names for the same
 * idea, and mixing them up fails silently.
 *
 * MONEY MODEL
 *   amount        the bill. For an estimate this is rewritten to the actual
 *                 figure when the manager sets it (v20); the estimate the
 *                 tenant first saw is kept in estimated_amount (and
 *                 estimated_kwh for electricity).
 *   adjustments   signed manual corrections. + charge, - credit.
 *                 Reconciliation rows are history only and not summed.
 *   totalDue()    amount + manual adjustments. THIS is what the tenant owes.
 *   amount_paid   sum of live BillPaymentAllocation rows. Written only by
 *                 App\Services\BillingService.
 *   outstanding() totalDue() - amount_paid, floored at 0.
 *
 * STATUSES: unpaid | partial | paid | void
 * A bill goes 'partial' the moment any money lands on it and a balance remains.
 * That is the point of the whole design: "rent paid, utilities partial" instead
 * of one all-or-nothing flag.
 */
class UtilityBillingProof extends Model
{
    use HasFactory;

    public const STATUS_UNPAID  = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID    = 'paid';
    public const STATUS_VOID    = 'void';

    protected $fillable = [
        'tenant_id',
        'lease_id',
        'batch_id',
        'maintenance_request_id',
        'share_basis_value',
        'rounding_adjustment',
        'file_path',
        'billing_month',
        'due_date',
        'is_estimated',
        'reconciled_at',
        'billing_type',
        'amount',
        'estimated_amount',
        'kwh_reading',
        'estimated_kwh',
        'kwh_rate',
        'amount_paid',
        'notes',
        'status',
        'voided_at',
        'void_reason',
        'created_by',
    ];

    protected $casts = [
        'amount'              => 'decimal:2',
        'estimated_amount'    => 'decimal:2',
        'kwh_reading'         => 'decimal:2',
        'estimated_kwh'       => 'decimal:2',
        'kwh_rate'            => 'decimal:2',
        'amount_paid'         => 'decimal:2',
        'rounding_adjustment' => 'decimal:2',
        'share_basis_value'   => 'decimal:4',
        'due_date'            => 'date',
        'is_estimated'        => 'boolean',
        'reconciled_at'       => 'datetime',
        'voided_at'           => 'datetime',
    ];

    /* ==================================================================
     |  RELATIONSHIPS
     * ================================================================== */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(UtilityBillBatch::class, 'batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The repair this bill charges for (Repair bills only). */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function adjustments()
    {
        return $this->hasMany(BillAdjustment::class, 'bill_id');
    }

    public function allocations()
    {
        return $this->hasMany(BillPaymentAllocation::class, 'bill_id');
    }

    /* ==================================================================
     |  MONEY
     * ================================================================== */

    /**
     * Signed total of the corrections that sit ON TOP of the bill amount.
     *
     * v20: reconciliation entries are excluded. Setting the actual amount
     * rewrites `amount` itself, so the reconciliation row is a history line
     * (estimate -> actual, who, when, why) — counting it here as well would
     * charge the difference twice.
     */
    public function adjustmentTotal(): float
    {
        return round((float) $this->adjustments()
            ->where('type', '!=', BillAdjustment::TYPE_RECONCILIATION)
            ->sum('amount'), 2);
    }

    /** Was this bill an estimate that has since been set to the actual figure? */
    public function wasReconciled(): bool
    {
        return $this->reconciled_at !== null && $this->estimated_amount !== null;
    }

    /** What the tenant actually owes: the bill amount plus manual corrections. */
    public function totalDue(): float
    {
        return round((float) ($this->amount ?? 0) + $this->adjustmentTotal(), 2);
    }

    /** Still owed. Never negative — overpayment becomes credit, not a negative bill. */
    public function outstanding(): float
    {
        if ($this->isVoid()) {
            return 0.0;
        }

        return max(0.0, round($this->totalDue() - (float) ($this->amount_paid ?? 0), 2));
    }

    public function isSettled(): bool
    {
        return $this->outstanding() <= 0.0;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID || $this->voided_at !== null;
    }

    public function isOverdue(): bool
    {
        if ($this->isSettled() || $this->isVoid() || !$this->due_date) {
            return false;
        }

        // An estimate cannot be paid yet (v19), so it cannot be late either.
        // Its clock starts when the manager sets the final amount.
        if ($this->isUnreconciledEstimate()) {
            return false;
        }

        return Carbon::parse($this->due_date)->endOfDay()->isPast();
    }

    /** Whole days past due. 0 when not overdue. */
    public function daysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return (int) Carbon::parse($this->due_date)->endOfDay()->diffInDays(Carbon::now());
    }

    /**
     * An estimate not yet reconciled against the real provider bill. These are
     * the rows that must not be treated as final.
     */
    public function isUnreconciledEstimate(): bool
    {
        return (bool) $this->is_estimated && $this->reconciled_at === null;
    }

    /**
     * Can the tenant pay this bill now?
     *
     * Not when it is void or settled, and not while it is an estimate the
     * manager has not yet corrected to the real amount (v19). An estimate is
     * shown to the tenant so they know it is coming, but money only lands on
     * the final figure — otherwise a tenant pays the guess, the real bill is
     * different, and the difference has to be chased or refunded.
     */
    public function isPayable(): bool
    {
        return ! $this->isVoid()
            && ! $this->isUnreconciledEstimate()
            && $this->outstanding() >= 0.01;
    }

    public function isStaleEstimate(): bool
    {
        if (!$this->isUnreconciledEstimate()) {
            return false;
        }

        $days = (int) config('billing.estimates.stale_after_days', 45);

        return Carbon::parse($this->created_at)->addDays($days)->isPast();
    }

    /**
     * Recompute amount_paid from live allocations and set the matching status.
     *
     * Allocations are the source of truth; amount_paid is a cache of them. Call
     * after any allocation change. Does not save — the caller decides, so this
     * can run inside a transaction alongside other writes.
     */
    public function syncPaidTotals(): self
    {
        if ($this->isVoid()) {
            return $this;
        }

        $paid = (float) $this->allocations()->whereNull('reversed_at')->sum('amount');

        $this->amount_paid = round($paid, 2);

        $due = $this->totalDue();

        if ($paid <= 0) {
            $this->status = self::STATUS_UNPAID;
        } elseif (round($paid, 2) >= round($due, 2)) {
            $this->status = self::STATUS_PAID;
        } else {
            $this->status = self::STATUS_PARTIAL;
        }

        return $this;
    }

    /* ==================================================================
     |  SCOPES
     * ================================================================== */

    /** Bills with money still owed on them. */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_PAID, self::STATUS_VOID]);
    }

    /**
     * Open bills a tenant can actually pay: excludes estimates that are still
     * waiting for their final amount (v19).
     */
    public function scopePayable($query)
    {
        return $query->open()->where(function ($q) {
            $q->where('is_estimated', false)
              ->orWhereNull('is_estimated')
              ->orWhereNotNull('reconciled_at');
        });
    }

    /** Estimates still waiting for the manager to set the final amount. */
    public function scopeAwaitingFinalAmount($query)
    {
        return $query->open()
            ->where('is_estimated', true)
            ->whereNull('reconciled_at');
    }

    public function scopeForLease($query, $leaseId)
    {
        return $query->where('lease_id', $leaseId);
    }

    /** Open bills whose due date has passed — the carried-forward balance. */
    public function scopeArrears($query)
    {
        // payable(): an unfinalised estimate is never arrears (v19).
        return $query->payable()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today());
    }

    /**
     * Oldest debt first — the allocation order. Stops a tenant paying the
     * current month forever while a bill from March quietly ages into
     * something a lawyer has to look at.
     */
    public function scopeOldestFirst($query)
    {
        return $query->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID    => 'Paid',
            self::STATUS_PARTIAL => 'Partially paid',
            self::STATUS_VOID    => 'Void',
            default              => $this->isUnreconciledEstimate() ? 'Estimate — awaiting final amount' : 'Unpaid',
        };
    }
}
