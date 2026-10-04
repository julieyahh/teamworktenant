<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Notification;
use App\Services\MaintenanceCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manager view of maintenance requests.
 *
 *   GET   /manager/requests/{id}          show()
 *   PATCH /manager/requests/{id}/status   updateStatus()
 *
 * Costs, work items and cost responsibility are handled by
 * Manager\MaintenanceCostController — they render on the same page.
 *
 * Status changes go through MaintenanceCostService::changeStatus() so every
 * move is written to maintenance_status_logs. `status` only ever holds the
 * current value, so without that log there is no answering "how long did this
 * take" or "who marked it completed" after the fact.
 */
class MaintenanceRequestController extends Controller
{
    public function __construct(private MaintenanceCostService $costs)
    {
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:Pending,In Progress,Completed,Cancelled',
            'notes'  => 'nullable|string|max:1000',
        ]);

        $maintenanceRequest = MaintenanceRequest::findOrFail($id);

        $this->costs->changeStatus(
            $maintenanceRequest,
            $data['status'],
            $data['notes'] ?? null,
            Auth::user()
        );

        // Keep the unit's own status in step with the work happening in it.
        $unit = $maintenanceRequest->unit;

        if ($unit) {
            $hasActiveLease = Lease::where('id', $maintenanceRequest->lease_id)
                ->where('unit_id', $maintenanceRequest->unit_id)
                ->whereIn('lea_status', ['active', 'Active'])
                ->exists();

            if ($data['status'] === 'In Progress') {
                $unit->update(['status' => 'maintenance']);
            } elseif ($data['status'] === 'Completed') {
                $unit->update(['status' => $hasActiveLease ? 'occupied' : 'pending']);
            }
        }

        if ($maintenanceRequest->tenant_id) {
            Notification::create([
                'user_id' => $maintenanceRequest->tenant_id,
                'title'   => 'Maintenance Update',
                'message' => 'Your maintenance request for ' . ($maintenanceRequest->room_no ?? 'your unit')
                    . ' is now marked "' . $data['status'] . '".'
                    . (!empty($data['notes']) ? ' Note: ' . $data['notes'] : ''),
            ]);
        }

        $extra = '';

        // A finished repair with a cost and nobody assigned to pay it is the
        // thing that quietly gets forgotten, so say so at the moment it happens.
        if ($data['status'] === 'Completed' && $maintenanceRequest->fresh()->needsResponsibilityDecision()) {
            $extra = ' This repair has costs recorded but nobody assigned to pay them — set the cost responsibility below.';
        }

        return back()->with('success', 'Maintenance request status updated.' . $extra);
    }

    /**
     * GET /manager/requests/{id}  (route name: manager.requests.show)
     *
     * Renders resources/views/manager/requests/show.blade.php.
     */
    public function show($id)
    {
        $maintenanceRequest = MaintenanceRequest::with([
            'tenant', 'unit', 'lease',
            'workItems.createdBy',
            'statusLogs.changedBy',
            'chargedBy',
        ])->findOrFail($id);

        return view('manager.requests.show', [
            'request' => $maintenanceRequest,
        ]);
    }
}
