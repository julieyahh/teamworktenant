<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\DueDateMonitor;

class DashboardController extends Controller
{
    // Manager Overview
    public function dashboard(DueDateMonitor $monitor)
    {
        // Total units dynamically from the database
        $totalUnits = \App\Models\Unit::count();

        // Occupied units (status = 'occupied')
        $occupiedUnits = \App\Models\Unit::where('status', 'occupied')->count();

        // Vacant units
        $vacantUnits = $totalUnits - $occupiedUnits;

        // Pending applications (users with tenant role and pending status)
        $pendingApplications = \App\Models\User::where('role', 'tenant')
                                    ->where('status', 'pending')
                                    ->count();

        // Approved tenants
        $approvedTenants = \App\Models\User::where('role', 'tenant')
                                ->where('status', 'approved')
                                ->count();

        // Rejected tenants
        $rejectedTenants = \App\Models\User::where('role', 'tenant')
                                ->where('status', 'rejected')
                                ->count();

        // Optional detailed lists
        $pendingTenants = \App\Models\User::where('role', 'tenant')
                                ->where('status', 'pending')
                                ->latest()
                                ->limit(7)
                                ->get();
                                

        $approvedTenantList = \App\Models\User::where('role', 'tenant')
                                    ->where('status', 'approved')
                                    ->latest()
                                    ->limit(7)
                                    ->get();

        // Due-date monitor (v18): rent, utility bills and lease end dates that
        // are overdue or fall due in the next 30 days. See DueDateMonitor.
        $due        = $monitor->build();
        $dueItems   = $due['items'];
        $dueSummary = $due['summary'];

        return view('manager.dashboard', compact(
            'dueItems',
            'dueSummary',
            'totalUnits',
            'occupiedUnits',
            'vacantUnits',
            'pendingApplications',
            'approvedTenants',
            'rejectedTenants',
            'pendingTenants',
            'approvedTenantList'
        ));
    }




    // Other pages
    /**
     * @deprecated NOT ROUTED. The live /manager/reports page is
     * Manager\ReportsController@index. This method renders view('manager.reports'),
     * which does not exist — it would throw if it were ever wired up.
     */
    public function reports(Request $request)
    {
        $month = $request->input('month'); // format: YYYY-MM
        $query = User::where('role', 'tenant');

        if ($month) {
            $query->whereMonth('created_at', date('m', strtotime($month)))
                ->whereYear('created_at', date('Y', strtotime($month)));
        }

        $tenants = $query->get();

        $totalUnits = 50; // Example
        $occupiedUnits = $tenants->where('status', 'approved')->count();
        $vacantUnits = $totalUnits - $occupiedUnits;

        $tenantStatus = [
            'approved' => $tenants->where('status', 'approved')->count(),
            'pending'  => $tenants->where('status', 'pending')->count(),
            'rejected' => $tenants->where('status', 'rejected')->count(),
        ];

        return view('manager.reports', compact(
            'tenantStatus', 
            'occupiedUnits', 
            'vacantUnits',
            'totalUnits',
            'month'
        ));
    }


    public function settings()
    {
        return view('manager.settings');
    }

    /**
     * @deprecated NOT ROUTED. The live /manager/tenants page is
     * Manager\ManagerController@tenants.
     */
    public function manageTenants()
    {
        $pendingTenants = User::where('role', 'tenant')
                            ->where('status', 'pending')
                            ->get();

        $approvedTenantList = User::where('role', 'tenant')
                                ->where('status', 'approved')
                                ->get();

        return view('manager.tenants', compact('pendingTenants', 'approvedTenantList'));
    }

}
