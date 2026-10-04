<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One line on a repair: an hour of labour or a part that was bought.
 *
 * Itemised on purpose. When a tenant is being charged for a repair, "PHP 1,800"
 * invites an argument and "2 hrs @ 450 + 1 flush valve @ 900" does not.
 *
 * line_total is computed on save — never set it by hand.
 */
class MaintenanceWorkItem extends Model
{
    use HasFactory;

    public const TYPE_LABOR    = 'labor';
    public const TYPE_MATERIAL = 'material';

    protected $fillable = [
        'maintenance_request_id',
        'type',
        'description',
        'quantity',
        'unit',
        'unit_cost',
        'line_total',
        'performed_by',
        'performed_on',
        'receipt_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity'     => 'decimal:2',
        'unit_cost'    => 'decimal:2',
        'line_total'   => 'decimal:2',
        'performed_on' => 'date',
    ];

    protected static function booted()
    {
        // Keep line_total honest no matter which controller wrote the row.
        static::saving(function (MaintenanceWorkItem $item) {
            $item->line_total = round(
                (float) ($item->quantity ?? 0) * (float) ($item->unit_cost ?? 0),
                2
            );
        });
    }

    public function request()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLabor($query)
    {
        return $query->where('type', self::TYPE_LABOR);
    }

    public function scopeMaterial($query)
    {
        return $query->where('type', self::TYPE_MATERIAL);
    }
}
