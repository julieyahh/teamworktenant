<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    public const STATUS_NEW      = 'New';
    public const STATUS_REVIEWED = 'Reviewed';

    /** Category dropdown options, in display order. */
    public const CATEGORIES = [
        'General',
        'Facility',
        'Staff',
        'Service',
        'Suggestion',
        'Complaint',
    ];

    protected $fillable = [
        'tenant_id',
        'category',
        'rating',
        'subject',
        'message',
        'status',
        'manager_response',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'rating'       => 'integer',
        'responded_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function respondedBy()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isReviewed(): bool
    {
        return $this->status === self::STATUS_REVIEWED;
    }
}
