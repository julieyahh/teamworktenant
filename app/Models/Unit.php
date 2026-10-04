<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'room_no',
        'room_price',
        'status',
        'property_id',
        'capacity',
        'no_of_occupants',
        'application_limit'
    ];

    // Unit belongs to a Property
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // Unit can have many leases
    public function leases()
    {
        return $this->hasMany(Lease::class, 'unit_id');
    }

    public function tenantApplications()
    {
        return $this->hasMany(TenantApplication::class, 'unit_id');
    }

    // Unit can have many maintenance requests through leases
    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'unit_id');
    }

    public function activeLease()
    {
        return $this->hasOne(Lease::class)->where('status', 'Active');
    }

}