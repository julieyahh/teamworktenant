<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A written demand for payment, and the record that it was served.
 *
 * This is the audit trail, not a rule engine. Nothing in the app creates one
 * of these automatically — a manager decides, writes it and records how it was
 * delivered. If a case is ever filed, what matters is this table: what was
 * demanded, when it was served, how, and that the notice period was given
 * before anything else happened.
 *
 * See config/collections.php for the periods, and note the standing rule there:
 * no utility cut-offs, no lockouts, no self-help remedies of any kind.
 */
class PaymentDemand extends Model
{
    use HasFactory;

    public const TYPE_REMINDER         = 'reminder';
    public const TYPE_FORMAL_DEMAND    = 'formal_demand';
    public const TYPE_NOTICE_TO_VACATE = 'notice_to_vacate';

    public const OUTCOME_OPEN        = 'open';
    public const OUTCOME_PAID        = 'paid';
    public const OUTCOME_ARRANGEMENT = 'arrangement';
    public const OUTCOME_SUPERSEDED  = 'superseded';
    public const OUTCOME_ESCALATED   = 'escalated';
    public const OUTCOME_WITHDRAWN   = 'withdrawn';

    protected $fillable = [
        'tenant_id',
        'lease_id',
        'type',
        'amount_claimed',
        'rent_component',
        'utility_component',
        'other_component',
        'issued_at',
        'grace_period_days',
        'comply_by',
        'delivery_channel',
        'delivery_reference',
        'delivered_at',
        'acknowledged_at',
        'outcome',
        'resolved_at',
        'body',
        'notes',
        'document_path',
        'issued_by',
    ];

    protected $casts = [
        'amount_claimed'    => 'decimal:2',
        'rent_component'    => 'decimal:2',
        'utility_component' => 'decimal:2',
        'other_component'   => 'decimal:2',
        'issued_at'         => 'datetime',
        'comply_by'         => 'date',
        'delivered_at'      => 'datetime',
        'acknowledged_at'   => 'datetime',
        'resolved_at'       => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('outcome', self::OUTCOME_OPEN);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /* ==================================================================
     |  STATE
     * ================================================================== */

    public function isOpen(): bool
    {
        return $this->outcome === self::OUTCOME_OPEN;
    }

    /**
     * Has the notice period run out?
     *
     * Deliberately conservative: a demand that was never recorded as delivered
     * is NOT counted as expired, however old it is. An undelivered notice
     * proves nothing, and treating it as expired would put a tenant into a
     * later stage on the strength of a document they may never have received.
     */
    public function graceHasExpired(): bool
    {
        if (!$this->isOpen() || !$this->delivered_at || !$this->comply_by) {
            return false;
        }

        return Carbon::parse($this->comply_by)->endOfDay()->isPast();
    }

    /** Days left to comply. Negative once the period has run out. */
    public function daysRemaining(): ?int
    {
        if (!$this->comply_by) {
            return null;
        }

        return (int) Carbon::today()->diffInDays(Carbon::parse($this->comply_by), false);
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * A demand that was issued but never recorded as served. These are the
     * dangerous ones — the landlord believes notice was given and the file
     * cannot show it. Surfaced prominently on the collections screen.
     */
    public function isUndelivered(): bool
    {
        return $this->isOpen() && $this->delivered_at === null;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_FORMAL_DEMAND    => 'Formal demand to pay',
            self::TYPE_NOTICE_TO_VACATE => 'Notice to vacate',
            default                     => 'Payment reminder',
        };
    }
}
