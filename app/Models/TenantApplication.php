<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'contact_number',
        'current_address',
        'birthdate',
        'unit_type',
        'unit_id',
        'room_no',
        'bed_number',
        'move_in_date',
        'reason',
        'employment_status',
        'employer_school',
        'source_of_income',
        'monthly_income',
        'emergency_name',
        'emergency_number',
        'emergency_relationship',
        'valid_id_path',
        'id_picture_path',
    ];

    // Relationship to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
