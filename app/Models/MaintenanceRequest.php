<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reported repair, from "the tap is leaking" through to who paid for it.
 *
 * COSTS
 *   labor_cost / material_cost / total_cost / tenant_charge_amount are rolled
 *   up from maintenance_work_items by MaintenanceCostService::recalculate().
 *   Nothing else should write them.
 *
 * RESPONSIBILITY
 *   cost_responsibility starts as 'undetermined' on purpose. Whether a repair
 *   is fair wear and tear or tenant damage is a judgement call with a lease
 *   contract behind it, so a person has to decide it — with a reason — before
 *   anyone gets billed. See MaintenanceCostService::setResponsibility().
 */
class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lease_id',
        'unit_id',
        'unit_type',
        'room_no', // still included, optional
        'description',
        'urgency',
        'supposed_date',
        'status',
        'issue_image',

        // Work
        'assigned_to',
        'scheduled_date',
        'started_at',
        'completed_at',
        'diagnosis',
        'resolution_notes',

        // Costs (written by MaintenanceCostService only)
        'labor_cost',
        'material_cost',
        'total_cost',

        // Who pays
        'cost_responsibility',
        'tenant_share_percent',
        'responsibility_reason',
        'tenant_charge_amount',
        'charged_at',
        'charged_by',
    ];

    protected $casts = [
        'supposed_date'        => 'date',
        'scheduled_date'       => 'date',
        'started_at'           => 'datetime',
        'completed_at'         => 'datetime',
        'charged_at'           => 'datetime',
        'labor_cost'           => 'decimal:2',
        'material_cost'        => 'decimal:2',
        'total_cost'           => 'decimal:2',
        'tenant_charge_amount' => 'decimal:2',
        'tenant_share_percent' => 'decimal:2',
    ];

    /* ==================================================================
     |  RELATIONSHIPS
     * ================================================================== */

    // Link to tenant (User)
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    // Link to lease
    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    // Link to unit
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // Link to property
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /** Labour and materials, itemised. */
    public function workItems()
    {
        return $this->hasMany(MaintenanceWorkItem::class, 'maintenance_request_id');
    }

    /** Repair history — every status change, oldest first. */
    public function statusLogs()
    {
        return $this->hasMany(MaintenanceStatusLog::class, 'maintenance_request_id')
            ->orderBy('created_at');
    }

    /** Repair bills posted for this request (v20). The live one is the non-void one. */
    public function bills()
    {
        return $this->hasMany(UtilityBillingProof::class, 'maintenance_request_id');
    }

    public function currentBill(): ?UtilityBillingProof
    {
        return $this->bills()
            ->where('status', '!=', UtilityBillingProof::STATUS_VOID)
            ->whereNull('voided_at')
            ->latest('id')
            ->first();
    }

    public function chargedBy()
    {
        return $this->belongsTo(User::class, 'charged_by');
    }

    /* ==================================================================
     |  STATE
     * ================================================================== */

    public function isCharged(): bool
    {
        return $this->charged_at !== null;
    }

    public function isComplete(): bool
    {
        return strtolower((string) $this->status) === 'completed';
    }

    /** Costed, finished, and nobody has said who pays. Needs a decision. */
    public function needsResponsibilityDecision(): bool
    {
        return (float) $this->total_cost > 0
            && $this->cost_responsibility === 'undetermined';
    }

    /** Decided as billable to the tenant, but not yet actually billed. */
    public function isBillableToTenant(): bool
    {
        return !$this->isCharged()
            && in_array($this->cost_responsibility, ['tenant', 'shared'], true)
            && (float) $this->tenant_charge_amount > 0;
    }

    /** What the landlord ends up absorbing. */
    public function landlordShare(): float
    {
        return max(0.0, round((float) $this->total_cost - (float) $this->tenant_charge_amount, 2));
    }

    /** How long the repair took, once it is finished. */
    public function turnaroundDays(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        $from = $this->started_at ?? $this->created_at;

        return $from ? (int) $from->diffInDays($this->completed_at) : null;
    }

    public function responsibilityLabel(): string
    {
        return match ($this->cost_responsibility) {
            'landlord' => 'Landlord pays',
            'tenant'   => 'Tenant pays',
            'shared'   => 'Shared (' . rtrim(rtrim((string) $this->tenant_share_percent, '0'), '.') . '% tenant)',
            default    => 'Not yet decided',
        };
    }

    /* ==================================================================
     |  SCOPES
     * ================================================================== */

    public function scopeAwaitingCostDecision($query)
    {
        return $query->where('total_cost', '>', 0)
            ->where('cost_responsibility', 'undetermined');
    }

    public function scopeBillable($query)
    {
        return $query->whereNull('charged_at')
            ->whereIn('cost_responsibility', ['tenant', 'shared'])
            ->where('tenant_charge_amount', '>', 0);
    }
}
