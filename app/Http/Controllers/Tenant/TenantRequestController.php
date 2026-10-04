<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaintenanceRequest; // 👈 make sure this matches your model name
use App\Models\Notification;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;

class TenantRequestController extends Controller
{

    public function index()
    {
        $requests = MaintenanceRequest::where('tenant_id', Auth::id())
                        ->with('lease.unit')
                        ->orderBy('created_at', 'desc')
                        ->get();

        // The list used to show `supposed_date` under a column headed
        // "Scheduled Date", which is the tenant's own PREFERRED date, not the
        // date the office booked. Those are different facts and the table now
        // shows both — see resources/views/tenant/request.blade.php.

        $activeLeases = Auth::user()->leases()
            ->whereIn('lea_status', ['active', 'pending'])
            ->whereHas('payments', function ($q) {
                $q->where('payment_for', 'deposit')
                ->where('pay_status', 'Accepted');
            })
            ->with('unit')
            ->get();

        return view('tenant.request', compact('requests', 'activeLeases'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'lease_id'      => 'required|exists:leases,id',
            'description'   => 'required|string',
            'urgency'       => 'required|string',
            'supposed_date' => 'required|date',
            'issue_image'   => 'required|image|mimes:jpg,jpeg,png',
        ]);

        $lease = Auth::user()->leases()
            ->where('id', $request->lease_id)
            ->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
            ->with('unit')
            ->first();

        if (!$lease) {
            return redirect()->back()->with('error', 'Selected room could not be found or is not active.');
        }

        // ✅ Store the image (same pattern as your working example)
        $issueImagePath = null;
        if ($request->hasFile('issue_image')) {
            $issueImagePath = $request->file('issue_image')->store('issues', 'public');
        }

        // ✅ Save to database
        \App\Models\MaintenanceRequest::create([
            'tenant_id'     => Auth::id(),
            'lease_id'      => $lease->id,
            'unit_id'      =>  $lease->unit_id,
            'unit_type'     => $lease->unit->type ?? 'Unit',
            'room_no'       => $lease->room_no,
            'description'   => $request->description,
            'urgency'       => $request->urgency,
            'supposed_date' => $request->supposed_date,
            'status'        => 'Pending',
            'issue_image'   => $issueImagePath,
        ]);

        // Create notification
        $managers = \App\Models\User::where('role', 'manager')->get();
        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title' => 'Maintenance Request',
                'message' => auth()->user()->name.' has submitted a maintenance request for unit ' . $lease->room_no . '.',
            ]);
        }

        return redirect()->route('tenant.requests')->with('success', 'Request submitted successfully!');
    }

    /**
     * GET /tenant/requests/{id}   (tenant.requests.show)
     *
     * The tenant's view of one repair, start to finish.
     *
     * WHY THIS EXISTS
     *   Everything the office records about a repair — who is coming, when,
     *   what they found, what they did, what it cost and who is paying —
     *   was written to maintenance_requests and then shown to nobody but the
     *   manager. The tenant could not even see the photo they uploaded. A
     *   tenant whose only signal is a status pill moving from "Pending" to
     *   "In Progress" has no way to know a technician is booked for Thursday,
     *   so they ring the office to ask, which is the cost of not building
     *   this page.
     *
     * WHAT IS DELIBERATELY NOT SHOWN
     *   The cost breakdown appears only when the tenant is actually being
     *   asked to pay some of it. When the landlord is absorbing the repair,
     *   what it cost the landlord is not the tenant's business, and showing
     *   it invites an argument about a bill nobody is sending. See
     *   $showCosts below.
     */
    public function show($id)
    {
        $maintenance = MaintenanceRequest::with([
            'lease.unit',
            'unit',
            'workItems',
            'statusLogs',
        ])
            // Scoped to the signed-in tenant, not filtered in the view: a
            // tenant guessing another request's id must get a 404, not
            // somebody else's repair history.
            ->where('tenant_id', Auth::id())
            ->findOrFail($id);

        // The itemised costs are shown only when the tenant carries some of
        // them. 'shared' and 'tenant' both qualify; 'landlord' and
        // 'undetermined' do not.
        $showCosts = in_array($maintenance->cost_responsibility, ['tenant', 'shared'], true)
            && (float) $maintenance->total_cost > 0;

        return view('tenant.requests.show', [
            'request'   => $maintenance,
            'showCosts' => $showCosts,
        ]);
    }

    public function cancel($id)
    {
        $request = \App\Models\MaintenanceRequest::where('tenant_id', Auth::id())
                        ->where('id', $id)
                        ->firstOrFail();

        if (in_array($request->status, ['Pending', 'In Progress'])) {
            $request->update(['status' => 'Cancelled']);

            $managers = \App\Models\User::where('role', 'manager')->get();
            foreach ($managers as $manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'title' => 'Maintenance Request',
                    'message' => auth()->user()->name.' cancelled his/her maintenance request for unit ' . $request->room_no . '.',
                ]);
            }

            return redirect()->back()->with('success', 'Request cancelled successfully.');
        }

        return redirect()->back()->with('error', 'Unable to cancel this request.');
    }

}
