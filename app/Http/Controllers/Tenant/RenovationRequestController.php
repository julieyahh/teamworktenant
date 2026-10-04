<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\RenovationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-side renovation requests.
 *
 * DEPOSIT GATE
 *   A room with an unpaid deposit cannot be renovated. The deposit is what
 *   covers damage, so approving alterations before it is settled leaves the
 *   landlord holding the risk for changes to their own property.
 *
 *   The gate is applied in three places on purpose:
 *     - index()  filters the room dropdown, so a blocked room is not offered
 *     - store()  re-checks inside the transaction, because the dropdown is
 *                client-side and a stale page could still post a blocked id
 *     - the view explains WHY a room is missing, so it does not just look broken
 */
class RenovationRequestController extends Controller
{
    public function index()
    {
        $requests = RenovationRequest::where('tenant_id', Auth::id())
            ->with(['lease.unit', 'decidedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Only leases the tenant actually occupies can be renovated.
        $leases = Auth::user()->leases()
            ->whereIn('lea_status', ['active', 'Active'])
            ->with('unit')
            ->get();

        // ...and only those with the deposit settled.
        $activeLeases   = $leases->filter(fn ($lease) => ! $lease->hasUnpaidDeposit())->values();
        $blockedLeases  = $leases->filter(fn ($lease) => $lease->hasUnpaidDeposit())->values();

        return view('tenant.renovations', compact('requests', 'activeLeases', 'blockedLeases'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'lease_id'              => 'required|exists:leases,id',
            'description'           => 'required|string|max:2000',
            'renovation_type'       => 'required|string|in:' . implode(',', RenovationRequest::TYPES),
            // Only required when the tenant picked "Others" — otherwise the
            // dropdown value is the type and this field is ignored.
            'renovation_type_other' => 'nullable|string|max:100|required_if:renovation_type,' . RenovationRequest::TYPE_OTHERS,
            'estimated_cost'        => 'nullable|numeric|min:0|max:9999999',
            'preferred_date'        => 'required|date|after_or_equal:today',
            'attachment'            => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ], [
            'renovation_type_other.required_if' => 'Please describe the type of renovation.',
        ]);

        // What actually gets stored in renovation_type. Picking "Others" and
        // typing "Aircon installation" stores "Aircon installation", not
        // "Others" — otherwise the manager's report would be full of rows
        // labelled Others with the real answer buried in the description.
        $renovationType = $request->renovation_type === RenovationRequest::TYPE_OTHERS
            ? trim((string) $request->renovation_type_other)
            : $request->renovation_type;

        $user = Auth::user();

        // Anti double-submit: same lock pattern as the lease application.
        $lock = Cache::lock('renovation-request:user:' . $user->id, 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'Your request is already being submitted. Please wait a moment.');
        }

        try {
            $outcome = DB::transaction(function () use ($request, $user, $renovationType) {

                $lease = Lease::where('id', $request->lease_id)
                    ->where('user_id', $user->id)
                    ->whereIn('lea_status', ['active', 'Active'])
                    ->with('unit')
                    ->lockForUpdate()
                    ->first();

                if (! $lease) {
                    return ['type' => 'error', 'message' => 'Selected room could not be found or the lease is not active.'];
                }

                // Deposit gate. Re-checked here rather than trusting index()'s
                // filtered dropdown: the form is rendered client-side, so a
                // stale tab could still post a lease id that has since fallen
                // behind on its deposit.
                if ($lease->hasUnpaidDeposit()) {
                    return [
                        'type'    => 'error',
                        'message' => 'You have an unpaid deposit of PHP ' .
                                     number_format((float) $lease->deposit_balance, 2) .
                                     ' on room ' . ($lease->room_no ?? 'N/A') .
                                     '. Renovation requests open up once the deposit is settled.',
                    ];
                }

                // One open renovation request per room at a time.
                $alreadyOpen = RenovationRequest::where('tenant_id', $user->id)
                    ->where('lease_id', $lease->id)
                    ->where('status', RenovationRequest::STATUS_PENDING)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyOpen) {
                    return [
                        'type'    => 'info',
                        'message' => 'You already have a renovation request pending for this room. ' .
                                     'Please wait for the manager to review it before submitting another.',
                    ];
                }

                $attachmentPath = null;
                if ($request->hasFile('attachment')) {
                    $attachmentPath = $request->file('attachment')->store('renovations', 'public');
                }

                // No `title` — the field was removed from the form. The type
                // and description together say what the request is. The column
                // is kept nullable so the titles on older requests survive.
                RenovationRequest::create([
                    'tenant_id'       => $user->id,
                    'lease_id'        => $lease->id,
                    'unit_id'         => $lease->unit_id,
                    'room_no'         => $lease->room_no ?? ($lease->unit->room_no ?? null),
                    'description'     => $request->description,
                    'renovation_type' => $renovationType,
                    'estimated_cost'  => $request->estimated_cost,
                    'preferred_date'  => $request->preferred_date,
                    'attachment'      => $attachmentPath,
                    'status'          => RenovationRequest::STATUS_PENDING,
                ]);

                foreach (User::where('role', 'manager')->get() as $manager) {
                    Notification::create([
                        'user_id' => $manager->id,
                        'title'   => 'Renovation Request',
                        'message' => $user->name . ' submitted a renovation request for room ' .
                                     ($lease->room_no ?? 'N/A') . ': ' . $renovationType,
                    ]);
                }

                return ['type' => 'success', 'message' => 'Renovation request submitted. The manager will review it shortly.'];
            });
        } catch (\Throwable $e) {
            Log::error('Renovation request failed for user ' . $user->id . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'We could not submit your request. Please try again.');
        } finally {
            $lock->release();
        }

        return redirect()->route('tenant.renovations')->with($outcome['type'], $outcome['message']);
    }

    public function cancel($id)
    {
        $renovation = RenovationRequest::where('tenant_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (! $renovation) {
            return redirect()->back()->with('error', 'Renovation request not found.');
        }

        if (! $renovation->isPending()) {
            return redirect()->back()->with('info', 'Only pending requests can be cancelled.');
        }

        $renovation->update(['status' => RenovationRequest::STATUS_CANCELLED]);

        foreach (User::where('role', 'manager')->get() as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title'   => 'Renovation Request Cancelled',
                'message' => Auth::user()->name . ' cancelled the renovation request for room ' .
                             ($renovation->room_no ?? 'N/A') . '.',
            ]);
        }

        return redirect()->back()->with('success', 'Renovation request cancelled.');
    }
}
