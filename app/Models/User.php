<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Boot functionality
    protected static function booted()
    {
        static::saving(function ($user) {
            // Automatically refresh statuses before saving
            $user->updatePaymentStatuses();
        });
    }


    // ✅ Add tenant financial info to fillable
    protected $fillable = [
        // 'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',            // tenant | manager
        'status',          // pending | approved | rejected
        'contact_number',
        'rejection_reason',

        // Tenant financial info
        'rent_amount',
        'utility_amount',
        'deposit_amount',
        'rent_balance',
        'utility_balance',
        'user_credit',

        // tenant payment statuses
        'rental_payment_status',
        'utility_payment_status',
        'proof_of_utility_billing',
        'terms_accepted',
        'privacy_consent_at',       // v18 — RA 10173 consent, see config/privacy.php
        'privacy_consent_version',

        'reset_token',
        'reset_token_created_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'privacy_consent_at' => 'datetime',
        'password' => 'hashed',
    ];

    // 🔗 Relationships
    public function leases()
    {
        return $this->hasMany(Lease::class, 'user_id');
    }

    // public function payments()
    // {
    //     return $this->hasMany(Payment::class, 'tenant_id');
    // }
    // Old

    // New: payments now go through leases
    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Lease::class, 'user_id', 'lease_id', 'id', 'id');
    }


    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'tenant_id');
    }

    public function tenantApplication()
    {
        return $this->hasOne(TenantApplication::class, 'user_id');
    }

    public function utilityProofs()
    {
        return $this->hasMany(UtilityBillingProof::class, 'tenant_id')->latest();
    }

    public function renovationRequests()
    {
        return $this->hasMany(RenovationRequest::class, 'tenant_id');
    }

    /** Total unpaid deposit across this tenant's live leases. */
    public function outstandingDeposit(): float
    {
        return (float) $this->leases()
            ->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
            ->where('deposit_balance', '>', 0)
            ->sum('deposit_balance');
    }


    public function hasCompletedTenantApplication(): bool
    {
        $app = $this->tenantApplication;
        return $app && $app->completed;
    }

    public function isApprovedTenant(): bool
    {
        return $this->role === 'tenant' && $this->status === 'approved';
    }

    // ⚡ Helpers for payments
    public function updatePaymentStatuses()
    {
        // ✅ Rental Payment
        if ($this->rent_balance > 0) {
            $this->rental_payment_status = 'pending';
        } else {
            $this->rental_payment_status = 'settled';
        }

        // ✅ Utility Payment
        if ($this->utility_balance > 0) {
            $this->utility_payment_status = 'pending';
        } else {
            $this->utility_payment_status = 'settled';
        }

        // ✅ Overdue check (7 days after account creation)
        $daysSinceCreated = Carbon::parse($this->created_at)->diffInDays(now());
        if ($daysSinceCreated > 7 && ($this->rent_balance > 0 || $this->utility_balance > 0)) {
            $this->rental_payment_status = 'overdue';
            $this->utility_payment_status = 'overdue';
        }

        $this->saveQuietly(); // avoid recursive updating event
    }


    // Total paid for a specific type (Rent, Utilities, Deposit)
    public function totalPaid(string $paymentFor): float
    {
        return (float) $this->payments()
            ->where('payment_for', $paymentFor)
            ->where('pay_status', 'Paid')
            ->sum('pay_amount');
    }

    // Remaining balance for rent or utilities
    public function unpaidBalance(string $paymentFor, float $expectedAmount): float
    {
        return max(0, $expectedAmount - $this->totalPaid($paymentFor));
    }

    // Check if deposit is paid
    public function depositPaid(): bool
    {
        return $this->payments()
            ->where('payment_for', 'Deposit')
            ->where('pay_status', 'Paid')
            ->exists();
    }

    public function getNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function activeLeases()
    {
        return $this->hasMany(Lease::class)->where('status', 'Active');
    }

    /**
     * Get the total utility balance from all active/pending leases
     * This calculates the sum of utility_balance from all active leases
     */
    public function getTotalUtilityBalanceAttribute()
    {
        $activeLeases = $this->leases()
            ->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
            ->get();

        return $activeLeases->sum(function($lease) {
            // Use lease utility_balance if set, otherwise fallback to 0
            return $lease->utility_balance ?? 0;
        });
    }

}