<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One renewal event.
 *
 * Written by Lease::renewForAnotherYear() and nowhere else, so the history can
 * never disagree with what the renewal actually did. If you find another class
 * creating rows here, that is the bug.
 *
 * Read it in the Renewal History report (manager/reports/renewal-history).
 */
class LeaseRenewal extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL    = 'manual';
    public const SOURCE_SCHEDULED = 'scheduled';
    public const SOURCE_LEGACY    = 'legacy';

    protected $fillable = [
        'lease_id',
        'tenant_id',
        'unit_id',
        'room_no',
        'renewal_no',
        'previous_rent',
        'new_rent',
        'increase_amount',
        'increase_rate',
        'previous_end_date',
        'new_start_date',
        'new_end_date',
        'source',
        'renewed_by',
        'notes',
    ];

    protected $casts = [
        'previous_end_date' => 'date',
        'new_start_date'    => 'date',
        'new_end_date'      => 'date',
        'previous_rent'     => 'decimal:2',
        'new_rent'          => 'decimal:2',
        'increase_amount'   => 'decimal:2',
        'increase_rate'     => 'decimal:4',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function renewedBy()
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }

    /** "5.0%" for display. */
    public function increasePercent(): string
    {
        return number_format((float) $this->increase_rate * 100, 1) . '%';
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_SCHEDULED => 'Automatic',
            self::SOURCE_LEGACY    => 'Pre-existing',
            default                => 'Manual',
        };
    }

    /** Most recent first — the order the report shows. */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
