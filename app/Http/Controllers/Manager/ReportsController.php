<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Reports — on-screen pages only.
 *
 *   GET /manager/reports            index()   the report picker
 *   GET /manager/reports/{report}   show()    the HTML table for one report
 *
 * PDF/Excel output lives in ReportExportController.
 * Recording and confirming payments lives in PaymentRecordController.
 *
 * The four report keys are: active-tenants, payment-history, lease-summary,
 * maintenance-requests. Each `case` below builds its own query — see
 * docs/PROJECT-MAP.md, "Search filters are duplicated three times".
 */
class ReportsController extends Controller
{
    public function index()
    {
        $reports = [
            'active-tenants'       => 'Active Tenants',
            'payment-history'      => 'Payment History',
            'lease-summary'        => 'Lease Information',
            'maintenance-requests' => 'Maintenance Requests',
            'renewal-history'      => 'Renewal History',
            'renovation-requests'  => 'Renovation Requests',
            'feedback'              => 'Tenant Feedback',
        ];

        return view('manager.reports.index', compact('reports'));
    }

    public function show(Request $request, $report)
    {
        $total = 0;
        $currentFilter = '';
        $dateRangeLabel = null;
        $data = collect();
        $title = '';
        $availableUnits = null;
        $tenantsForPayment = [];

        switch ($report) {
            case 'active-tenants':
                $query = User::with(['tenantApplication', 'leases' => function($q) {
                    $q->where('lea_status', 'active')->latest('created_at');
                }])->where('role', 'tenant')->where('status', 'approved');

                if ($request->filled('search')) {
                    $search = $request->search;
                    $searchLower = strtolower(str_replace([' ', '-'], '', $search));

                    if ($searchLower === 'inactive') {
                        // Get users with terminated or no active leases
                        $query->where(function($q) {
                            $q->whereDoesntHave('leases', function($subQ) {
                                $subQ->where('lea_status', 'active');
                            });
                        });
                    } else {
                        $query->where(function ($q) use ($search) {
                            $q->whereHas('tenantApplication', function($subQ) use ($search) {
                                $subQ->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('unit_type', 'like', "%{$search}%")
                                    ->orWhere('employment_status', 'like', "%{$search}%");
                            })
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });
                    }
                }

                $total = $query->count();
                $data = $query->paginate(10);
                $title = "Active Tenants";
                break;

            case 'payment-history':

                $query = Payment::with(['tenant', 'lease.unit']);

                // Date filter (shortcuts: This Month / Previous Month / This
                // Year / Past Year, or an explicit date_from/date_to range).
                // The same call is made in ReportExportController for the PDF
                // preview and download, so whatever is picked here also
                // applies to the export.
                $dateRange = \App\Support\DateRangeResolver::resolve($request);
                if ($dateRange) {
                    [$rangeStart, $rangeEnd, $dateRangeLabel] = $dateRange;
                    $query->whereBetween('pay_date', [$rangeStart, $rangeEnd]);
                }

                // ✅ Search filter - search across ALL visible columns
                if ($request->filled('search')) {
                    $search = $request->search;

                    $query->where(function($q) use ($search) {

                        // ✅ 1. Search by Tenant Name / Email
                        $q->whereHas('tenant', function($tenantQuery) use ($search) {
                            $tenantQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });

                        // ✅ 2. Search by Amount
                        if (is_numeric($search)) {
                            $q->orWhere('pay_amount', 'like', "%{$search}%");
                        }

                        // ✅ 3. Search by Date (Multiple Formats)
                        $dateFormats = ['Y-m-d', 'm/d/Y', 'M d, Y', 'F d, Y', 'Y', 'M Y', 'F Y'];
                        foreach ($dateFormats as $format) {
                            try {
                                $parsedDate = \Carbon\Carbon::createFromFormat($format, $search);
                                $q->orWhereDate('pay_date', $parsedDate->format('Y-m-d'));
                                break;
                            } catch (\Exception $e) {}
                        }

                        // ✅ Search by Month Name
                        $monthMap = [
                            'january'=>1,'jan'=>1, 'february'=>2,'feb'=>2,
                            'march'=>3,'mar'=>3, 'april'=>4,'apr'=>4,
                            'may'=>5, 'june'=>6,'jun'=>6,
                            'july'=>7,'jul'=>7, 'august'=>8,'aug'=>8,
                            'september'=>9,'sep'=>9, 'october'=>10,'oct'=>10,
                            'november'=>11,'nov'=>11, 'december'=>12,'dec'=>12
                        ];

                        $searchLower = strtolower($search);
                        if (isset($monthMap[$searchLower])) {
                            $q->orWhereMonth('pay_date', $monthMap[$searchLower]);
                        }

                        // ✅ Search by Year
                        if (strlen($search) == 4 && is_numeric($search)) {
                            $q->orWhereYear('pay_date', $search);
                        }

                        // ✅ 4. Search by Payment For
                        $q->orWhere('payment_for', 'like', "%{$search}%");

                        // ✅ 5. Search by Status
                        $q->orWhere('pay_status', 'like', "%{$search}%");

                        // ✅ 6. Additional Fields
                        $q->orWhere('pay_method', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('account_no', 'like', "%{$search}%");

                        // ✅ 7. Search by Room / Unit
                        $q->orWhereHas('lease', function($leaseQuery) use ($search) {
                            $leaseQuery->where('room_no', 'like', "%{$search}%")
                                ->orWhereHas('unit', function($unitQuery) use ($search) {
                                    $unitQuery->where('type', 'like', "%{$search}%")
                                            ->orWhere('room_no', 'like', "%{$search}%");
                                });
                        });

                    });

                    $currentFilter = $search;
                }

                // ✅ Tenants for Make Payment Modal
                // Only list tenants who actually owe something: an outstanding
                // deposit / rent / utility / penalty on a live lease, or a payment
                // still sitting in Pending. Everyone else is fully settled and has
                // nothing for the manager to record.
                $tenantsForPayment = \App\Models\User::with(['leases' => function ($q) {
                        $q->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
                          ->with('unit');
                    }])
                    ->where('role', 'tenant')
                    ->where('status', 'approved')
                    ->where(function ($outer) {
                        // (a) a live lease carrying a balance
                        $outer->whereHas('leases', function ($q) {
                            $q->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])
                              ->where(function ($balance) {
                                  $balance->where('deposit_balance', '>', 0)
                                          ->orWhere('rent_balance', '>', 0)
                                          ->orWhere('utility_balance', '>', 0)
                                          ->orWhere('penalty_fee', '>', 0);
                              });
                        })
                        // (b) or a payment awaiting confirmation
                        ->orWhereHas('leases.payments', function ($q) {
                            $q->whereIn('pay_status', ['Pending', 'pending']);
                        });
                    })
                    ->orderBy('first_name')
                    ->get();

                // ✅ Total Accepted Payments
                $total = (clone $query)
                    ->where('pay_status', 'Accepted')
                    ->sum('pay_amount');

                // ✅ Paginated Data
                $data = $query->latest()->paginate(10);

                $title = "Payment History per Tenant";

            break;

            case 'lease-summary':
                $query = User::with(['tenantApplication', 'leases' => function($q) {
                    $q->latest('created_at')->with('unit'); // Get all leases regardless of status to preserve history
                }])
                ->where('role', 'tenant')
                ->where('status', 'approved');

                $availableUnits = Unit::with(['leases' => fn($query) => $query->whereIn('lea_status', ['active', 'pending'])])
                    ->where(function ($query) {
                        $query->where('status', 'vacant')
                            ->orWhere(function ($bedQuery) {
                                $bedQuery->where('type', 'Bed-Spacer')
                                    ->whereColumn('no_of_occupants', '<', 'capacity');
                            });
                    })
                    ->get();

                // ✅ Comprehensive search filter - search ALL visible columns
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        // 1. Search by Tenant Name (column: Tenant)
                        $q->where('email', 'like', "%{$search}%")
                          ->orWhere('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);

                        // 2. Search by Unit Type (column: Unit Type)
                        $q->orWhereHas('tenantApplication', function($subQ) use ($search) {
                            $subQ->where('full_name', 'like', "%{$search}%")
                                 ->orWhere('unit_type', 'like', "%{$search}%");
                        });

                        // 3. Search by Lease fields (Start Date, End Date, Term, Status, Room)
                        $q->orWhereHas('leases', function($leaseQuery) use ($search) {
                            // Search by lease status
                            $leaseQuery->where('lea_status', 'like', "%{$search}%")
                                      ->orWhere('room_no', 'like', "%{$search}%")
                                      ->orWhere('lea_terms', 'like', "%{$search}%");

                            // Search by lease dates
                            try {
                                $dateFormats = ['Y-m-d', 'm/d/Y', 'M d, Y', 'F d, Y', 'Y', 'M Y', 'F Y'];
                                foreach ($dateFormats as $format) {
                                    try {
                                        $parsedDate = \Carbon\Carbon::createFromFormat($format, $search);
                                        $leaseQuery->orWhereDate('lea_start_date', $parsedDate->format('Y-m-d'))
                                                  ->orWhereDate('lea_end_date', $parsedDate->format('Y-m-d'));
                                        break;
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                                // Search by month name
                                $monthMap = [
                                    'january' => 1, 'jan' => 1,
                                    'february' => 2, 'feb' => 2,
                                    'march' => 3, 'mar' => 3,
                                    'april' => 4, 'apr' => 4,
                                    'may' => 5,
                                    'june' => 6, 'jun' => 6,
                                    'july' => 7, 'jul' => 7,
                                    'august' => 8, 'aug' => 8,
                                    'september' => 9, 'sep' => 9,
                                    'october' => 10, 'oct' => 10,
                                    'november' => 11, 'nov' => 11,
                                    'december' => 12, 'dec' => 12,
                                ];
                                $searchLower = strtolower($search);
                                if (isset($monthMap[$searchLower])) {
                                    $leaseQuery->orWhereMonth('lea_start_date', $monthMap[$searchLower])
                                              ->orWhereMonth('lea_end_date', $monthMap[$searchLower]);
                                }
                                // Search by year
                                if (strlen($search) == 4 && is_numeric($search)) {
                                    $leaseQuery->orWhereYear('lea_start_date', $search)
                                              ->orWhereYear('lea_end_date', $search);
                                }
                            } catch (\Exception $e) {
                                // Continue with other searches
                            }

                            // Search by unit type and room number through lease->unit
                            $leaseQuery->orWhereHas('unit', function($unitQuery) use ($search) {
                                $unitQuery->where('type', 'like', "%{$search}%")
                                         ->orWhere('room_no', 'like', "%{$search}%");
                            });
                        });
                    });
                    $currentFilter = $request->search;
                }
                $total = $query->count();
                $data = $query->paginate(10);
                $title = "Lease Summary";
                break;

           case 'maintenance-requests':
                // No changes needed - already has search
                $query = \App\Models\MaintenanceRequest::with(['tenant', 'unit']);

                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }
                if ($request->filled('urgency')) {
                    $query->where('urgency', $request->urgency);
                }

               if ($request->filled('search')) {
                    $search = $request->search;

                    $query->where(function ($q) use ($search) {
                        $q->where('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('urgency', 'like', "%{$search}%")
                        ->orWhereHas('tenant', function($subQ) use ($search) {
                            $subQ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });
                    });
                }


                $total = (clone $query)->count();
                $data = $query->orderBy('created_at', 'desc')->paginate(10);
                $title = "Maintenance Requests";
                break;

            // ---------------- RENEWAL HISTORY ----------------
            // One row per renewal EVENT, not per lease: a lease renewed three
            // times appears three times, which is the point — the question
            // this answers is "who has renewed, when, and at what rent".
            case 'renewal-history':
                $query = \App\Models\LeaseRenewal::with([
                    'tenant.tenantApplication',
                    'lease.unit',
                    'unit',
                    'renewedBy',
                ]);

                if ($request->filled('source')) {
                    $query->where('source', $request->source);
                }

                if ($request->filled('search')) {
                    $search = $request->search;

                    $query->where(function ($q) use ($search) {
                        $q->where('room_no', 'like', "%{$search}%")
                          ->orWhere('source', 'like', "%{$search}%");

                        // Tenant name / email
                        $q->orWhereHas('tenant', function ($t) use ($search) {
                            $t->where('email', 'like', "%{$search}%")
                              ->orWhere('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });

                        // Rent figures
                        $numeric = preg_replace('/[^0-9.]/', '', $search);
                        if ($numeric !== '' && is_numeric($numeric)) {
                            $q->orWhere('previous_rent', 'like', "%{$numeric}%")
                              ->orWhere('new_rent', 'like', "%{$numeric}%");
                        }

                        // Dates. Uses whereMonth/whereYear rather than the raw
                        // DATE_FORMAT() the older reports use, so this one also
                        // works on SQLite (see docs/PROJECT-MAP.md §3).
                        $monthMap = [
                            'january'=>1,'jan'=>1,'february'=>2,'feb'=>2,'march'=>3,'mar'=>3,
                            'april'=>4,'apr'=>4,'may'=>5,'june'=>6,'jun'=>6,'july'=>7,'jul'=>7,
                            'august'=>8,'aug'=>8,'september'=>9,'sep'=>9,'october'=>10,'oct'=>10,
                            'november'=>11,'nov'=>11,'december'=>12,'dec'=>12,
                        ];
                        $searchLower = strtolower($search);
                        if (isset($monthMap[$searchLower])) {
                            $q->orWhereMonth('created_at', $monthMap[$searchLower]);
                        }
                        if (strlen($search) === 4 && is_numeric($search)) {
                            $q->orWhereYear('created_at', $search);
                        }
                    });

                    $currentFilter = $search;
                }

                $total = (clone $query)->count();
                $data  = $query->orderBy('created_at', 'desc')->paginate(10);
                $title = "Renewal History";
                break;

            // ---------------- RENOVATION REQUESTS ----------------
            // Moved here from the per-tenant modal in manager/tenants. The
            // approve/deny controls came with it — see the report view.
            case 'renovation-requests':
                $query = \App\Models\RenovationRequest::with([
                    'tenant',
                    'lease.unit',
                    'unit',
                    'decidedBy',
                ]);

                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }

                if ($request->filled('search')) {
                    $search = $request->search;

                    $query->where(function ($q) use ($search) {
                        $q->where('description', 'like', "%{$search}%")
                          ->orWhere('renovation_type', 'like', "%{$search}%")
                          ->orWhere('status', 'like', "%{$search}%")
                          ->orWhere('room_no', 'like', "%{$search}%")
                          ->orWhere('manager_remarks', 'like', "%{$search}%")
                          // Older requests still carry a title.
                          ->orWhere('title', 'like', "%{$search}%");

                        $q->orWhereHas('tenant', function ($t) use ($search) {
                            $t->where('email', 'like', "%{$search}%")
                              ->orWhere('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });
                    });

                    $currentFilter = $search;
                }

                $total = (clone $query)->count();
                $data  = $query->orderBy('created_at', 'desc')->paginate(10);
                $title = "Renovation Requests";
                break;

            // ---------------- TENANT FEEDBACK ----------------
            case 'feedback':
                $query = \App\Models\Feedback::with(['tenant', 'respondedBy']);

                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }

                if ($request->filled('category')) {
                    $query->where('category', $request->category);
                }

                if ($request->filled('search')) {
                    $search = $request->search;

                    $query->where(function ($q) use ($search) {
                        $q->where('subject', 'like', "%{$search}%")
                          ->orWhere('message', 'like', "%{$search}%")
                          ->orWhere('category', 'like', "%{$search}%")
                          ->orWhere('status', 'like', "%{$search}%")
                          ->orWhere('manager_response', 'like', "%{$search}%");

                        $q->orWhereHas('tenant', function ($t) use ($search) {
                            $t->where('email', 'like', "%{$search}%")
                              ->orWhere('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });
                    });

                    $currentFilter = $search;
                }

                $total = (clone $query)->count();
                $data  = $query->orderBy('created_at', 'desc')->paginate(10);
                $title = "Tenant Feedback";
                break;

            default:
                abort(404, 'Report not found.');
        }

        return view('manager.reports.show', compact(
            'data', 'title', 'report', 'total', 'currentFilter', 'dateRangeLabel', 'availableUnits', 'tenantsForPayment'
        ));
    }
}
