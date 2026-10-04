<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single payment row.
 *
 * Column-name gotchas (these have bitten this project before):
 *  - The payment date column is `pay_date`. There is NO `paid_at` column.
 *  - The Electricity/Water column is `utility_type`. There is NO `billing_type`
 *    column here — `billing_type` belongs to `utility_billing_proofs`.
 *    (The migration 2026_03_14_102957_add_billing_type_to_payments_table.php is
 *    misnamed: it actually adds `utility_type`.)
 *
 * Anything assigned through create()/fill() that is not in $fillable below is
 * discarded without error, so a typo here fails silently.
 */
class Payment extends Model
{
    protected $fillable = [
        'tenant_id',
        'lease_id',
        'pay_date',
        'payment_for',    // Rent, Advance Rent, Utilities, Deposit, Other
        'utility_type', // Electricity, Water and Other
        'pay_amount',
        // Advance-rent bookkeeping. Written only by BillingService when the
        // payment is applied — never by a controller off the request. See
        // 2026_08_29_000002_add_advance_fields_to_payments_table.php.
        'advance_months',
        'advance_amount',
        'pay_method',     // Cash, GCash, Bank Transfer
        'account_no',
        'reference_number',
        'pay_status',     // Paid, Pending, Overdue
        'accepted_at',    // v19 — when the office confirmed it; the invoice date
        'proof',
    ];

    protected $casts = [
        'pay_date' => 'datetime',
        'accepted_at' => 'datetime',
        'pay_amount' => 'decimal:2',
        'advance_months' => 'integer',
        'advance_amount' => 'decimal:2',
    ];

    /** Did any of this payment buy future rent, rather than clearing arrears? */
    public function isAdvanceRent(): bool
    {
        return $this->payment_for === 'Advance Rent';
    }

    /**
     * A one-line description of what an advance payment bought, for receipts
     * and history tables. Null for everything else.
     */
    public function advanceSummary(): ?string
    {
        if (!$this->isAdvanceRent()) {
            return null;
        }

        $months  = (int) ($this->advance_months ?? 0);
        $arrears = round((float) $this->pay_amount - (float) ($this->advance_amount ?? 0), 2);

        $parts = [];

        if ($months > 0) {
            $parts[] = $months . ' month' . ($months === 1 ? '' : 's') . ' ahead';
        }

        if ($arrears >= 0.01) {
            $parts[] = 'PHP ' . number_format($arrears, 2) . ' to arrears';
        }

        return $parts ? implode(' · ', $parts) : 'Held on account';
    }

    /* ==================================================================
     |  INVOICE (v19)
     * ================================================================== */

    /** Only a payment the office has accepted has an invoice. */
    public function hasInvoice(): bool
    {
        return $this->pay_status === 'Accepted';
    }

    /** INV-2026-000123: year accepted, then the payment id. Never stored. */
    public function invoiceNumber(): string
    {
        $when = $this->accepted_at ?? $this->updated_at ?? $this->created_at ?? now();

        return sprintf(
            '%s-%s-%06d',
            config('invoice.prefix', 'INV'),
            $when->format('Y'),
            $this->id
        );
    }

    public function invoiceDate()
    {
        return $this->accepted_at ?? $this->updated_at ?? $this->created_at;
    }

    // 🔗 Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    // Alias for clarity
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Which bills this payment was applied to, and for how much.
     *
     * Written only by App\Services\BillingService. A payment can settle
     * several bills and a bill can take several payments, so the relationship
     * needs its own table — see BillPaymentAllocation.
     */
    public function allocations()
    {
        return $this->hasMany(BillPaymentAllocation::class, 'payment_id');
    }

    /** Allocations that still count (not reversed). */
    public function liveAllocations()
    {
        return $this->allocations()->whereNull('reversed_at');
    }

    /** How much of this payment has actually landed on a bill. */
    public function allocatedTotal(): float
    {
        return round((float) $this->liveAllocations()->sum('amount'), 2);
    }

    /**
     * Money on this payment that did not settle a bill — it became tenant
     * credit or is awaiting refund. Only meaningful for utility payments.
     */
    public function unallocatedTotal(): float
    {
        return max(0.0, round((float) $this->pay_amount - $this->allocatedTotal(), 2));
    }
}
