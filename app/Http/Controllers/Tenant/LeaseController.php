<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\PropertyApplication;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;

class LeaseController extends Controller
{

    // Fetch available units for a property
    /**
     * @deprecated NOT ROUTED. Renders view('tenant.properties.index'), which does
     * not exist. The live lease page is App\Http\Controllers\TenantLeaseController@index.
     * Only cancelMoveOut() and cancelAddUnit() in this class are reachable.
     */
    public function index()
    {
        $tenant = auth()->user();

        // Leases with property and unit
        $leases = $tenant->leases()->with('unit.property')->get();

        // Approved property applications (converted to Lease-like objects)
        $approvedApplications = PropertyApplication::with('unit.property')
            ->where('user_id', $tenant->id)
            ->where('status', 'approved')
            ->get()
            ->map(function ($app) {
                // Create a dummy object that mimics a Lease model
                $leaseLike = new \stdClass();
                $leaseLike->id = $app->id;
                $leaseLike->unit = $app->unit;
                $leaseLike->room_no = $app->room_no;
                $leaseLike->lea_start_date = now();
                $leaseLike->lea_end_date = now()->addYear();
                $leaseLike->status = $app->status;
                return $leaseLike;
            });

        // Merge Eloquent collection with plain objects safely
        $combinedLeases = collect($leases)->merge($approvedApplications);

        // Available vacant units
        $availableUnits = Unit::where('status', 'vacant')->with('property')->get();

        // Unit types
        $unitTypes = $availableUnits->pluck('type')->unique();

        // Get latest tenant application for prefill (optional)
        $application = $tenant->tenantApplication()->latest()->first();

        return view('tenant.properties.index', compact(
            'combinedLeases', 'unitTypes', 'availableUnits', 'application'
        ));
    }


    protected function getTenantBirthdate($tenant)
    {
        // Use tenant's birthdate if available; otherwise, use a placeholder
        return $tenant->birthdate ?: '1900-01-01';
    }


    // Submit a new lease application
    // In LeaseController.php

    public function applyUnit(Request $request)
    {
        $request->validate([
            'unit_id'   => 'required|exists:units,id',
            'unit_type' => 'required|string',
            'description' => 'required|string',
        ]);

        $tenant = auth()->user();
        $unit = Unit::findOrFail($request->unit_id);

        if ($unit->status !== 'vacant') {
            return redirect()->back()->with('error', 'Selected room is not available.');
        }

        PropertyApplication::create([
            'user_id' => $tenant->id,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'unit_type' => $request->unit_type,
            'room_no' => $unit->room_no,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Application submitted successfully!');
    }



    public function submitApplication(Request $request)
    {
        $request->validate([
            'unit_type'    => 'required|string',
            'unit_id'      => 'required|exists:units,id',
            'lease_start'  => 'required|date',
            'lease_end'    => 'required|date|after:lease_start',
            'description'  => 'required|string|max:500', // new field
        ]);

        // Optionally, check if the tenant already has a pending application
        $tenant = auth()->user();

        // Save a new Lease or Application record depending on your design
        $lease = Lease::create([
            'user_id' => $tenant->id,
            'unit_id' => $request->unit_id,
            'lea_terms' => $request->lease_terms,
            'lea_status' => 'pending',
            'room_no' => $tenant->leases()->where('unit_id', $request->unit_id)->value('room_no') ?? null,
        ]);

        return redirect()->back()->with('success', 'Unit application submitted successfully!');
    }

    public function cancelMoveOut($leaseId)
    {
        $tenant = auth()->user();

        $lease = Lease::where('id', $leaseId)
                ->where('user_id', $tenant->id)
                ->where('move_out_requested', 1)
                ->first();

        // Add null check
        if (!$lease) {
            return redirect()->back()->with('error', 'Lease not found or you do not have permission to cancel this move out request.');
        }

        $lease->update([
            'move_out_requested' => 0,
        ]);

        $managers = \App\Models\User::where('role', 'manager')->get();
        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title' => 'Cancel Move Out Request',
                'message' => auth()->user()->name . ' has cancelled request to move out from Room ' . ($lease->room_no ?? 'N/A')
            ]);
        }

        return redirect()->back()->with('success', 'Move out has been cancelled successfully!');
    }

    public function cancelAddUnit($leaseId)
    {
        $tenant = auth()->user();

        $lease = Lease::where('id', $leaseId)
                ->where('user_id', $tenant->id)
                ->whereNotIn('lea_status', ['active', 'rejected'])
                ->first();

        if (!$lease) {
            return redirect()->back()->with('error', 'Lease not found or you do not have permission to cancel this move out request.');
        }

        if ($lease->lea_status === 'active') {
            return redirect()->back()->with('error', 'Cancellation denied. This lease is already approved by the manager!');
        }

        if ($lease->lea_status === 'rejected') {
            return redirect()->back()->with('error', 'Cancellation denied. This lease is already rejected by the manager!');
        }


        $lease->update([
            'lea_status' => 'cancelled',
        ]);

        $managers = \App\Models\User::where('role', 'manager')->get();
        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title' => 'Cancel Add Unit Request',
                'message' => auth()->user()->name . ' has cancelled request to add unit room ' . ($lease->room_no ?? 'N/A')
            ]);
        }

        return redirect()->back()->with('success', 'Adding unit has been cancelled successfully!');
    }


}
