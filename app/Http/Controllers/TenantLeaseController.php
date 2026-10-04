<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\Notification;
use App\Models\TenantApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantLeaseController extends Controller
{
    public function index()
    {
        $leases = Lease::where('user_id', auth()->id())->with('unit')->get();

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

        // Used by the view to explain why "Apply for New Unit" is blocked.
        $unpaidDeposit = self::outstandingDepositFor(auth()->id());
        $hasPendingApplication = Lease::where('user_id', auth()->id())
            ->where('lea_status', 'pending')
            ->exists();

        return view('tenant.leasemanagement', compact(
            'leases',
            'availableUnits',
            'unpaidDeposit',
            'hasPendingApplication'
        ));
    }

    /**
     * Total unpaid deposit across a tenant's live leases.
     * Shared with the landing page so both entry points agree.
     */
    public static function outstandingDepositFor($userId): float
    {
        return (float) Lease::where('user_id', $userId)
            ->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
            ->where('deposit_balance', '>', 0)
            ->sum('deposit_balance');
    }

    public function store(Request $request)
    {
        $request->validate([
            'unit_id'    => 'required|exists:units,id',
            'start_date' => 'required|date',
            'bed_number' => 'nullable|integer|min:1|required_if:unit_type,Bed-Spacer',
        ]);

        $user = auth()->user();

        /* ------------------------------------------------------------------
         | GUARD 1 — an unpaid deposit blocks any new application.
         | Checked before the lock so the tenant gets the real reason back.
         * ------------------------------------------------------------------ */
        $unpaidDeposit = self::outstandingDepositFor($user->id);

        if ($unpaidDeposit > 0) {
            return redirect()->back()->with(
                'error',
                'You still have an unpaid deposit of PHP ' . number_format($unpaidDeposit, 2) .
                '. Please settle your deposit before applying for another unit or room.'
            );
        }

        /* ------------------------------------------------------------------
         | GUARD 2 — anti double-submit.
         | An atomic lock serialises rapid repeat clicks; whichever request
         | gets the lock first does the work, the rest are turned away
         | instead of queueing up and each writing their own row.
         * ------------------------------------------------------------------ */
        $lock = Cache::lock('lease-application:user:' . $user->id, 15);

        if (! $lock->get()) {
            return redirect()->back()->with(
                'info',
                'Your application is already being submitted. Please wait a moment before trying again.'
            );
        }

        try {
            $outcome = DB::transaction(function () use ($request, $user) {

                $unit = Unit::lockForUpdate()->find($request->unit_id);

                if (! $unit) {
                    return ['type' => 'error', 'message' => 'Selected unit could not be found.'];
                }

                /* GUARD 3 — one pending application at a time. */
                $existingPending = Lease::where('user_id', $user->id)
                    ->where('lea_status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($existingPending) {
                    return [
                        'type'    => 'info',
                        'message' => 'You already have a pending application for room ' .
                                     ($existingPending->room_no ?? $existingPending->unit->room_no ?? 'N/A') .
                                     ' awaiting manager approval. Please wait for it to be reviewed.',
                    ];
                }

                /* GUARD 4 — never two live leases on the same unit/bed. */
                $duplicate = Lease::where('user_id', $user->id)
                    ->where('unit_id', $unit->id)
                    ->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
                    ->when($request->bed_number, fn($q) => $q->where('bed_number', $request->bed_number))
                    ->lockForUpdate()
                    ->exists();

                if ($duplicate) {
                    return [
                        'type'    => 'info',
                        'message' => 'You have already applied for this room. Duplicate applications are not recorded.',
                    ];
                }

                /* GUARD 5 — the bed must still be free. */
                if ($unit->type === 'Bed-Spacer') {
                    if (! $request->bed_number) {
                        return ['type' => 'error', 'message' => 'Please select a bed for this Bed-Spacer unit.'];
                    }

                    $bedTaken = Lease::where('unit_id', $unit->id)
                        ->where('bed_number', $request->bed_number)
                        ->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
                        ->lockForUpdate()
                        ->exists();

                    if ($bedTaken) {
                        return ['type' => 'error', 'message' => 'That bed has just been taken. Please choose another one.'];
                    }
                } elseif ($unit->status !== 'vacant') {
                    return ['type' => 'error', 'message' => 'That room is no longer available.'];
                }

                $startDate = \Carbon\Carbon::parse($request->start_date);

                $lease = Lease::create([
                    'user_id'        => $user->id,
                    'unit_id'        => $unit->id,
                    'lea_start_date' => $startDate,
                    'lea_end_date'   => $startDate->copy()->addYear(),
                    'lea_status'     => 'pending',
                    'room_no'        => $unit->room_no,
                    'bed_number'     => $request->bed_number ?? null,
                    'monthly_rent'   => $unit->room_price ?? 0,
                    'renewal_count'  => 0,
                    'due_date'       => $startDate->copy()->addMonth(),
                ]);

                $managers = \App\Models\User::where('role', 'manager')->get();
                foreach ($managers as $manager) {
                    Notification::create([
                        'user_id' => $manager->id,
                        'title'   => 'Request Add Unit',
                        'message' => $user->name . ' requested to add unit room ' . ($lease->room_no ?? 'N/A'),
                    ]);
                }

                return ['type' => 'success', 'message' => 'Your lease application has been submitted!'];
            });
        } catch (\Throwable $e) {
            Log::error('Lease application failed for user ' . $user->id . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'We could not submit your application. Please try again.');
        } finally {
            $lock->release();
        }

        if ($outcome['type'] === 'success') {
            return redirect()->route('tenant.leases')->with('success', $outcome['message']);
        }

        return redirect()->back()->with($outcome['type'], $outcome['message']);
    }

    public function requestMoveOut(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $lock = Cache::lock('move-out-request:lease:' . $id, 10);

        if (! $lock->get()) {
            return redirect()->route('tenant.leases')
                ->with('info', 'Your move out request is already being submitted.');
        }

        try {
            $lease = Lease::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (! $lease) {
                return redirect()->route('tenant.leases')
                    ->with('error', 'Lease not found or you do not have permission to act on it.');
            }

            // Check if lease is active or pending
            if (!in_array($lease->lea_status, ['active', 'pending'])) {
                return redirect()->route('tenant.leases')
                    ->with('error', 'You can only request to move out from active or pending leases.');
            }

            // Check if move out already requested — stops duplicate notifications too
            if ($lease->move_out_requested) {
                return redirect()->route('tenant.leases')
                    ->with('info', 'Move out request has already been submitted for this lease.');
            }

            $lease->move_out_requested = true;
            $lease->move_out_reason    = $request->reason;
            $lease->save();

            // Create notification for manager(s)
            $managers = \App\Models\User::where('role', 'manager')->get();
            foreach ($managers as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'title'   => 'Move Out Request',
                    'message' => auth()->user()->name . ' has requested to move out from Room ' .
                                 ($lease->room_no ?? 'N/A') . '. Reason: ' . $request->reason,
                ]);
            }
        } finally {
            $lock->release();
        }

        return redirect()->route('tenant.leases')
            ->with('success', 'Your move out request has been submitted. The manager will review it shortly.');
    }

    /**
     * The tenant asks to renew (v19). Nothing about the lease changes here —
     * it only unlocks the manager's Renew action and tells the office.
     */
    public function requestRenewal($id)
    {
        $lock = Cache::lock('renewal-request:lease:' . $id, 10);

        if (! $lock->get()) {
            return redirect()->route('tenant.leases')->with('info', 'Your renewal request is already being submitted.');
        }

        try {
            $lease = Lease::where('id', $id)->where('user_id', auth()->id())->first();

            if (! $lease) {
                return redirect()->route('tenant.leases')
                    ->with('error', 'Lease not found or you do not have permission to act on it.');
            }

            if ($lease->renewal_requested) {
                return redirect()->route('tenant.leases')
                    ->with('info', 'You have already asked to renew this lease. The office will be in touch.');
            }

            if (! $lease->canRequestRenewal()) {
                $opens = $lease->renewalRequestOpensOn();

                $why = match (true) {
                    strtolower((string) $lease->lea_status) !== 'active' => 'Only an active lease can be renewed.',
                    (bool) $lease->move_out_requested                   => 'You have a move out request open. Cancel it first if you want to stay.',
                    $opens !== null && $opens->isFuture()               => 'Renewal requests open on ' . $opens->format('M d, Y')
                        . ', ' . (int) config('leases.renewal_request_window_days', 60) . ' days before your lease ends.',
                    default                                             => 'This lease cannot be renewed right now. Please contact the office.',
                };

                return redirect()->route('tenant.leases')->with('error', $why);
            }

            $lease->renewal_requested    = true;
            $lease->renewal_requested_at = now();
            $lease->save();

            $managers = \App\Models\User::where('role', 'manager')->get();

            foreach ($managers as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'title'   => 'Renewal Request',
                    'message' => auth()->user()->name . ' has asked to renew the lease on Room '
                        . ($lease->room_no ?? 'N/A') . ' (ends '
                        . optional($lease->lea_end_date)->format('M d, Y') . '). '
                        . 'Renew Lease is now available on Tenant Management.',
                ]);
            }
        } finally {
            $lock->release();
        }

        return redirect()->route('tenant.leases')->with('success',
            'Renewal requested. The office will review it — on renewal your rent becomes PHP '
            . number_format($lease->nextRenewalRent(), 2) . ' a month (the 5% yearly increase).');
    }

    /** The tenant changes their mind before the manager acts. */
    public function cancelRenewal($id)
    {
        $lease = Lease::where('id', $id)->where('user_id', auth()->id())->first();

        if (! $lease) {
            return redirect()->route('tenant.leases')
                ->with('error', 'Lease not found or you do not have permission to act on it.');
        }

        if (! $lease->renewal_requested) {
            return redirect()->route('tenant.leases')->with('info', 'There is no renewal request on this lease.');
        }

        $lease->renewal_requested    = false;
        $lease->renewal_requested_at = null;
        $lease->save();

        foreach (\App\Models\User::where('role', 'manager')->get() as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title'   => 'Renewal Request Withdrawn',
                'message' => auth()->user()->name . ' withdrew their renewal request for Room ' . ($lease->room_no ?? 'N/A') . '.',
            ]);
        }

        return redirect()->route('tenant.leases')->with('success', 'Your renewal request has been withdrawn.');
    }
}
