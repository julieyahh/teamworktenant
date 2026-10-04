<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One provider bill split across several leases (shared meter).
 *
 * Exists to answer "why is my share this number?". The batch stores the total
 * we were charged, the rule used to divide it, and where the leftover centavo
 * went. The per-tenant bills are UtilityBillingProof rows with batch_id set.
 */
class UtilityBillBatch extends Model
{
    use HasFactory;

    public const RULE_EQUAL     = 'equal';
    public const RULE_HEADCOUNT = 'headcount';
    public const RULE_UNIT_SIZE = 'unit_size';

    public const REMAINDER_LANDLORD  = 'landlord';
    public const REMAINDER_DESIGNATED = 'designated_tenant';

    protected $fillable = [
        'billing_type',
        'billing_month',
        'total_amount',
        'due_date',
        'split_rule',
        'remainder_policy',
        'rounding_absorbed',
        'designated_lease_id',
        'is_estimated',
        'file_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'due_date'          => 'date',
        'total_amount'      => 'decimal:2',
        'rounding_absorbed' => 'decimal:2',
        'is_estimated'      => 'boolean',
    ];

    public function bills()
    {
        return $this->hasMany(UtilityBillingProof::class, 'batch_id');
    }

    public function designatedLease()
    {
        return $this->belongsTo(Lease::class, 'designated_lease_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Plain-language description of how this bill was divided, for the tenant
     * to read. Keep it readable — this is the sentence that settles arguments.
     */
    public function splitExplanation(): string
    {
        $rule = match ($this->split_rule) {
            self::RULE_HEADCOUNT => 'in proportion to the number of occupants on each lease',
            self::RULE_UNIT_SIZE => 'in proportion to each unit\'s size',
            default              => 'equally between all participating rooms',
        };

        $text = sprintf(
            'A %s bill of PHP %s for %s was divided %s across %d room(s).',
            strtolower($this->billing_type ?? 'utility'),
            number_format((float) $this->total_amount, 2),
            $this->billing_month,
            $rule,
            $this->bills()->count()
        );

        $remainder = (float) $this->rounding_absorbed;
        if ($remainder > 0) {
            $text .= $this->remainder_policy === self::REMAINDER_DESIGNATED
                ? sprintf(' PHP %s left over from rounding was added to one room\'s share.', number_format($remainder, 2))
                : sprintf(' PHP %s left over from rounding was absorbed by the landlord.', number_format($remainder, 2));
        }

        return $text;
    }
}
