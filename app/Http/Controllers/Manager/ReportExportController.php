<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Reports — file output.
 *
 *   GET /manager/reports/{report}/export       export()         download
 *   GET /manager/reports/export/{report}       viewReportPdf()  inline preview
 *
 * WARNING: each method re-implements the search/filter logic from
 * ReportsController@show rather than sharing it, and the three copies are NOT
 * identical (payment-history in particular filters on different columns).
 * If a filter behaves differently on screen vs in the PDF, that is why —
 * fix all three places.
 */
class ReportExportController extends Controller
{
    public function viewReportPdf(Request $request, $report)
    {
        switch ($report) {

            // ---------------- PAYMENT HISTORY ----------------
            case 'payment-history':
                $query = Payment::with(['tenant', 'tenant.tenantApplication', 'tenant.leases', 'lease', 'lease.unit']);

                // Same date filter as the on-screen report (see the class
                // docblock) — a shortcut or custom range picked there must
                // still apply here, or the PDF preview would silently show
                // more rows than the manager was just looking at.
                $dateRange = \App\Support\DateRangeResolver::resolve($request);
                if ($dateRange) {
                    [$rangeStart, $rangeEnd, $dateRangeLabel] = $dateRange;
                    $query->whereBetween('pay_date', [$rangeStart, $rangeEnd]);
                }

                if ($request->filled('payment_for')) {
                    $query->where('payment_for', $request->payment_for);
                }

                if ($request->filled('search')) {
                    $search = $request->search;

                    $query->where(function($q) use ($search) {

                        // 1. Direct payment columns
                        $q->where('payment_for',      'like', "%{$search}%")
                        ->orWhere('pay_status',      'like', "%{$search}%")
                        ->orWhere('pay_method',      'like', "%{$search}%")
                        ->orWhere('reference_number','like', "%{$search}%")
                        ->orWhere('utility_type',    'like', "%{$search}%"); // Electricity / Water

                        // 2. Amount — handle partial numeric search e.g. "500" or "1,200"
                        $numericSearch = preg_replace('/[^0-9.]/', '', $search);
                        if (is_numeric($numericSearch) && $numericSearch !== '') {
                            $q->orWhereRaw('CAST(pay_amount AS CHAR) LIKE ?', ["%{$numericSearch}%"]);
                        }

                        // 3. Date — handle formats like "Jan 15 2024", "2024-01-15", "January"
                        $q->orWhereRaw("DATE_FORMAT(pay_date, '%M %d, %Y') LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("DATE_FORMAT(pay_date, '%b %d, %Y') LIKE ?",  ["%{$search}%"])
                        ->orWhereRaw("DATE_FORMAT(pay_date, '%Y-%m-%d') LIKE ?",   ["%{$search}%"])
                        ->orWhereRaw("DATE_FORMAT(pay_date, '%M') LIKE ?",         ["%{$search}%"])
                        ->orWhereRaw("DATE_FORMAT(pay_date, '%Y') LIKE ?",         ["%{$search}%"]);

                        // 4. Tenant name / email
                        $q->orWhereHas('tenant', function($userQ) use ($search) {
                            $userQ->where('email',      'like', "%{$search}%")
                                ->orWhere('first_name','like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });

                        // 5. Tenant application
                        $q->orWhereHas('tenant.tenantApplication', function($subQ) use ($search) {
                            $subQ->where('full_name',  'like', "%{$search}%")
                                ->orWhere('unit_type','like', "%{$search}%");
                        });

                        // 6. Lease — room, status, terms
                        $q->orWhereHas('lease', function($leaseQ) use ($search) {
                            $leaseQ->where('room_no',   'like', "%{$search}%")
                                ->orWhere('lea_status','like', "%{$search}%")
                                ->orWhere('lea_terms', 'like', "%{$search}%");
                        });

                        // 7. Unit type / room number
                        $q->orWhereHas('lease.unit', function($unitQ) use ($search) {
                            $unitQ->where('type',   'like', "%{$search}%")
                                ->orWhere('room_no','like', "%{$search}%");
                        });
                    });
                }

                $payments = $query->orderBy('pay_date', 'desc')->get();

                $pdf = Pdf::loadView('reports.pdf.payment-history', [
                    'payments'       => $payments,
                    'dateRangeLabel' => $dateRangeLabel ?? null,
                ])->setPaper('a4', 'landscape');

                return $pdf->stream('payment-history.pdf');

            // ---------------- TENANT REPORT ----------------
            case 'active-tenants':

                $pdfTitle = "active";
                $title = "Active Tenants";
                $query = User::with(['tenantApplication', 'leases' => function($q) {
                        $q->where('lea_status', 'active')->latest('created_at');
                    }])->where('role', 'tenant')->where('status', 'approved');

                    if ($request->filled('search')) {
                        $search = $request->search;
                        $searchLower = strtolower(str_replace([' ', '-'], '', $search));

                        if ($searchLower === 'inactive') {
                            // Get users with terminated or no active leases
                            $pdfTitle = "inactive";
                            $title = "Inactive Tenants";
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

                // ✅ Order alphabetically by full name
                $approvedTenants = $query->orderByRaw("CONCAT(first_name, ' ', last_name) ASC")->get();

                $pdf = Pdf::loadView('reports.pdf.active-tenants', [
                    'approvedTenants' => $approvedTenants,
                    'title' => $title,
                ])->setPaper('a4', 'landscape');

                return $pdf->stream($pdfTitle.'-tenants.pdf');

            // ---------------- MAINTENANCE REQUESTS ----------------
            case 'maintenance-requests':
                $requests = MaintenanceRequest::with(['tenant', 'tenant.leases', 'unit'])
                    ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
                    ->when($request->filled('urgency'), fn($q) => $q->where('urgency', $request->urgency))
                    ->when($request->filled('search'), function ($q) use ($request) {
                        $search = $request->search;

                        $q->where(function($q) use ($search) {

                            // 1. Direct maintenance_requests columns
                            $q->where('description', 'like', "%{$search}%")
                            ->orWhere('status',     'like', "%{$search}%")
                            ->orWhere('urgency',    'like', "%{$search}%")
                            ->orWhere('room_no',    'like', "%{$search}%")
                            ->orWhere('unit_type',  'like', "%{$search}%");

                            // 2. Scheduled date — e.g. "Jan 2024", "March", "2024"
                            $q->orWhereRaw("DATE_FORMAT(supposed_date, '%M %d, %Y') LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("DATE_FORMAT(supposed_date, '%b %d, %Y') LIKE ?",  ["%{$search}%"])
                            ->orWhereRaw("DATE_FORMAT(supposed_date, '%M') LIKE ?",          ["%{$search}%"])
                            ->orWhereRaw("DATE_FORMAT(supposed_date, '%Y') LIKE ?",          ["%{$search}%"]);

                            // 3. Tenant name
                            $q->orWhereHas('tenant', function($t) use ($search) {
                                $t->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name',  'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                            });

                            // 4. Unit type from related unit
                            $q->orWhereHas('unit', function($u) use ($search) {
                                $u->where('type',    'like', "%{$search}%")
                                ->orWhere('room_no','like', "%{$search}%");
                            });
                        });
                    })
                    ->latest()
                    ->get();

                $pdf = Pdf::loadView('reports.pdf.maintenance-requests', [
                    'requests' => $requests
                ])->setPaper('a4', 'landscape');

                return $pdf->stream('maintenance-requests.pdf');


            // ---------------- LEASE SUMMARY ----------------
            case 'lease-summary':
            $query = \App\Models\Lease::with(['user','user.tenantApplication', 'unit'])
                    ->whereHas('user', function($q) {
                        $q->where('role', 'tenant')
                        ->where('status', 'approved');
                    })
                    ->latest('created_at');

            // ✅ Comprehensive search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    // 1. Search by Tenant Name
                    $q->whereHas('user', function($userQ) use ($search) {
                        $userQ->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                    });

                    // 2. Search by Unit Type (from tenant application)
                    $q->orWhereHas('user.tenantApplication', function($appQ) use ($search) {
                        $appQ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('unit_type', 'like', "%{$search}%");
                    });

                    // 3. Search by Lease fields
                    $q->orWhere('lea_status', 'like', "%{$search}%")
                    ->orWhere('room_no', 'like', "%{$search}%")
                    ->orWhere('lea_terms', 'like', "%{$search}%");

                    // 4. Search by dates
                    try {
                        $dateFormats = ['Y-m-d', 'm/d/Y', 'M d, Y', 'F d, Y', 'Y', 'M Y', 'F Y'];
                        foreach ($dateFormats as $format) {
                            try {
                                $parsedDate = \Carbon\Carbon::createFromFormat($format, $search);
                                $q->orWhereDate('lea_start_date', $parsedDate->format('Y-m-d'))
                                ->orWhereDate('lea_end_date', $parsedDate->format('Y-m-d'));
                                break;
                            } catch (\Exception $e) {
                                continue;
                            }
                        }

                        $monthMap = [
                            'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2,
                            'march' => 3, 'mar' => 3, 'april' => 4, 'apr' => 4,
                            'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
                            'august' => 8, 'aug' => 8, 'september' => 9, 'sep' => 9,
                            'october' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11,
                            'december' => 12, 'dec' => 12,
                        ];
                        $searchLower = strtolower($search);
                        if (isset($monthMap[$searchLower])) {
                            $q->orWhereMonth('lea_start_date', $monthMap[$searchLower])
                            ->orWhereMonth('lea_end_date', $monthMap[$searchLower]);
                        }

                        if (strlen($search) == 4 && is_numeric($search)) {
                            $q->orWhereYear('lea_start_date', $search)
                            ->orWhereYear('lea_end_date', $search);
                        }
                    } catch (\Exception $e) {
                        // Continue
                    }

                    // 5. Search by Unit
                    $q->orWhereHas('unit', function($unitQuery) use ($search) {
                        $unitQuery->where('type', 'like', "%{$search}%")
                                ->orWhere('room_no', 'like', "%{$search}%");
                    });
                });
            }

            $data = $query->get();
            $total = $data->count();

            // ✅ REMOVE dd($data); - This was preventing PDF generation

            $pdf = Pdf::loadView('reports.pdf.lease-summary', [
                'data' => $data,
                'total' => $total
            ])->setPaper('a4', 'landscape');

            return $pdf->stream('lease-summary.pdf');

            // ---------------- RENEWAL HISTORY ----------------
            case 'renewal-history':
                $renewals = $this->renewalHistoryQuery($request)->get();

                $pdf = Pdf::loadView('reports.pdf.renewal-history', [
                    'renewals' => $renewals,
                    'search'   => $request->search,
                ])->setPaper('a4', 'landscape');

                return $pdf->stream('renewal-history.pdf');

            // ---------------- RENOVATION REQUESTS ----------------
            case 'renovation-requests':
                $requests = $this->renovationRequestsQuery($request)->get();

                $pdf = Pdf::loadView('reports.pdf.renovation-requests', [
                    'requests' => $requests,
                    'search'   => $request->search,
                ])->setPaper('a4', 'landscape');

                return $pdf->stream('renovation-requests.pdf');

            // ---------------- TENANT FEEDBACK ----------------
            case 'feedback':
                $feedback = $this->feedbackQuery($request)->get();

                $pdf = Pdf::loadView('reports.pdf.feedback', [
                    'feedback' => $feedback,
                    'search'   => $request->search,
                ])->setPaper('a4', 'landscape');

                return $pdf->stream('feedback.pdf');

            default:
                abort(404, 'Report not available for PDF view.');
        }
    }

    public function export(Request $request, $report)
    {
        switch ($report) {
            // ---------------- PAYMENT HISTORY ----------------
            case 'payment-history':
                $query = Payment::with(['tenant', 'lease.unit']);

                // Same date filter as the on-screen report and the inline
                // preview (see the class docblock) — this is the one that
                // matters most: it's what actually leaves the building.
                $dateRange = \App\Support\DateRangeResolver::resolve($request);
                if ($dateRange) {
                    [$rangeStart, $rangeEnd, $dateRangeLabel] = $dateRange;
                    $query->whereBetween('pay_date', [$rangeStart, $rangeEnd]);
                }

                // Filter by purpose (legacy support)
                if ($request->filled('payment_for')) {
                    $query->where('payment_for', $request->payment_for);
                }

                // ✅ Comprehensive search filter - search ALL visible columns
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function($q) use ($search) {
                        // 1. Search by Tenant Name (column: Tenant)
                        $q->whereHas('tenant', function($tenantQuery) use ($search) {
                            $tenantQuery->where('email', 'like', "%{$search}%")
                                    ->orWhere('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });

                        // 2. Search by Amount (column: Amount)
                        if (is_numeric($search)) {
                            $q->orWhere('pay_amount', 'like', "%{$search}%");
                        }

                        // 3. Search by Date (column: Date)
                        try {
                            $dateFormats = ['Y-m-d', 'm/d/Y', 'M d, Y', 'F d, Y', 'Y', 'M Y', 'F Y'];
                            foreach ($dateFormats as $format) {
                                try {
                                    $parsedDate = \Carbon\Carbon::createFromFormat($format, $search);
                                    $q->orWhereDate('pay_date', $parsedDate->format('Y-m-d'));
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                            $monthNames = ['january', 'february', 'march', 'april', 'may', 'june',
                                        'july', 'august', 'september', 'october', 'november', 'december',
                                        'jan', 'feb', 'mar', 'apr', 'may', 'jun',
                                        'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
                            if (in_array(strtolower($search), $monthNames)) {
                                $monthNum = array_search(strtolower($search), array_map('strtolower', $monthNames)) % 12 + 1;
                                $q->orWhereMonth('pay_date', $monthNum);
                            }
                            if (strlen($search) == 4 && is_numeric($search)) {
                                $q->orWhereYear('pay_date', $search);
                            }
                        } catch (\Exception $e) {
                            // Continue with other searches
                        }

                        // 4. Search by Purpose/Payment Type (column: Purpose)
                        $q->orWhere('payment_for', 'like', "%{$search}%");

                        // 5. Search by Status (column: Status)
                        $q->orWhere('pay_status', 'like', "%{$search}%");

                        // Additional fields
                        $q->orWhere('pay_method', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('account_no', 'like', "%{$search}%");

                        // Search by room number
                        $q->orWhereHas('lease', function($leaseQuery) use ($search) {
                            $leaseQuery->where('room_no', 'like', "%{$search}%")
                                    ->orWhereHas('unit', function($unitQuery) use ($search) {
                                        $unitQuery->where('type', 'like', "%{$search}%")
                                                ->orWhere('room_no', 'like', "%{$search}%");
                                    });
                        });
                    });
                }

                $payments = $query->orderBy('pay_date', 'desc')->get();

                $pdf = Pdf::loadView('reports.pdf.payment-history', [
                            'payments'       => $payments,
                            'dateRangeLabel' => $dateRangeLabel ?? null,
                        ])
                        ->setPaper('a4', 'landscape');

                return $pdf->download("payment-history-" . now()->format('Y-m-d_H-i-s') . ".pdf");

            // ---------------- ACTIVE TENANTS ----------------
            case 'active-tenants':
                $pdfTitle = "active";
                $title = "Active Tenants";
                $query = User::with(['tenantApplication', 'leases' => function($q) {
                        $q->where('lea_status', 'active')->latest('created_at');
                    }])->where('role', 'tenant')->where('status', 'approved');

                    if ($request->filled('search')) {
                        $search = $request->search;
                        $searchLower = strtolower(str_replace([' ', '-'], '', $search));

                        if ($searchLower === 'inactive') {
                            // Get users with terminated or no active leases
                            $pdfTitle = "inactive";
                            $title = "Inactive Tenants";
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

                // ✅ Order alphabetically by full name
                $approvedTenants = $query->orderByRaw("CONCAT(first_name, ' ', last_name) ASC")->get();

                try {
                    $pdf = Pdf::loadView('reports.pdf.active-tenants', compact('approvedTenants', 'title'))
                            ->setPaper('a4', 'landscape');

                    return $pdf->download($pdfTitle."-tenants-" . now()->format('Y-m-d_H-i-s') . ".pdf");
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }


            // ---------------- LEASE SUMMARY ----------------
            case 'lease-summary':
                $query = \App\Models\Lease::with(['user','user.tenantApplication', 'unit'])
                        ->whereHas('user', function($q) {
                            $q->where('role', 'tenant')
                            ->where('status', 'approved');
                        })
                        ->latest('created_at');

                // ✅ Comprehensive search filter
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        // 1. Search by Tenant Name
                        $q->whereHas('user', function($userQ) use ($search) {
                            $userQ->where('email', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                        });

                        // 2. Search by Unit Type (from tenant application)
                        $q->orWhereHas('user.tenantApplication', function($appQ) use ($search) {
                            $appQ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('unit_type', 'like', "%{$search}%");
                        });

                        // 3. Search by Lease fields
                        $q->orWhere('lea_status', 'like', "%{$search}%")
                        ->orWhere('room_no', 'like', "%{$search}%")
                        ->orWhere('lea_terms', 'like', "%{$search}%");

                        // 4. Search by dates
                        try {
                            $dateFormats = ['Y-m-d', 'm/d/Y', 'M d, Y', 'F d, Y', 'Y', 'M Y', 'F Y'];
                            foreach ($dateFormats as $format) {
                                try {
                                    $parsedDate = \Carbon\Carbon::createFromFormat($format, $search);
                                    $q->orWhereDate('lea_start_date', $parsedDate->format('Y-m-d'))
                                    ->orWhereDate('lea_end_date', $parsedDate->format('Y-m-d'));
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }

                            $monthMap = [
                                'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2,
                                'march' => 3, 'mar' => 3, 'april' => 4, 'apr' => 4,
                                'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
                                'august' => 8, 'aug' => 8, 'september' => 9, 'sep' => 9,
                                'october' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11,
                                'december' => 12, 'dec' => 12,
                            ];
                            $searchLower = strtolower($search);
                            if (isset($monthMap[$searchLower])) {
                                $q->orWhereMonth('lea_start_date', $monthMap[$searchLower])
                                ->orWhereMonth('lea_end_date', $monthMap[$searchLower]);
                            }

                            if (strlen($search) == 4 && is_numeric($search)) {
                                $q->orWhereYear('lea_start_date', $search)
                                ->orWhereYear('lea_end_date', $search);
                            }
                        } catch (\Exception $e) {
                            // Continue
                        }

                        // 5. Search by Unit
                        $q->orWhereHas('unit', function($unitQuery) use ($search) {
                            $unitQuery->where('type', 'like', "%{$search}%")
                                    ->orWhere('room_no', 'like', "%{$search}%");
                        });
                    });
                }

                $data = $query->get();
                $total = $data->count();

                $pdf = Pdf::loadView('reports.pdf.lease-summary', compact('data','total'))
                        ->setPaper('a4', 'landscape');

                return $pdf->download("lease-summary-" . now()->format('Y-m-d_H-i-s') . ".pdf");

            // ---------------- MAINTENANCE REQUESTS ----------------
            case 'maintenance-requests':
                $requests = MaintenanceRequest::with(['tenant', 'tenant.leases', 'unit'])
                    ->when($request->filled('status'),  fn($q) => $q->where('status',  $request->status))
                    ->when($request->filled('urgency'), fn($q) => $q->where('urgency', $request->urgency))
                    ->when($request->filled('search'), function ($q) use ($request) {
                        $search = $request->search;

                        $q->where(function($q) use ($search) {

                            // 1. Direct columns
                            $q->where('description', 'like', "%{$search}%")
                            ->orWhere('status',     'like', "%{$search}%")
                            ->orWhere('urgency',    'like', "%{$search}%")
                            ->orWhere('room_no',    'like', "%{$search}%")
                            ->orWhere('unit_type',  'like', "%{$search}%");

                            // 2. Scheduled date
                            $q->orWhereRaw("DATE_FORMAT(supposed_date, '%M %d, %Y') LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("DATE_FORMAT(supposed_date, '%b %d, %Y') LIKE ?",  ["%{$search}%"])
                            ->orWhereRaw("DATE_FORMAT(supposed_date, '%M') LIKE ?",          ["%{$search}%"])
                            ->orWhereRaw("DATE_FORMAT(supposed_date, '%Y') LIKE ?",          ["%{$search}%"]);

                            // 3. Tenant name
                            $q->orWhereHas('tenant', function($t) use ($search) {
                                $t->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name',  'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                            });

                            // 4. Unit
                            $q->orWhereHas('unit', function($u) use ($search) {
                                $u->where('type',     'like', "%{$search}%")
                                ->orWhere('room_no', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->latest()
                    ->get();

                $pdf = Pdf::loadView('reports.pdf.maintenance-requests', compact('requests'))
                        ->setPaper('a4', 'landscape');

                return $pdf->download("maintenance-requests-" . now()->format('Y-m-d_H-i-s') . ".pdf");

            // ---------------- RENEWAL HISTORY ----------------
            case 'renewal-history':
                $renewals = $this->renewalHistoryQuery($request)->get();

                $pdf = Pdf::loadView('reports.pdf.renewal-history', [
                    'renewals' => $renewals,
                    'search'   => $request->search,
                ])->setPaper('a4', 'landscape');

                return $pdf->download("renewal-history-" . now()->format('Y-m-d_H-i-s') . ".pdf");

            // ---------------- RENOVATION REQUESTS ----------------
            case 'renovation-requests':
                $requests = $this->renovationRequestsQuery($request)->get();

                $pdf = Pdf::loadView('reports.pdf.renovation-requests', [
                    'requests' => $requests,
                    'search'   => $request->search,
                ])->setPaper('a4', 'landscape');

                return $pdf->download("renovation-requests-" . now()->format('Y-m-d_H-i-s') . ".pdf");

            // ---------------- TENANT FEEDBACK ----------------
            case 'feedback':
                $feedback = $this->feedbackQuery($request)->get();

                $pdf = Pdf::loadView('reports.pdf.feedback', [
                    'feedback' => $feedback,
                    'search'   => $request->search,
                ])->setPaper('a4', 'landscape');

                return $pdf->download("feedback-" . now()->format('Y-m-d_H-i-s') . ".pdf");

            default:
                abort(404, 'Export not available for this report.');
        }
    }

    /* ==================================================================
     |  SHARED QUERY BUILDERS (new in v14)
     |
     |  The four original reports each have their filter logic written out
     |  three times — on screen, in the preview and in the download — and the
     |  copies have already drifted (see docs/PROJECT-MAP.md section 3).
     |
     |  These two reports do not repeat that. Both PDF entry points call the
     |  same builder, so a filter fixed once is fixed in both.
     * ================================================================== */

    /** Renewal history, filtered the same way for preview and download. */
    private function renewalHistoryQuery(Request $request)
    {
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

                $q->orWhereHas('tenant', function ($t) use ($search) {
                    $t->where('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                });

                $numeric = preg_replace('/[^0-9.]/', '', $search);
                if ($numeric !== '' && is_numeric($numeric)) {
                    $q->orWhere('previous_rent', 'like', "%{$numeric}%")
                      ->orWhere('new_rent', 'like', "%{$numeric}%");
                }

                // whereMonth/whereYear rather than raw DATE_FORMAT(), so this
                // report also works on SQLite.
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
        }

        return $query->orderBy('created_at', 'desc');
    }

    /** Renovation requests, filtered the same way for preview and download. */
    private function renovationRequestsQuery(Request $request)
    {
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
                  ->orWhere('title', 'like', "%{$search}%");   // older requests

                $q->orWhereHas('tenant', function ($t) use ($search) {
                    $t->where('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                });
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    /** Tenant feedback, filtered the same way for preview and download. */
    private function feedbackQuery(Request $request)
    {
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
        }

        return $query->orderBy('created_at', 'desc');
    }
}
