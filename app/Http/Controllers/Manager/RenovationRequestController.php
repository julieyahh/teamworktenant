<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\RenovationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenovationRequestController extends Controller
{
    /**
     * Approve or deny a renovation request.
     *
     * Both actions run through one method so the "already decided" guard
     * and the notification wording stay in a single place — a second click
     * on Approve/Deny can never write a second decision.
     */
    public function decide(Request $request, $id)
    {
        $request->validate([
            'decision'        => 'required|in:approve,deny',
            'manager_remarks' => 'nullable|string|max:1000',
        ]);

        $lock = Cache::lock('renovation-decision:' . $id, 10);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'This request is already being processed.');
        }

        try {
            $result = DB::transaction(function () use ($request, $id) {

                $renovation = RenovationRequest::with('tenant', 'lease')
                    ->lockForUpdate()
                    ->find($id);

                if (! $renovation) {
                    return ['type' => 'error', 'message' => 'Renovation request not found.'];
                }

                // Idempotency guard — stops duplicate decisions and duplicate notifications.
                if (! $renovation->isPending()) {
                    return [
                        'type'    => 'info',
                        'message' => 'This request was already marked as ' . strtolower($renovation->status) . '.',
                    ];
                }

                if (! $renovation->tenant) {
                    return ['type' => 'error', 'message' => 'The tenant for this request no longer exists.'];
                }

                // Deposit gate, manager side. The tenant-side controller blocks
                // submission, but a request can sit in the queue while the
                // deposit position changes — so approval is checked against the
                // balance as it stands right now, not as it stood at submission.
                // Denying is always allowed.
                if ($request->decision === 'approve'
                    && $renovation->lease
                    && $renovation->lease->hasUnpaidDeposit()) {
                    return [
                        'type'    => 'error',
                        'message' => 'This room has an unpaid deposit of PHP ' .
                                     number_format((float) $renovation->lease->deposit_balance, 2) .
                                     '. Renovation cannot be approved until the deposit is settled.',
                    ];
                }

                $approved = $request->decision === 'approve';
                $status   = $approved ? RenovationRequest::STATUS_APPROVED : RenovationRequest::STATUS_DENIED;

                $renovation->update([
                    'status'          => $status,
                    'manager_remarks' => $request->manager_remarks,
                    'decided_by'      => Auth::id(),
                    'decided_at'      => now(),
                ]);

                $room = $renovation->room_no ?? 'N/A';

                // summaryLabel() rather than title: the Title field was removed
                // from the tenant form in v14, so new requests have none and
                // this would otherwise read `request "" for room 3B`.
                $label = $renovation->summaryLabel();

                $message = $approved
                    ? "Good news! Your renovation request \"{$label}\" for room {$room} has been APPROVED."
                    : "Your renovation request \"{$label}\" for room {$room} has been DENIED.";

                if ($request->filled('manager_remarks')) {
                    $message .= ' Manager remarks: ' . $request->manager_remarks;
                }

                // Notify the tenant (item 9: "Then Notify the Tenant in Notification").
                Notification::create([
                    'user_id' => $renovation->tenant_id,
                    'title'   => $approved ? 'Renovation Request Approved' : 'Renovation Request Denied',
                    'message' => $message,
                ]);

                // Keep a record on the manager's own notification feed too.
                Notification::create([
                    'user_id' => Auth::id(),
                    'title'   => $approved ? 'Renovation Approved' : 'Renovation Denied',
                    'message' => $renovation->tenant->name . "'s renovation request for room {$room} was " .
                                 strtolower($status) . '.',
                ]);

                return [
                    'type'    => 'success',
                    'message' => 'Renovation request ' . strtolower($status) . ' successfully.',
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Renovation decision failed for request ' . $id . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while processing this request. Please try again.');
        } finally {
            $lock->release();
        }

        return redirect()->back()->with($result['type'], $result['message']);
    }
}
