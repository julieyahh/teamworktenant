<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\UtilityBillingProof;

/**
 * JSON lookup used by the manager's "Record Payment" modal.
 *
 *   GET /manager/tenant/{tenant}/leases   (manager.tenant.leases)
 *
 * When the manager picks a tenant, the browser calls this to fill the Lease
 * dropdown and pre-fill the amount owed per lease.
 *
 * This used to be a 60-line closure inside routes/web.php.
 */
class LeaseBalanceController extends Controller
{
    public function forTenant($tenantId)
    {
        $leases = Lease::with('unit', 'tenant')
            ->where('user_id', $tenantId)
            ->whereIn('lea_status', ['active', 'pending'])
            ->where('move_out_requested', 0)
            ->get()
            ->map(fn (Lease $lease) => [
                'id'                 => $lease->id,
                'room_no'            => $lease->room_no,
                'bed_number'         => $lease->bed_number ?? 0,
                'unit_type'          => $lease->unit->type ?? 'Unit',
                'room_price'         => ($lease->rent_balance ?? 0) + ($lease->penalty_fee ?? 0),
                'deposit'            => $lease->deposit_balance ?? 0,
                'utility_balance'    => $lease->utility_balance,
                'electricity_amount' => $this->unpaidUtilityAmount($lease->id, 'Electricity'),
                'water_amount'       => $this->unpaidUtilityAmount($lease->id, 'Water'),

                // Advance rent. `monthly_rent` is what one month forward costs
                // — note that it is NOT the same as `room_price` above, which
                // is confusingly named: that field holds the OUTSTANDING rent
                // plus penalty, not the monthly rate.
                'monthly_rent'        => $lease->currentRent(),
                'advance_balance'     => $lease->advanceRentBalance(),
                'advance_months_left' => max(
                    0,
                    (int) config('billing.advance_rent.max_months', 12) - $lease->advanceMonthsCovered()
                ),
                'rent_paid_through'   => $lease->rent_paid_through?->format('M d, Y'),
            ]);

        return response()->json($leases);
    }

    /**
     * Newest unpaid Electricity/Water proof for this lease, this month or last.
     */
    private function unpaidUtilityAmount(int $leaseId, string $billingType): float
    {
        $currentMonth  = now()->month;
        $previousMonth = now()->subMonth()->month;

        $proof = UtilityBillingProof::where('lease_id', $leaseId)
            ->where('billing_type', $billingType)
            ->where(fn ($q) => $q->whereMonth('created_at', $currentMonth)
                                 ->orWhereMonth('created_at', $previousMonth))
            ->whereYear('created_at', now()->year)
            ->where('status', 'unpaid')
            ->latest()
            ->first();

        return (float) ($proof?->amount ?? 0);
    }
}
