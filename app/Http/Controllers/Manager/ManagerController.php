<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Mail\TenantApprovedMail;
use App\Mail\TenantPaymentReminderMail;
use App\Mail\TenantRejectedMail;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\TenantApplication;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{
    protected SmsService $smsService;
    protected BillingService $billingService;

    public function __construct()
    {
        $this->smsService = new SmsService();
        $this->billingService = new BillingService();
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
     * Show all tenants grouped by status
     */

   public function notifyTenant($id)
    {
        $tenant = User::findOrFail($id);

        if ($tenant->status !== 'approved') {
            return redirect()->back()->with('error', 'Only approved tenants can be notified.');
        }

        try {
            Mail::to($tenant->email)->send(new TenantPaymentReminderMail($tenant));
        } catch (\Exception $e) {
            Log::error("❌ Failed to send payment reminder email to {$tenant->email}: {$e->getMessage()}");
            return redirect()->back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Payment reminder sent to ' . $tenant->name);
    }


    public function exportTenantsPdf(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $query = User::with(['tenantApplication', 'leases'])
            ->where('role', 'tenant');

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        $tenants = $query->get();

        $pdf = Pdf::loadView('reports.pdf.tenants-filtered', [
            'tenants' => $tenants,
            'filter' => $filter,
            'generatedAt' => now()->format('Y-m-d H:i:s')
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("tenant-report-{$filter}.pdf");
    }

    public function tenants(Request $request)
    {
        // Capture filter and search (default: all for filter, empty for search)
        $filter = $request->query('filter', 'all');
        $search = $request->query('search', '');

        // Base query for tenants - load leases with utility_balance
        // Load ALL leases regardless of status (including terminated, rejected, etc.)
        // Also load tenantApplication with unit relationship to show application info when no leases exist
        $query = User::with([
                'tenantApplication.unit',
                'leases' => function($q) {
                    $q->with('unit'); // Remove status filter to show all leases
                },
                'utilityProofs',
                // renovationRequests is no longer eager-loaded here: the
                // records moved to Reports > Renovation Requests, so this page
                // was paying for a nested load it never rendered.
            ])
            ->where('role', 'tenant');

        // Apply status filter if specified
        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        // ✅ Apply search filter (by full_name in tenant_applications)
        if (!empty($search)) {
            $query->whereHas('tenantApplication', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        // Group by status for easy section display (these can stay as-is, but we'll use $filteredTenants for display)
        $pendingTenants = User::where('role', 'tenant')->where('status', 'pending')->get();
        $approvedTenantList = User::where('role', 'tenant')->where('status', 'approved')->get();
        $rejectedTenantList = User::where('role', 'tenant')->where('status', 'rejected')->get();

        // Filtered results for card display (now includes search)
        // $filteredTenants = $query->orderByRaw("CONCAT(first_name, ' ', last_name) ASC")->get();
        $filteredTenants = $query->orderBy("created_at", 'desc')->get();

        return view('manager.tenants', compact(
            'pendingTenants',
            'approvedTenantList',
            'rejectedTenantList',
            'filteredTenants',
            'filter',
            'search'  // Pass search to the view for form pre-fill
        ));
    }

    public function approve($id)
    {
        // Serialise repeat clicks on Accept so only one lease row is ever written.
        $lock = Cache::lock('tenant-approve:' . $id, 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'This application is already being processed.');
        }

        try {
            return $this->performApprove($id);
        } catch (\Throwable $e) {
            Log::error('Tenant approval failed for user ' . $id . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while approving this tenant. Please try again.');
        } finally {
            $lock->release();
        }
    }

    protected function performApprove($id)
    {
        $user = User::find($id);

        if (!$user || $user->role !== 'tenant') {
            return redirect()->back()->with('error', 'Invalid tenant.');
        }

        $tenantApp = $user->tenantApplication;

        if (!$tenantApp) {
            return redirect()->back()->with('error', 'Tenant application not found.');
        }

        // Get unit early (needed for rejection logic)
        $unit = Unit::find($tenantApp->unit_id);
        if (!$unit) {
            return redirect()->back()->with('error', 'Selected unit not found.');
        }

        // Idempotency guard: if this tenant is already approved and already has a
        // live lease on this unit, a second Accept must not duplicate anything.
        $existingLease = Lease::where('user_id', $user->id)
            ->where('unit_id', $unit->id)
            ->whereIn('lea_status', ['active', 'Active', 'approved'])
            ->first();

        if ($user->status === 'approved' && $existingLease) {
            return redirect()->back()->with('info', 'This tenant has already been approved for room ' . $unit->room_no . '.');
        }

        $isBedSpacer = $unit->type === 'Bed-Spacer';

        // ------------------------------------------------------------------
        // Capacity guard.
        //
        // A bed spacer holds `capacity` people. Approving past that used to be
        // possible and silently pushed no_of_occupants over the limit, which
        // then made the vacancy maths wrong everywhere else.
        // ------------------------------------------------------------------
        if ($isBedSpacer) {
            $capacity  = (int) ($unit->capacity ?? 0);
            $occupants = (int) ($unit->no_of_occupants ?? 0);

            if ($capacity > 0 && $occupants >= $capacity) {
                return redirect()->back()->with('error',
                    "Room {$unit->room_no} is full ({$occupants}/{$capacity} beds taken). " .
                    'You cannot approve another applicant for it.');
            }

            // Two people cannot hold the same bed. This is separate from the
            // capacity check: a room can have a free bed AND a clash, if both
            // applicants asked for bed 2 while bed 3 sits empty.
            if ($tenantApp->bed_number) {
                $bedTaken = Lease::where('unit_id', $unit->id)
                    ->where('bed_number', $tenantApp->bed_number)
                    ->whereIn('lea_status', ['active', 'Active', 'approved', 'pending'])
                    ->exists();

                if ($bedTaken) {
                    return redirect()->back()->with('error',
                        "Bed {$tenantApp->bed_number} in room {$unit->room_no} is already assigned. " .
                        'Reassign this applicant to a free bed before approving.');
                }
            }
        } elseif (strtolower((string) $unit->status) === 'occupied') {
            return redirect()->back()->with('error',
                "Room {$unit->room_no} is already occupied.");
        }

        // Approve tenant
        $user->status = 'approved';
        $user->rejection_reason = null;
        $user->save();

        // ------------------------------------------------------------------
        // Auto-reject other pending applicants — but only the ones this
        // approval actually shuts out.
        //
        // The old code rejected EVERY other pending applicant for the unit.
        // For a whole room that is right: one room, one tenant. For a bed
        // spacer it was wrong and was throwing away applicants for beds that
        // were still empty.
        //
        // So the rule is now:
        //   whole unit   → everyone else is shut out, reject them all
        //   bed spacer   → reject only the applicants for the SAME bed, plus,
        //                  if this approval fills the last bed, everyone left
        // ------------------------------------------------------------------
        $occupantsAfter = $isBedSpacer
            ? (int) ($unit->no_of_occupants ?? 0) + 1
            : 1;

        $capacityAfter = $isBedSpacer ? (int) ($unit->capacity ?? 0) : 1;
        $unitNowFull   = $capacityAfter > 0 && $occupantsAfter >= $capacityAfter;

        $othersQuery = TenantApplication::where('unit_id', $tenantApp->unit_id)
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', function($query) {
                $query->where('status', 'pending');
            })
            ->with('user');

        // Beds remain, so only the people who wanted THIS bed are blocked.
        if ($isBedSpacer && ! $unitNowFull) {
            if ($tenantApp->bed_number) {
                $othersQuery->where('bed_number', $tenantApp->bed_number);
            } else {
                // No specific bed was requested, so this approval doesn't
                // conflict with anyone in particular. Leave them all pending.
                $othersQuery->whereRaw('1 = 0');
            }
        }

        $otherPendingApplications = $othersQuery->get();

        foreach ($otherPendingApplications as $otherApp) {
            $otherUser = $otherApp->user;

            $sameBed = $isBedSpacer
                && $tenantApp->bed_number
                && $otherApp->bed_number == $tenantApp->bed_number;

            $reason = $sameBed
                ? "Bed {$tenantApp->bed_number} in room {$unit->room_no} has been assigned to another applicant."
                : "Unit is already taken. Another application for this unit has been approved.";

            // Reject the other tenant
            $otherUser->status = 'rejected';
            $otherUser->rejection_reason = $reason;
            $otherUser->save();

            // Send rejection SMS
            $contactNumber = $this->normalizePhoneNumber($otherApp->contact_number ?? null);
            if ($contactNumber) {
                $rejectionMessage = "TenantMS\nHello {$otherUser->name}, your application for unit {$unit->room_no} has been REJECTED. "
                    . $reason;
                $this->smsService->send($contactNumber, $rejectionMessage);
            }

            // Create notification
            Notification::create([
                'user_id' => $otherUser->id,
                'title'   => 'Application Rejected',
                'message' => "Your application for unit {$unit->room_no} has been rejected. " . $reason,
            ]);

            // Send rejection email
            try {
                Mail::to($otherUser->email)->send(new TenantRejectedMail($otherUser));
            } catch (\Exception $e) {
                Log::error("❌ Failed to send tenant rejection email to {$otherUser->email}: {$e->getMessage()}");
            }
        }

        // Lease period
        $startDate = Carbon::today();
        $endDate   = $startDate->copy()->addYear();

        $leaseTermText = sprintf(
            '1 Year Lease (%s – %s)',
            $startDate->format('M d, Y'),
            $endDate->format('M d, Y')
        );

        // Bed-Spacer logic
        if ($isBedSpacer) {
            $unit->no_of_occupants = ($unit->no_of_occupants ?? 0) + 1;

            // Only mark as 'occupied' if full
            if ($unit->no_of_occupants >= $unit->capacity) {
                $unit->status = 'occupied';
            }
        } else {
            // Other unit types are fully occupied
            $unit->status = 'occupied';
        }
        $unit->save();

        // ------------------------------------------------------------------
        // Rent and deposit.
        //
        // This used to be a match() on the applicant's chosen unit_type that
        // listed Studio / 1-Bedroom / 2-Bedroom / Bed-Spacer and fell through
        // to `default => 0`. Commercial hit that default, so a commercial
        // lease was created with zero rent and zero deposit — which is why the
        // manager could not record a deposit or rent payment against it: the
        // Record Payment dropdown only lists tenants who owe something, and
        // that lease owed nothing.
        //
        // Every branch of the match returned the same thing anyway. The unit's
        // own price is the authoritative figure, it works for every type
        // including any added later, and it is what approveAdditionalUnit has
        // always used.
        // ------------------------------------------------------------------
        $monthlyRent      = (float) ($unit->room_price ?? 0);
        $depositAmount    = round($monthlyRent * 2, 2);
        $monthlyUtilities = 0;

        if ($monthlyRent <= 0) {
            Log::warning("Unit {$unit->id} (room {$unit->room_no}, type {$unit->type}) has no room_price. " .
                         "Lease for user {$user->id} created with zero rent and deposit.");
        }

        // Create Lease
        $lease = Lease::create([
            'user_id'        => $user->id,
            'unit_id'        => $unit->id,
            'lea_start_date' => $startDate,
            'lea_end_date'   => $endDate,
            'lea_status'     => 'active',
            'room_no'        => $unit->room_no,
            'lea_terms'      => $leaseTermText,
            'bed_number'     => $tenantApp->bed_number ?? null,
            'deposit_balance' => $depositAmount,
            // Left at zero deliberately. openRentSchedule() below raises the
            // first month as a proper charge and puts it on the balance —
            // setting it here as well would bill the tenant twice on day one.
            'rent_balance' => 0,
            'monthly_rent' => $monthlyRent,
            'renewal_count' => 0,
            'due_date'      => \Carbon\Carbon::parse($startDate)->addMonth()
        ]);

        // Put the lease on the monthly rent schedule and give the first month
        // a charge row, so it looks like every month after it instead of a
        // bare figure on rent_balance with nothing behind it. rent_balance was
        // already set to one month above, so the charge is recorded without
        // adding to the balance a second time.
        $lease->refresh();
        $this->billingService->openRentSchedule($lease, \Carbon\Carbon::parse($startDate), auth()->user());

        // Update tenant financial info
        $user->update([
            'rent_amount'     => $monthlyRent,
            'utility_amount'  => $monthlyUtilities,
            'deposit_amount'  => $depositAmount,
            'rent_balance'    => $monthlyRent,
            'utility_balance' => $monthlyUtilities,
        ]);

        // Notification
        Notification::create([
            'user_id' => $user->id,
            'title'   => 'Application Approved',
            'message' => 'Congratulations! Your tenant application has been approved.',
        ]);

        Notification::create([
            'user_id' => auth()->user()->id,
            'title'   => 'Approved Application',
            'message' => $user->name . " application for unit {$unit->room_no} has been approved.",
        ]);

        // Send Approval Email
        try {
            Mail::to($user->email)->send(new TenantApprovedMail($user, $lease));
        } catch (\Exception $e) {
            Log::error("❌ Failed to send tenant approval email to {$user->email}: {$e->getMessage()}");
        }

        // Send Approval SMS
        $contactNumber = $this->normalizePhoneNumber($tenantApp->contact_number);

        if ($contactNumber) {
            $approvalMessage = "Hello {$user->name}, your application has been APPROVED! "
                . "Your lease for room {$unit->room_no} starts {$startDate->format('M d, Y')}.";
            $this->smsService->send($contactNumber, $approvalMessage);
        }

        return redirect()->back()->with('success', 'Tenant approved successfully. Lease and financial info initialized, and unit occupancy updated.');
    }


    // With reject this time
    public function reject(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|exists:users,id',
            'rejection_reason' => 'required|string|max:255',
        ]);

        $tenant = User::find($request->tenant_id);

        if (!$tenant || $tenant->role !== 'tenant') {
            return redirect()->back()->with('error', 'Invalid tenant.');
        }

        if ($tenant->status === 'rejected') {
            return redirect()->back()->with('info', 'This tenant has already been rejected.');
        }

        $tenantApp = $tenant->tenantApplication;

        $tenant->status = 'rejected';
        $tenant->rejection_reason = $request->rejection_reason;
        $tenant->save();

        $rejectedRoom = $tenantApp->room_no ?? ($tenantApp->unit->room_no ?? 'N/A');

        Notification::create([
            'user_id' => auth()->user()->id,
            'title'   => 'Rejected Application',
            'message' => $tenant->name . " application for unit {$rejectedRoom} has been rejected.",
        ]);

        Notification::create([
            'user_id' => $tenant->id,
            'title'   => 'Application Rejected',
            'message' => "Your application for unit {$rejectedRoom} has been rejected. Reason: {$request->rejection_reason}",
        ]);

        // ✅ Send Rejection Email
        try {
            Mail::to($tenant->email)->send(new TenantRejectedMail($tenant));
        } catch (\Exception $e) {
            Log::error("❌ Failed to send tenant rejection email to {$tenant->email}: {$e->getMessage()}");
        }

        // ✅ Send Rejection SMS
        $tenantApp = $tenant->tenantApplication;
        $contactNumber = $this->normalizePhoneNumber($tenantApp->contact_number ?? null);

        if ($contactNumber) {
            $rejectionMessage = "TenantMS\n,Hello {$tenant->name}, your application has been REJECTED. "
                . "Reason: {$tenant->rejection_reason}";
            $this->smsService->send($contactNumber, $rejectionMessage);
        }

        return redirect()->back()->with('warning', 'Tenant rejected successfully.');
    }

    public function viewIds($id)
    {
        $tenant = User::with('tenantApplication.unit')->findOrFail($id);

        if (!$tenant->tenantApplication) {
            abort(404, 'Tenant has no application data.');
        }

        $tenantApp = $tenant->tenantApplication;

        $pdf = Pdf::loadView('reports.pdf.tenant-full-info', [
            'tenant' => $tenant,
            'tenantApp' => $tenantApp,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("tenant-info-{$tenant->id}.pdf");
    }

    public function forceMoveOut(Request $request, $tenantId)
    {
        $request->validate([
            'lease_id' => 'required|exists:leases,id',
            'reason' => 'required|string|min:10',
            'deposit_amount' => 'required|numeric|min:0',
            'damage_cost' => 'required|numeric|min:0',
            'refund_amount' => 'required|numeric|min:0',
            'damages_json' => 'required|json',
            'deposit_paid' => 'required|in:true,false',
        ]);

        try {
            $tenant = User::findOrFail($tenantId);
            $lease = Lease::with('unit')->findOrFail($request->lease_id);
            $isDepositPaid = $request->deposit_paid === 'true';

            // Verify the lease belongs to this tenant
            if ($lease->user_id !== $tenant->id) {
                return back()->with('error', 'Invalid lease selection.');
            }

            // Check if lease is eligible for force move out
            if (!in_array($lease->lea_status, ['active', 'approved', 'pending'])) {
                return back()->with('info', 'This lease is already inactive.');
            }

            // v19: an estimate is not payable, so it is not in the settlement.
            // Moving out with one open would leave it behind unpaid forever.
            if ($blocked = $this->unfinalisedEstimateMessage($lease)) {
                return back()->with('error', $blocked);
            }

            // --------------------------------------------------------------
            // Unpaid balance carried into the settlement.
            //
            // The old code set rent_balance and penalty_fee to 0 without
            // collecting them, so everything the tenant still owed simply
            // disappeared at move-out. It is now itemised alongside the
            // damages and deducted from the refund.
            //
            // Computed HERE, from the lease, never from the form. The modal
            // posts a figure for display, but a stale tab or an edited field
            // must not be able to change what the tenant is charged.
            //
            // The deposit balance is deliberately NOT included: an unpaid
            // deposit is not money owed out of the refund, it means there is
            // no refund to take it from.
            // --------------------------------------------------------------
            //
            // Advance rent is settled BEFORE the arrears are read. A tenant
            // who paid three months forward and is being moved out is not in
            // arrears for a month their own money already covers — charging
            // them for it and then handing the same money back as a refund
            // nets out, but reads as a debt they never had.
            //
            // Read the settlement first, then apply it. Same numbers either
            // way, but this gives us `advance_used` for the notification —
            // "we spent ₱5,000 of your advance on the month you owed" is the
            // difference between an explained figure and a mystery one. It is
            // also exactly what the modal previewed, so the manager and the
            // tenant are looking at the same settlement.
            $settlement = $lease->settlementPreview();

            $lease->consumeAdvanceRent();
            $lease->save();

            $unpaidRent      = $settlement['unpaid_rent'];
            $unpaidUtilities = $settlement['unpaid_utilities'];
            $penalty         = $settlement['unpaid_penalty'];
            $unpaidBalance   = $settlement['unpaid_total'];

            // Whatever advance survives that is the tenant's money, held for
            // months they will not be living here. It is returned, not kept.
            $advanceUsed   = $settlement['advance_used'];
            $advanceRefund = $settlement['advance_refund'];

            // Damages as entered by the manager, then the arrears lines
            // appended so the stored breakdown shows exactly what was taken
            // and why.
            $damages = json_decode($request->damages_json, true);
            $damages = is_array($damages) ? $damages : [];

            if ($unpaidRent > 0) {
                $damages[] = ['item' => 'Unpaid rent', 'cost' => $unpaidRent];
            }
            if ($unpaidUtilities > 0) {
                $damages[] = ['item' => 'Unpaid utility bills', 'cost' => $unpaidUtilities];
            }
            if ($penalty > 0) {
                $damages[] = ['item' => 'Late payment penalty', 'cost' => $penalty];
            }

            $damageCost   = round((float) $request->damage_cost, 2);
            $totalDeducted = round($damageCost + $unpaidBalance, 2);

            // Recompute the refund rather than trusting the posted one, for
            // the same reason as above. No deposit paid means no refund and
            // nothing to deduct from — the arrears remain owed and are what
            // the collections paper trail is for.
            $depositPaidAmount = $isDepositPaid ? round((float) $request->deposit_amount, 2) : 0.0;
            $refundAmount      = $isDepositPaid ? max(0, round($depositPaidAmount - $totalDeducted, 2)) : 0.0;

            // Unused advance rent is returned regardless of the deposit
            // position. It is not security against damage — it is rent for
            // months that are not going to happen.
            if ($advanceRefund >= 0.01) {
                $damages[]     = ['item' => 'Unused advance rent (returned)', 'cost' => -$advanceRefund];
                $refundAmount  = round($refundAmount + $advanceRefund, 2);
            }

            // What the deposit could not cover. Still owed after move-out.
            $shortfall = $isDepositPaid
                ? max(0, round($totalDeducted - $depositPaidAmount, 2))
                : $unpaidBalance;

            // Update lease status
            $lease->update([
                'lea_status'         => 'terminated',
                'lea_end_date'       => now(),
                'move_out_reason'    => $request->reason,
                'move_out_requested' => false,
                'damage_cost'        => $totalDeducted,
                'refund_amount'      => $refundAmount,
                'damage_details'     => json_encode($damages),
                'rent_balance'       => 0,
                'penalty_fee'        => 0,
                // Returned above via $refundAmount, so it must not stay on the
                // lease as well or it would be owed to the tenant twice.
                'advance_rent_balance' => 0,
                'rent_paid_through'    => null,
            ]);

            // rent_balance/penalty_fee are zeroed above, but the unpaid
            // utility total sits open on the individual bill rows until they
            // are closed the same way a real payment would. Only when the
            // deposit was actually paid, though — with no deposit there is
            // nothing to deduct the balance from, and it stays owed exactly
            // as the notice above says.
            if ($isDepositPaid) {
                $this->billingService->settleOpenBillsAtMoveOut($lease);
            }

            // Update unit availability
            if ($lease->unit) {
                if ($lease->unit->type === 'Bed-Spacer') {
                    $current = max(0, ($lease->unit->no_of_occupants ?? 0) - 1);
                    $lease->unit->no_of_occupants = $current;
                    if ($current < $lease->unit->capacity) {
                        $lease->unit->status = 'vacant';
                    }
                } else {
                    $lease->unit->status = 'vacant';
                }
                $lease->unit->save();
            }

            // Check if tenant has any other active leases
            $hasActiveLeases = $tenant->leases()
                ->whereIn('lea_status', ['active', 'approved', 'pending'])
                ->where('id', '!=', $lease->id)
                ->exists();

            // If no other active leases, update tenant status
            if (!$hasActiveLeases) {
                $tenant->update(['status' => 'moved_out']);
            }

            // Build notification message based on deposit status.
            //
            // The advance rent gets its own sentence in both branches. It is
            // the tenant's own money for months they will not be living here,
            // and if it is silently folded into one refund figure the
            // arithmetic does not add up from their side — deposit minus
            // deductions will not equal what lands in their hand.
            $advanceLine = '';

            if ($advanceUsed >= 0.01) {
                $advanceLine .= sprintf(
                    ' ₱%s of your advance rent was applied to what was owed.',
                    number_format($advanceUsed, 2)
                );
            }

            if ($advanceRefund >= 0.01) {
                $advanceLine .= sprintf(
                    ' Unused advance rent of ₱%s is returned to you and is included in the refund above.',
                    number_format($advanceRefund, 2)
                );
            }

            if ($isDepositPaid) {
                $notifMessage = sprintf(
                    'You have been forcibly moved out from room %s. Reason: %s. Deposit ₱%s less damages ₱%s and unpaid balance ₱%s. Refund: ₱%s.',
                    $lease->room_no ?? 'N/A',
                    $request->reason,
                    number_format($depositPaidAmount, 2),
                    number_format($damageCost, 2),
                    number_format($unpaidBalance, 2),
                    number_format($refundAmount, 2)
                );

                $notifMessage .= $advanceLine;

                if ($shortfall > 0) {
                    $notifMessage .= sprintf(
                        ' Your deposit did not cover the full amount — ₱%s remains outstanding. Please contact the office.',
                        number_format($shortfall, 2)
                    );
                }
            } else {
                // An unpaid deposit means no deposit refund. It does NOT mean
                // no refund: unused advance rent is not security against
                // damage, it is rent for months that will not happen, and it
                // comes back either way. The old message said "no refund"
                // flatly and would have contradicted the money actually paid
                // out.
                $notifMessage = sprintf(
                    'You have been forcibly moved out from room %s. Reason: %s. No deposit refund — deposit was not paid.',
                    $lease->room_no ?? 'N/A',
                    $request->reason
                );

                if ($advanceRefund >= 0.01) {
                    $notifMessage .= sprintf(
                        ' Your unused advance rent of ₱%s is still returned to you. Refund: ₱%s.',
                        number_format($advanceRefund, 2),
                        number_format($refundAmount, 2)
                    );
                }

                if ($advanceUsed >= 0.01) {
                    $notifMessage .= sprintf(
                        ' ₱%s of your advance rent was applied to what was owed.',
                        number_format($advanceUsed, 2)
                    );
                }

                if ($unpaidBalance > 0) {
                    $notifMessage .= sprintf(
                        ' ₱%s remains outstanding on this room. Please contact the office.',
                        number_format($unpaidBalance, 2)
                    );
                }
            }

            Notification::create([
                'user_id' => $tenant->id,
                'title' => 'Forced Move Out',
                'message' => $notifMessage,
            ]);

            // Build success message
            $message = sprintf('Tenant %s has been forcibly moved out from %s.', $tenant->name, $lease->room_no);

            if ($isDepositPaid) {
                $message .= sprintf(' Refund amount: ₱%s', number_format($refundAmount, 2));

                $parts = [];
                if ($damageCost > 0)    { $parts[] = 'damages ₱' . number_format($damageCost, 2); }
                if ($unpaidBalance > 0) { $parts[] = 'unpaid balance ₱' . number_format($unpaidBalance, 2); }
                if ($parts) {
                    $message .= ' (deducted: ' . implode(', ', $parts) . ')';
                }
            } elseif ($advanceRefund >= 0.01) {
                $message .= sprintf(
                    ' Deposit was unpaid, so no deposit refund — but ₱%s of unused advance rent is returned. Refund amount: ₱%s',
                    number_format($advanceRefund, 2),
                    number_format($refundAmount, 2)
                );
            } else {
                $message .= ' No refund processed (deposit was unpaid).';
            }

            if ($advanceRefund >= 0.01 && $isDepositPaid) {
                $message .= sprintf(' (includes ₱%s unused advance rent returned)', number_format($advanceRefund, 2));
            }

            if ($advanceUsed >= 0.01) {
                $message .= sprintf(' ₱%s of advance rent was applied to arrears first.', number_format($advanceUsed, 2));
            }

            if ($shortfall > 0) {
                $message .= sprintf(' ₱%s still uncollected — record it in Collections if you intend to pursue it.',
                    number_format($shortfall, 2));
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function moveOut(Request $request, $leaseId)
    {
        $request->validate([
            'deposit_amount' => 'required|numeric|min:0',
            'damage_cost'    => 'required|numeric|min:0',
            'refund_amount'  => 'required|numeric|min:0',
            'damages_json'   => 'required|json',
        ]);

        // Serialise repeat clicks on "Approve & Refund".
        $lock = Cache::lock('lease-move-out:' . $leaseId, 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'This move out request is already being processed.');
        }

        try {
            $result = DB::transaction(function () use ($request, $leaseId) {

                $lease = Lease::with('unit', 'tenant')->lockForUpdate()->find($leaseId);

                if (! $lease) {
                    return ['type' => 'error', 'message' => 'Lease not found.'];
                }

                $tenant = $lease->tenant;

                if (! $tenant) {
                    return ['type' => 'error', 'message' => 'The tenant for this lease no longer exists.'];
                }

                // Idempotency guard: a lease that is already terminated must not be
                // terminated again, which is what produced duplicate notifications
                // and double-decremented the unit occupancy.
                if (!in_array(strtolower((string) $lease->lea_status), ['active', 'pending'], true)) {
                    return ['type' => 'info', 'message' => 'This lease is already inactive.'];
                }

                // v19: see the same check in @forceMoveOut.
                if ($blocked = $this->unfinalisedEstimateMessage($lease)) {
                    return ['type' => 'error', 'message' => $blocked];
                }

                // --------------------------------------------------------------
                // Unpaid balance carried into the settlement, computed HERE from
                // the lease rather than trusted from the form — same reasoning
                // as ManagerController@forceMoveOut: a stale tab or an edited
                // hidden field must not be able to change what the tenant is
                // charged on their way out.
                // --------------------------------------------------------------
                // Advance rent settled first, then whatever is left of it is
                // the tenant's to take with them — see the same block in
                // @forceMoveOut for the reasoning.
                $settlement = $lease->settlementPreview();

                $lease->consumeAdvanceRent();
                $lease->save();

                $unpaidRent      = $settlement['unpaid_rent'];
                $unpaidUtilities = $settlement['unpaid_utilities'];
                $penalty         = $settlement['unpaid_penalty'];
                $unpaidBalance   = $settlement['unpaid_total'];

                $advanceUsed   = $settlement['advance_used'];
                $advanceRefund = $settlement['advance_refund'];

                $damages = json_decode($request->damages_json, true);
                $damages = is_array($damages) ? $damages : [];

                if ($unpaidRent > 0) {
                    $damages[] = ['item' => 'Unpaid rent', 'cost' => $unpaidRent];
                }
                if ($unpaidUtilities > 0) {
                    $damages[] = ['item' => 'Unpaid utility bills', 'cost' => $unpaidUtilities];
                }
                if ($penalty > 0) {
                    $damages[] = ['item' => 'Late payment penalty', 'cost' => $penalty];
                }

                $damageCost    = round((float) $request->damage_cost, 2);
                $totalDeducted = round($damageCost + $unpaidBalance, 2);

                $depositAmount = round((float) $request->deposit_amount, 2);
                $refundAmount  = max(0, round($depositAmount - $totalDeducted, 2));
                $shortfall     = max(0, round($totalDeducted - $depositAmount, 2));

                if ($advanceRefund >= 0.01) {
                    $damages[]    = ['item' => 'Unused advance rent (returned)', 'cost' => -$advanceRefund];
                    $refundAmount = round($refundAmount + $advanceRefund, 2);
                }

                // Update lease with move out details
                $lease->update([
                    'lea_status'         => 'terminated',
                    'lea_end_date'       => now(),
                    'damage_cost'        => $totalDeducted,
                    'refund_amount'      => $refundAmount,
                    'damage_details'     => json_encode($damages),
                    'move_out_requested' => false,
                    'rent_balance'       => 0,
                    'penalty_fee'        => 0,
                    'advance_rent_balance' => 0,
                    'rent_paid_through'    => null,
                ]);

                // rent_balance/penalty_fee are zeroed above, but the unpaid
                // utility total sits open on the individual bill rows until
                // closed the same way a real payment would. Only when there
                // was a deposit to deduct it from — with none, the balance
                // stays owed rather than being written off.
                if ($depositAmount > 0) {
                    $this->billingService->settleOpenBillsAtMoveOut($lease);
                }

                // Update unit occupancy and status
                if ($lease->unit) {
                    if ($lease->unit->type === 'Bed-Spacer') {
                        $current = max(0, ($lease->unit->no_of_occupants ?? 0) - 1);
                        $lease->unit->no_of_occupants = $current;

                        if ($current < $lease->unit->capacity) {
                            $lease->unit->status = 'vacant';
                        }
                    } else {
                        $lease->unit->status = 'vacant';
                    }
                    $lease->unit->save();
                }

                // If this was the tenant's last live lease, mark them moved out.
                $stillHasLease = Lease::where('user_id', $tenant->id)
                    ->whereIn('lea_status', ['active', 'approved', 'pending'])
                    ->where('id', '!=', $lease->id)
                    ->exists();

                if (! $stillHasLease) {
                    $tenant->update(['status' => 'moved_out']);
                }

                // Notify the tenant.
                //
                // The unused advance is named as its own line rather than
                // being folded into the total. Without it the message reads
                // as broken arithmetic: deposit minus deductions would not
                // equal the refund, and the tenant's first assumption when
                // the numbers do not tie out is that they are being cheated.
                $notifMessage = sprintf(
                    'Your move out request for room %s has been approved. Total refund: PHP %s (Deposit: PHP %s, Damages/Unpaid Bills: PHP %s%s)',
                    $lease->room_no ?? 'N/A',
                    number_format($refundAmount, 2),
                    number_format($depositAmount, 2),
                    number_format($totalDeducted, 2),
                    $advanceRefund >= 0.01
                        ? ', Unused Advance Rent returned: PHP ' . number_format($advanceRefund, 2)
                        : ''
                );

                if ($advanceUsed >= 0.01) {
                    $notifMessage .= sprintf(
                        ' PHP %s of your advance rent was applied to what was owed on this lease.',
                        number_format($advanceUsed, 2)
                    );
                }

                if ($shortfall > 0) {
                    $notifMessage .= sprintf(
                        ' Your deposit did not cover the full amount — PHP %s remains outstanding. Please contact the office.',
                        number_format($shortfall, 2)
                    );
                }

                Notification::create([
                    'user_id' => $tenant->id,
                    'title'   => 'Move Out Approved',
                    'message' => $notifMessage,
                ]);

                $message = sprintf(
                    'Move out approved for %s (Room %s). Refund amount: PHP %s',
                    $tenant->name,
                    $lease->room_no ?? 'N/A',
                    number_format($refundAmount, 2)
                );

                if ($totalDeducted > 0) {
                    $message .= sprintf(' (Damages/Unpaid Bills deducted: PHP %s)', number_format($totalDeducted, 2));
                }

                if ($advanceRefund >= 0.01) {
                    $message .= sprintf(' Includes PHP %s unused advance rent returned.', number_format($advanceRefund, 2));
                }

                if ($advanceUsed >= 0.01) {
                    $message .= sprintf(' PHP %s of advance rent was applied to arrears first.', number_format($advanceUsed, 2));
                }

                if ($shortfall > 0) {
                    $message .= sprintf(' PHP %s still uncollected — record it in Collections if you intend to pursue it.',
                        number_format($shortfall, 2));
                }

                return ['type' => 'success', 'message' => $message];
            });
        } catch (\Throwable $e) {
            Log::error('Move out approval failed for lease ' . $leaseId . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while approving this move out. Please try again.');
        } finally {
            $lock->release();
        }

        return redirect()->back()->with($result['type'], $result['message']);
    }

    public function rejectMoveOut(Request $request, Lease $lease)
    {
        $lease->load('tenant');

        if (!$lease->move_out_requested) {
            return redirect()->back()->with('info', 'No move out request to reject for this lease.');
        }

        // Optional: you can store rejection reason if you want
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $lease->move_out_requested = false;
        $lease->save();

        // Notify tenant
        $message = 'Your move out request for room ' . ($lease->room_no ?? 'N/A') . ' has been rejected.';
        if ($request->reason) {
            $message .= ' Reason: ' . $request->reason;
        }

        if ($lease->tenant) {
            Notification::create([
                'user_id' => $lease->tenant->id,
                'title'   => 'Move Out Rejected',
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('info', 'Move out request rejected.');
    }


    /**
     * Null when the lease is clear to move out, otherwise why it is not:
     * it still has estimated bills with no final amount (v19).
     */
    private function unfinalisedEstimateMessage(Lease $lease): ?string
    {
        $count = $lease->utilityBills()->awaitingFinalAmount()->count();

        if ($count === 0) {
            return null;
        }

        return sprintf(
            'Room %s still has %d estimated bill%s with no final amount. Estimates cannot be paid or '
            . 'deducted, so set the final amount (Utility Bills → open the bill) before moving this tenant out.',
            $lease->room_no ?? 'N/A',
            $count,
            $count === 1 ? '' : 's'
        );
    }

    /**
     * Renew a lease for another year. Rent goes up 5% (Lease::RENT_INCREASE_RATE)
     * on every renewal — see also the scheduled `leases:renew` command, which
     * does the same thing automatically on the lease anniversary.
     */
    public function renewLease(Request $request, $leaseId)
    {
        $lock = Cache::lock('lease-renew:' . $leaseId, 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'This lease is already being renewed.');
        }

        $actorId = auth()->id();

        try {
            $result = DB::transaction(function () use ($leaseId, $actorId) {

                $lease = Lease::with('tenant', 'unit')->lockForUpdate()->find($leaseId);

                if (! $lease) {
                    return ['type' => 'error', 'message' => 'Lease not found.'];
                }

                if (strtolower((string) $lease->lea_status) !== 'active') {
                    return ['type' => 'info', 'message' => 'Only active leases can be renewed.'];
                }

                if ($lease->move_out_requested) {
                    return ['type' => 'info', 'message' => 'This tenant has a pending move out request. Resolve it before renewing.'];
                }

                // v19: renewal starts with the tenant. Locked in the UI too;
                // this is the check that cannot be bypassed with a stale tab.
                if (! $lease->renewal_requested) {
                    return ['type' => 'error', 'message' => 'Room ' . ($lease->room_no ?? 'N/A')
                        . ': the tenant has not requested a renewal yet. Renewal opens once they ask '
                        . 'from their Lease Management page.'];
                }

                // Don't renew the same lease twice in one day.
                if ($lease->last_renewed_at && Carbon::parse($lease->last_renewed_at)->isToday()) {
                    return ['type' => 'info', 'message' => 'This lease has already been renewed today.'];
                }

                $summary = $lease->renewForAnotherYear(
                    null,
                    \App\Models\LeaseRenewal::SOURCE_MANUAL,
                    $actorId
                );

                if (! $summary) {
                    return ['type' => 'error', 'message' => 'This lease could not be renewed.'];
                }

                // Keep the tenant-level rent figures in step with the new rate.
                if ($lease->tenant) {
                    $tenant = $lease->tenant;
                    $tenant->rent_amount  = ($tenant->rent_amount ?? 0) - $summary['old_rent'] + $summary['new_rent'];
                    // Rate only. Renewal no longer bills a month of rent —
                    // `rent:charge` raises it at the new rate when the month
                    // comes round, and doing both would double-bill.
                    $tenant->save();

                    Notification::create([
                        'user_id' => $tenant->id,
                        'title'   => 'Lease Renewed',
                        'message' => sprintf(
                            'Your lease for room %s has been renewed until %s. As per your contract, rent increased by 5%%: PHP %s to PHP %s per month.',
                            $lease->room_no ?? 'N/A',
                            $summary['end_date']->format('M d, Y'),
                            number_format($summary['old_rent'], 2),
                            number_format($summary['new_rent'], 2)
                        ),
                    ]);
                }

                return [
                    'type'    => 'success',
                    'message' => sprintf(
                        'Lease renewed until %s. Rent increased 5%% from PHP %s to PHP %s.',
                        $summary['end_date']->format('M d, Y'),
                        number_format($summary['old_rent'], 2),
                        number_format($summary['new_rent'], 2)
                    ),
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Lease renewal failed for lease ' . $leaseId . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while renewing this lease. Please try again.');
        } finally {
            $lock->release();
        }

        return redirect()->back()->with($result['type'], $result['message']);
    }

}