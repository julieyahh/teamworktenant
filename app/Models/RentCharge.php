<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One month of rent, charged.
 *
 * `leases.rent_balance` is the running total; this is what it is made of.
 * Written only by App\Services\BillingService::chargeDueRent().
 *
 * There is no per-charge "paid" flag on purpose. Rent payments come off the
 * lease balance rather than being allocated to a specific month (unlike
 * utility bills, which have bill_payment_allocations). Settlement is therefore
 * DERIVED, oldest-first, by Lease::rentChargeLedger() — which is always right,
 * where a stored flag would be one more thing to keep in step.
 */
class RentCharge extends Model
{
    use HasFactory;

    public const SOURCE_SCHEDULED = 'scheduled';
    public const SOURCE_MANUAL    = 'manual';
    public const SOURCE_APPROVAL  = 'approval';

    protected $fillable = [
        'lease_id',
        'tenant_id',
        'room_no',
        'period_start',
        'period_end',
        'due_date',
        'amount',
        'advance_applied',
        'source',
        'charged_by',
        'notes',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'due_date'        => 'date',
        'amount'          => 'decimal:2',
        'advance_applied' => 'decimal:2',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function chargedBy()
    {
        return $this->belongsTo(User::class, 'charged_by');
    }

    /**
     * e.g. "March 2026". What the tenant recognises it as.
     *
     * A part-month (v19 — move-in mid-month, or a pre-v19 lease joining the
     * 1st-of-the-month schedule) reads "Oct 17–31, 2026" so it is obvious why
     * it is not a full month's rent.
     */
    public function periodLabel(): string
    {
        if (! $this->period_start) {
            return '—';
        }

        if ($this->isPartMonth() && $this->period_end) {
            $last = $this->period_end->copy()->subDay();

            return $this->period_start->format('M j') . '–' . $last->format('j, Y');
        }

        return $this->period_start->format('F Y');
    }

    /** Shorter than a whole calendar month (v19). */
    public function isPartMonth(): bool
    {
        return $this->period_start !== null
            && ! \App\Support\RentCalendar::isWholeMonth($this->period_start);
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_MANUAL   => 'Raised by the office',
            self::SOURCE_APPROVAL => 'First month (on approval)',
            default               => 'Monthly',
        };
    }

    public function scopeOldestFirst($query)
    {
        return $query->orderBy('period_start')->orderBy('id');
    }

    public function scopeForLease($query, $leaseId)
    {
        return $query->where('lease_id', $leaseId);
    }
}
