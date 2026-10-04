<?php
// app/Http/Controllers/LandingController.php
namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\TenantApplication;

class LandingController extends Controller
{
    public function index()
    {
        // Fetch all units
        $units = Unit::with(['leases', 'tenantApplications.user'])->get();

        // Count vacant rooms
        $vacantCount = $units->where('status', 'vacant')->count();

        // Get available units (vacant or Bed-Spacer with available beds)
         $availableUnits = Unit::with(['leases' => fn($query) => $query->where('lea_status', 'active')])
            ->where(function ($query) {
                $query->where('status', 'vacant')
                    ->orWhere(function ($bedQuery) {
                        $bedQuery->where('type', 'Bed-Spacer')
                            ->whereColumn('no_of_occupants', '<', 'capacity');
                    });
            })
            ->get();

        $pendingBedReservations = TenantApplication::select('unit_id', 'bed_number')
            ->whereNotNull('bed_number')
            ->whereHas('user', function ($query) {
                $query->whereIn('status', ['pending', 'approved']);
            })
            ->get()
            ->groupBy('unit_id')
            ->map(fn($group) => $group->pluck('bed_number')->filter()->unique());

        // Add taken_beds attribute to each unit
        $availableUnits->each(function ($unit) use ($pendingBedReservations) {
            // Only include active leases, exclude pending
            $leaseBeds = $unit->leases->pluck('bed_number')->filter();
            $pendingBeds = $pendingBedReservations->get($unit->id, collect());
            $unit->setAttribute(
                'taken_beds',
                $leaseBeds->merge($pendingBeds)->filter()->unique()->values()
            );
        });

        // An authenticated tenant with an unpaid deposit cannot apply for another
        // unit. Computed here so the landing modal can explain the block instead
        // of letting them submit a form that the controller will only reject.
        $unpaidDeposit = 0.0;
        $hasPendingApplication = false;

        if (auth()->check()) {
            $unpaidDeposit = \App\Http\Controllers\TenantLeaseController::outstandingDepositFor(auth()->id());

            $hasPendingApplication = \App\Models\Lease::where('user_id', auth()->id())
                ->where('lea_status', 'pending')
                ->exists();
        }

        // Pass everything to the view
        return view('landing', compact(
            'units', 'vacantCount', 'availableUnits', 'unpaidDeposit', 'hasPendingApplication'
        ));
    }
}
