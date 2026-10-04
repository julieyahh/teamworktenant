<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How much of one payment was applied to one bill.
 *
 * Written only by App\Services\BillingService. Nothing else should create or
 * edit these rows — the sum of live allocations for a bill IS that bill's
 * amount_paid, and the two drifting apart is the failure mode this whole
 * design exists to prevent.
 */
class BillPaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'bill_id',
        'lease_id',
        'amount',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function bill()
    {
        return $this->belongsTo(UtilityBillingProof::class, 'bill_id');
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    /** Allocations that still count towards a bill's paid total. */
    public function scopeLive($query)
    {
        return $query->whereNull('reversed_at');
    }
}
