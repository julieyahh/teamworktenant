<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Mail\TenantApprovedMail;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\Unit;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class UnitController extends Controller
{
    protected SmsService $smsService;
    /**
     * Show all units with add form.
     */

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        $units = Unit::with([
                'leases',
                'tenantApplications.user'
            ])
            ->when($search, function ($query) use ($search) {
                $query->where('room_no', 'like', "%{$search}%");
            })
            ->when($filter !== 'all', function ($query) use ($filter) {
                $query->where('type', $filter);
            })
            ->get();

        $pendingLeases = Lease::with('tenant', 'unit')
            ->where('lea_status', 'pending')
            ->get();

        return view('manager.unitsetup', compact('units', 'pendingLeases', 'filter', 'search'));
    }


    protected function normalizePhoneNumber(?string $number): ?string
    {
        if (!$number) return null;

        $number = preg_replace('/\D/', '', $number); // remove non-numeric characters

        if (str_starts_with($number, '0')) {
            return '+63' . substr($number, 1);
        } elseif (str_starts_with($number, '63')) {
            return '+' . $number;
        } elseif (str_starts_with($number, '+63')) {
            return $number;
        }

        // fallback: assume it's a 10-digit local number
        return '+63' . $number;
    }

    /**
     * Store a new unit.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|max:255',
            'room_no' => 'required|string|max:50|unique:units,room_no',
            'room_price' => 'required|numeric|min:0',
            'status' => 'required|in:vacant,occupied',
            'capacity' => 'required|integer|min:1',
            'application_limit' => 'required|integer|min:1',
        ]);

        Unit::create([
            'type' => $request->type,
            'room_no' => $request->room_no,
            'room_price' => $request->room_price,
            'status' => $request->status,
            'capacity' => $request->capacity,
            'application_limit' => $request->application_limit,
        ]);

        return redirect()->route('manager.units.index')->with('success', 'Unit added successfully.');
    }

    /**
     * Edit a unit.
     */
    public function edit($id)
    {
        return response()->json(Unit::findOrFail($id));
    }


    /**
     * Update a unit.
     */
    /**
 * Update a unit.
 */
    public function update(Request $request, Unit $unit)
    {
        $request->validate([
            'type' => 'required|string|max:255',
            'room_no' => 'required|string|max:50|unique:units,room_no,' . $unit->id,
            'room_price' => 'required|numeric|min:0',
            'status' => 'required|in:vacant,occupied',
            'capacity' => 'required|integer|min:1',
            'application_limit' => 'required|integer|min:1',
        ]);

        $unit->update($request->only(['type', 'room_no', 'room_price', 'status', 'capacity', 'application_limit']));

        return redirect()->route('manager.units.index')
            ->with('success', 'Unit updated successfully.');
    }

    /**
     * Delete a unit.
     */
    public function destroy(Unit $unit)
    {
        $unit->delete();

        return redirect()->route('manager.units.index')
            ->with('success', 'Unit deleted successfully.');
    }

public function approveAdditionalUnit($userId, $unitId, $leaseId)
{
    $lock = Cache::lock('approve-additional-unit:' . $leaseId, 15);

    if (! $lock->get()) {
        return redirect()->back()->with('info', 'This application is already being processed.');
    }

    try {
        $result = DB::transaction(function () use ($userId, $unitId, $leaseId) {

            $user = User::lockForUpdate()->find($userId);
            if (!$user || $user->role !== 'tenant') {
                return ['type' => 'error', 'message' => 'Invalid tenant.'];
            }

            $unit = Unit::lockForUpdate()->find($unitId);
            if (!$unit) {
                return ['type' => 'error', 'message' => 'Selected unit not found.'];
            }

            $lease = Lease::where('id', $leaseId)
                ->where('user_id', $user->id)
                ->where('unit_id', $unit->id)
                ->lockForUpdate()
                ->first();

            if (!$lease) {
                return ['type' => 'error', 'message' => 'Lease not found for this unit.'];
            }

            // Idempotency guard: a second click must not create a second lease
            // or charge the tenant a second deposit.
            if (strtolower((string) $lease->lea_status) === 'active') {
                return ['type' => 'info', 'message' => 'This unit application has already been approved.'];
            }

            if (in_array(strtolower((string) $lease->lea_status), ['rejected', 'cancelled', 'terminated'], true)) {
                return ['type' => 'info', 'message' => 'This application is no longer pending and cannot be approved.'];
            }

            // Approve the tenant account if it is not approved yet
            if ($user->status !== 'approved') {
                $user->status = 'approved';
                $user->rejection_reason = null;
            }

            // Lease dates
            $startDate = Carbon::today();
            $endDate   = $startDate->copy()->addYear();
            $leaseTermText = sprintf(
                '1 Year Lease (%s - %s)',
                $startDate->format('M d, Y'),
                $endDate->format('M d, Y')
            );

            // Assign unit occupancy
            if ($unit->type === 'Bed-Spacer') {
                $unit->no_of_occupants = ($unit->no_of_occupants ?? 0) + 1;
                if ($unit->no_of_occupants >= $unit->capacity) {
                    $unit->status = 'occupied';
                }
            } else {
                $unit->status = 'occupied';
            }
            $unit->save();

            // Financials for the new unit
            $monthlyRent      = (float) ($unit->room_price ?? 0);
            $depositAmount    = $monthlyRent * 2;
            $monthlyUtilities = 0;

            // Accumulate the tenant-level totals
            $user->deposit_amount  = ($user->deposit_amount ?? 0) + $depositAmount;
            $user->rent_balance    = ($user->rent_balance ?? 0) + $monthlyRent;
            $user->utility_balance = ($user->utility_balance ?? 0) + $monthlyUtilities;
            $user->rent_amount     = ($user->rent_amount ?? 0) + $monthlyRent;
            $user->utility_amount  = ($user->utility_amount ?? 0) + $monthlyUtilities;
            $user->save();

            // Activate the existing lease. The deposit is written here, which is
            // what makes it show up in Tenant Management after approval.
            $lease->update([
                'lea_start_date'  => $startDate,
                'lea_end_date'    => $endDate,
                'lea_status'      => 'active',
                'room_no'         => $unit->room_no,
                'lea_terms'       => $leaseTermText,
                'bed_number'      => $lease->bed_number ?: ($user->tenantApplication->bed_number ?? null),
                'deposit_balance' => $depositAmount,
                // openRentSchedule() below raises the first month and puts it
                // on the balance. Setting it here too would double-bill.
                'rent_balance'    => 0,
                'monthly_rent'    => $monthlyRent,
                'utility_balance' => ($lease->utility_balance ?? 0) + $monthlyUtilities,
                'due_date'        => $startDate->copy()->addMonth(),
            ]);

            // Same as ManagerController@performApprove: start the monthly
            // schedule and record the first month as a charge.
            $lease->refresh();
            app(\App\Services\BillingService::class)
                ->openRentSchedule($lease, $startDate->copy(), auth()->user());

            Notification::create([
                'user_id' => $user->id,
                'title'   => 'Approved Additional Unit',
                'message' => "Your request to add unit room {$unit->room_no} has been approved. " .
                             'A deposit of PHP ' . number_format($depositAmount, 2) . ' is now due.',
            ]);

            foreach (User::where('role', 'manager')->get() as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'title'   => 'Approved Additional Unit',
                    'message' => "{$user->name} request to add unit room {$unit->room_no} has been approved.",
                ]);
            }

            return [
                'type'    => 'success',
                'message' => 'Tenant approved successfully. Deposit of PHP ' .
                             number_format($depositAmount, 2) . ' recorded and financials updated.',
            ];
        });
    } catch (\Throwable $e) {
        Log::error('approveAdditionalUnit failed for lease ' . $leaseId . ': ' . $e->getMessage());

        return redirect()->back()->with('error', 'An error occurred while approving this unit. Please try again.');
    } finally {
        $lock->release();
    }

    return redirect()->back()->with($result['type'], $result['message']);
}

public function rejectAdditionalUnit($userId, $unitId, $leaseId)
{
    $user = User::find($userId);
    if (!$user || $user->role !== 'tenant') {
        return redirect()->back()->with('error', 'Invalid tenant.');
    }

    $unit = Unit::find($unitId);
    if (!$unit) {
        return redirect()->back()->with('error', 'Selected unit not found.');
    }

    // Find the lease for this unit
    $lease = Lease::where('id', $leaseId)
                ->where('user_id', $user->id)
                ->where('unit_id', $unit->id)
                ->first();

    if (!$lease) {
        return redirect()->back()->with('error', 'Lease not found for this unit.');
    }

    // Idempotency guard - a second click must not re-run the occupancy maths
    // or fire a second round of notifications.
    if (in_array(strtolower((string) $lease->lea_status), ['rejected', 'cancelled', 'terminated'], true)) {
        return redirect()->back()->with('info', 'This application has already been rejected.');
    }

    $isPending = strtolower((string) $lease->lea_status) === 'pending';

    // Update lease status to rejected
    $lease->update([
        'lea_status' => 'rejected'
    ]);

    // Release the unit. A *pending* lease never took up occupancy in the first
    // place, so only an already-active lease frees a slot back up.
    if (!$isPending) {
        if ($unit->type === 'Bed-Spacer') {
            $unit->no_of_occupants = max(0, ($unit->no_of_occupants ?? 0) - 1);

            if ($unit->no_of_occupants >= $unit->capacity) {
                $unit->status = 'occupied';
            } else {
                $unit->status = 'vacant';
            }
        } else {
            $unit->status = 'vacant';
        }
        $unit->save();
    }

    // Create notification for the tenant
    Notification::create([
        'user_id' => $user->id,
        'title'   => 'Unit Application Rejected',
        'message' => "Your application for room {$unit->room_no} has been rejected."
    ]);

    $managers = \App\Models\User::where('role', 'manager')->get();
    foreach ($managers as $manager) {
        Notification::create([
            'user_id' => $manager->id,
            'title' => 'Rejected Additional Unit',
            'message' => "{$user->name} request to add unit room {$unit->room_no} has been rejected."
        ]);
    }

    return redirect()->back()->with('success', 'Unit application rejected successfully.');
}


public function cancelAdditionalUnit($userId, $unitId, $leaseId)
{
    $user = User::find($userId);
    if (!$user || $user->role !== 'tenant') {
        return redirect()->back()->with('error', 'Invalid tenant.');
    }

    $unit = Unit::find($unitId);
    if (!$unit) {
        return redirect()->back()->with('error', 'Selected unit not found.');
    }

    // Find the lease for this unit
    $lease = Lease::where('id', $leaseId)
                ->where('user_id', $user->id)
                ->where('unit_id', $unit->id)
                ->first();

    if (!$lease) {
        return redirect()->back()->with('error', 'Lease not found for this unit.');
    }

    if (in_array(strtolower((string) $lease->lea_status), ['cancelled', 'rejected', 'terminated'], true)) {
        return redirect()->back()->with('info', 'This application has already been cancelled.');
    }

    $isPending = strtolower((string) $lease->lea_status) === 'pending';

    // Update lease status to cancelled
    $lease->update([
        'lea_status' => 'cancelled'
    ]);

    // Only an already-active lease frees an occupancy slot back up.
    if (!$isPending) {
        if ($unit->type === 'Bed-Spacer') {
            $unit->no_of_occupants = max(0, ($unit->no_of_occupants ?? 0) - 1);

            if ($unit->no_of_occupants >= $unit->capacity) {
                $unit->status = 'occupied';
            } else {
                $unit->status = 'vacant';
            }
        } else {
            $unit->status = 'vacant';
        }
        $unit->save();
    }

    // Create notification for the tenant
    Notification::create([
        'user_id' => $user->id,
        'title'   => 'Unit Application Rejected',
        'message' => "Your application for room {$unit->room_no} has been rejected."
    ]);

    return redirect()->back()->with('success', 'Unit application rejected successfully.');
}



}
