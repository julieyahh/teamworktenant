<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A correction to a bill, as an entry rather than an edit.
 *
 * Manual corrections (correction / goodwill / rounding) are added on top of
 * the bill amount by UtilityBillingProof::totalDue().
 *
 * Reconciliation rows (v20) are a HISTORY line only: setting the actual
 * amount rewrites the bill's `amount` itself, and this row records the
 * estimate -> actual difference, who did it and why. They are NOT summed
 * into totalDue() — that would charge the difference twice.
 *
 *   amount > 0  additional charge (we under-estimated)
 *   amount < 0  credit            (we over-estimated)
 */
class BillAdjustment extends Model
{
    use HasFactory;

    public const TYPE_RECONCILIATION = 'reconciliation';
    public const TYPE_CORRECTION     = 'correction';
    public const TYPE_GOODWILL       = 'goodwill';
    public const TYPE_ROUNDING       = 'rounding';

    protected $fillable = [
        'bill_id',
        'type',
        'amount',
        'reason',
        'actual_amount',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'actual_amount' => 'decimal:2',
    ];

    public function bill()
    {
        return $this->belongsTo(UtilityBillingProof::class, 'bill_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCredit(): bool
    {
        return (float) $this->amount < 0;
    }

    public function isReconciliation(): bool
    {
        return $this->type === self::TYPE_RECONCILIATION;
    }

    /** Does this row change what the tenant owes? (Reconciliation rows do not.) */
    public function affectsTotal(): bool
    {
        return ! $this->isReconciliation();
    }

    public function label(): string
    {
        if ($this->isReconciliation()) {
            return 'Estimate → actual';
        }

        return $this->isCredit() ? 'Credit' : 'Additional charge';
    }
}
