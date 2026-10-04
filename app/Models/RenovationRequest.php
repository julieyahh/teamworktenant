<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenovationRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'Pending';
    public const STATUS_APPROVED  = 'Approved';
    public const STATUS_DENIED    = 'Denied';
    public const STATUS_CANCELLED = 'Cancelled';

    /**
     * Picking this in the form reveals a free-text box, and what the tenant
     * types is what gets stored in `renovation_type`. The literal string
     * "Others" is never saved — see Tenant\RenovationRequestController@store.
     */
    public const TYPE_OTHERS = 'Others';

    /** The dropdown options, in display order. */
    public const TYPES = [
        'Painting',
        'Flooring',
        'Fixtures & Lighting',
        'Plumbing',
        'Cabinetry / Storage',
        'Partition / Layout',
        self::TYPE_OTHERS,
    ];

    protected $fillable = [
        'tenant_id',
        'lease_id',
        'unit_id',
        'room_no',
        'title',
        'description',
        'renovation_type',
        'estimated_cost',
        'preferred_date',
        'attachment',
        'status',
        'manager_remarks',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'decided_at'     => 'datetime',
        'estimated_cost' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Only pending requests can still be acted on. */
    public function isDecided(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_DENIED], true);
    }

    /**
     * One line naming the request, for table rows and confirm dialogs.
     *
     * New requests have no title — the field was removed from the form — so
     * this falls back to the type. Requests filed before that change still
     * have a title and it is still the most useful label for them, so it wins
     * where it exists.
     */
    public function summaryLabel(): string
    {
        return $this->title
            ?: ($this->renovation_type ?: 'Renovation request');
    }
}
