<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Repair history — every status change on a maintenance request.
 *
 * `maintenance_requests.status` only holds the current value, so without this
 * there is no answering "how long did that repair take" or "who marked it
 * completed" after the fact.
 */
class MaintenanceStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_request_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by',
    ];

    public function request()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
