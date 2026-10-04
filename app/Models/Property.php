<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'legder_id',
        'description', // optional
        'status',      // optional: e.g., 'active', 'inactive'
    ];

    /**
     * 🔗 Property belongs to a manager (User with role = manager)
     */

    public function tenants()
    {
        return $this->hasManyThrough(User::class, Lease::class, 'unit_id', 'id', 'id', 'user_id')
                    ->join('units', 'leases.unit_id', '=', 'units.id')
                    ->where('units.property_id', $this->id);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * 🔗 Property has many units
     * Needed for tenant unit selection
     */
    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * 🔗 Property has many leases through units
     * Optional convenience relationship
     */
    public function leases()
    {
        return $this->hasManyThrough(Lease::class, Unit::class, 'property_id', 'unit_id');
    }

    /**
     * 🔗 Property has many maintenance requests through units
     */
    public function maintenanceRequests()
    {
        return $this->hasManyThrough(MaintenanceRequest::class, Unit::class, 'property_id', 'unit_id');
    }
}
