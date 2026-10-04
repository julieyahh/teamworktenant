<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Recording and confirming payments from the manager side.
 *
 *   PATCH /manager/payments/{payment}/status   updatePaymentStatus()  accept/reject a tenant-submitted payment
 *   PATCH /manager/payments                    paymentStore()         record a walk-in/cash payment
 *
 * The tenant-facing equivalent is App\Http\Controllers\Tenant\PaymentController.
 * Keep the two in sync: they write to the same `payments` table.
 *
 * WHERE THE MONEY LOGIC LIVES
 *   Not here. App\Services\BillingService owns allocation across bills,
 *   overpayment handling and balance updates, so the manager side and the
 *   tenant side cannot drift apart the way they did before. This controller
 *   validates, writes the payment row, and calls the service.
 */
class PaymentRecordController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    /**
     * Accept or reject a tenant-submitted payment.
     *
     * Accepting allocates the money; moving a payment back OUT of Accepted
     * reverses those allocations, so a mis-clicked Accept can be undone without
     * leaving a bill showing money it never received.
     */
    public function updatePaymentStatus(Request $request, Payment $payment)
    {
        $request->validate([
            'pay_status' => 'required|in:Pending,Accepted,Rejected',
        ]);

        $oldStatus = $payment->pay_status;
        $newStatus = $request->pay_status;

        if ($oldStatus === $newStatus) {
            return redirect()->back()->with('info', 'That payment is already marked ' . $newStatus . '.');
        }

        // Two managers clicking Accept at once would otherwise allocate twice.
        $lock = Cache::lock('payment-status:' . $payment->id, 10);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'This payment is already being processed.');
        }

        try {
            $tenant = $payment->tenant;

            // --- Accepting -------------------------------------------------
            if ($newStatus === 'Accepted') {
                $payment->update(['pay_status' => 'Accepted', 'accepted_at' => now()]);

                $result = $this->billing->applyAcceptedPayment($payment->fresh());

                if ($tenant) {
                    $this->notifyOfAllocation($payment, $tenant, $result);
                }

                $summary = 'Payment accepted. PHP ' . number_format($result['allocated'], 2) . ' applied.';

                if ($result['credited'] > 0) {
                    $summary .= ' PHP ' . number_format($result['credited'], 2) . ' held as tenant credit.';
                }

                if ($result['refundable'] > 0) {
                    $summary .= ' PHP ' . number_format($result['refundable'], 2) . ' flagged for refund.';
                }

                return redirect()->back()->with('success', $summary);
            }

            // --- Un-accepting ----------------------------------------------
            if ($oldStatus === 'Accepted') {
                $this->billing->reverseAcceptedPayment(
                    $payment,
                    'Payment changed from Accepted to ' . $newStatus
                );

                if ($tenant) {
                    $this->createNotification(
                        $tenant->id,
                        'Payment Reversed',
                        'Payment ' . $payment->reference_number . ' was reversed. The amount has been put back '
                        . 'on your outstanding balance. Please contact the office if this is unexpected.'
                    );
                }
            }

            // Leaving Accepted withdraws the invoice; accepted_at goes with it.
            $payment->update(['pay_status' => $newStatus, 'accepted_at' => null]);

            return redirect()->back()->with('success', 'Payment status updated successfully.');

        } finally {
            $lock->release();
        }
    }

    /**
     * Record a walk-in / cash payment.
     *
     * Partial amounts are allowed for Rent and Utilities — see the note in
     * Tenant\PaymentController@store. The deposit is the one thing that still
     * has to arrive in full.
     */
    public function paymentStore(Request $request)
    {
        $request->validate([
            'tenant_id'    => 'required|exists:users,id',
            'lease_id'     => 'required|exists:leases,id',
            'payment_for'  => 'required|in:Deposit,Rent,Advance Rent,Utilities',
            'pay_amount'   => 'required|numeric|min:0.01',
            'pay_method'   => 'required|string',
            'billing_type' => 'nullable|string|in:Electricity,Water,Repair|required_if:payment_for,Utilities',
            'advance_months' => 'nullable|integer|min:1',
        ]);

        $actor = Auth::user();

        $tenant = $actor->role === 'manager'
            ? User::find($request->tenant_id)
            : $actor;

        if (! $tenant) {
            return redirect()->back()->with('error', 'Tenant not found.');
        }

        $lease = Lease::where('id', $request->lease_id)
            ->where('user_id', $tenant->id)
            ->first();

        // Guard: the lease dropdown is populated by JS, so a stale/mismatched
        // lease_id would otherwise crash on "property on null" below.
        if (! $lease) {
            return redirect()->back()->with('error', 'Selected lease does not belong to that tenant.');
        }

        if (! in_array($lease->lea_status, ['active', 'pending', 'Active', 'Pending'])) {
            return redirect()->back()->with('error', 'You can only make payments for active or pending leases.');
        }

        $amount = round((float) $request->pay_amount, 2);

        $hasDeposit = (float) $lease->deposit_balance > 0;

        if ($hasDeposit && $request->payment_for !== 'Deposit') {
            return redirect()->back()->with('error', 'You must pay the Deposit first.');
        }

        // Advance rent. Same rules as the tenant side (see
        // Tenant\PaymentController@store) — a walk-in advance is the same
        // transaction, and the two sides must not drift.
        if ($request->payment_for === 'Advance Rent') {
            if (! config('billing.advance_rent.enabled', true)) {
                return back()->with('error', 'Advance rent payments are not currently accepted.');
            }

            $monthlyRent = $lease->currentRent();

            if ($monthlyRent <= 0) {
                return back()->with('error',
                    'This lease has no monthly rent set, so rent cannot be paid in advance.');
            }

            $arrears   = round((float) $lease->rent_balance + (float) ($lease->penalty_fee ?? 0), 2);
            $maxMonths = (int) config('billing.advance_rent.max_months', 12);
            $roomLeft  = max(0, $maxMonths - $lease->advanceMonthsCovered());

            if ($roomLeft < 1) {
                return back()->with('error',
                    'This lease is already paid the maximum ' . $maxMonths . ' months ahead.');
            }

            if ($amount < round($arrears + $monthlyRent, 2)) {
                return back()->withErrors([
                    'pay_amount' => 'An advance has to cover at least one full month of rent'
                        . ($arrears > 0 ? ' plus the PHP ' . number_format($arrears, 2) . ' already due' : '')
                        . ' — PHP ' . number_format($arrears + $monthlyRent, 2) . ' minimum. '
                        . 'Use Rent for a part payment of what is currently due.',
                ]);
            }

            if ($amount > round($arrears + ($monthlyRent * $roomLeft), 2)) {
                return back()->withErrors([
                    'pay_amount' => 'That is further ahead than the ' . $maxMonths . '-month cap allows. '
                        . 'Maximum right now: PHP ' . number_format($arrears + ($monthlyRent * $roomLeft), 2) . '.',
                ]);
            }
        }

        if ($request->payment_for === 'Deposit' && $amount < round((float) $lease->deposit_balance, 2)) {
            return back()->withErrors([
                'pay_amount' => 'The deposit must be paid in full. Outstanding: PHP '
                    . number_format((float) $lease->deposit_balance, 2) . '.',
            ]);
        }

        // Overpayment is no longer rejected. Rejecting it meant a tenant handing
        // over a round PHP 1,000 against a PHP 987.50 bill got turned away at
        // the counter. The excess is now recorded and becomes credit against
        // their next bill — see config('billing.overpayment').
        $owed = match ($request->payment_for) {
            'Deposit'   => (float) $lease->deposit_balance,
            'Rent'      => (float) $lease->rent_balance + (float) ($lease->penalty_fee ?? 0),
            'Utilities' => $lease->openUtilityTotal(),
            // An advance is never short: everything above the arrears is held
            // on account, so there is nothing left "outstanding" to report.
            default     => 0.0,
        };

        $payment = Payment::create([
            'tenant_id'        => $tenant->id,
            'lease_id'         => $lease->id,
            'pay_date'         => now(),
            'payment_for'      => $request->payment_for,
            'utility_type'     => $request->payment_for === 'Utilities' ? $request->billing_type : null,
            'pay_amount'       => $amount,
            'pay_method'       => $request->pay_method,
            'pay_status'       => $request->pay_method === 'Cash' ? 'Accepted' : 'Pending',
            'accepted_at'      => $request->pay_method === 'Cash' ? now() : null,
            'account_no'       => $request->account_no,
            'reference_number' => 'PAY-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
        ]);

        $message = 'Payment recorded.';

        // Cash is accepted on the spot, so apply it now. Anything else waits
        // for the manager to confirm the proof.
        if ($payment->pay_status === 'Accepted') {
            $result = $this->billing->applyAcceptedPayment($payment);

            $message = 'Payment recorded. PHP ' . number_format($result['allocated'], 2) . ' applied.';

            if ($result['credited'] > 0) {
                $message .= ' PHP ' . number_format($result['credited'], 2) . ' held as credit.';
            }

            if ($result['refundable'] > 0) {
                $message .= ' PHP ' . number_format($result['refundable'], 2) . ' flagged for refund.';
            }
        }

        if ($request->payment_for === 'Advance Rent') {
            $payment->refresh();
            $months = (int) ($payment->advance_months ?? 0);

            if ($months > 0) {
                $message .= ' Rent is now covered ' . $months . ' month'
                    . ($months === 1 ? '' : 's') . ' ahead'
                    . ($lease->fresh()->rent_paid_through
                        ? ', through ' . Carbon::parse($lease->fresh()->rent_paid_through)->format('F d, Y') . '.'
                        : '.');
            }

            return redirect()->back()->with('success', $message);
        }

        $shortfall = round($owed - $amount, 2);

        if ($shortfall >= 0.01) {
            $message .= ' PHP ' . number_format($shortfall, 2) . ' still outstanding, carried forward.';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Tell the tenant what their money actually did — which bills it cleared,
     * what is left, and where any excess went.
     */
    private function notifyOfAllocation(Payment $payment, User $tenant, array $result): void
    {
        $lines = [
            'Your ' . strtolower((string) $payment->payment_for) . ' payment of PHP '
                . number_format((float) $payment->pay_amount, 2)
                . ' (' . $payment->reference_number . ') has been confirmed.',
        ];

        if (! empty($result['bills'])) {
            $lines[] = 'Applied to ' . count($result['bills']) . ' bill(s), oldest first.';
        }

        if ($lease = $payment->lease) {
            $lease->refresh();

            if ($payment->payment_for === 'Advance Rent') {
                $months = (int) ($payment->advance_months ?? 0);

                if ($months > 0) {
                    $lines[] = 'Your rent is now paid ' . $months . ' month'
                        . ($months === 1 ? '' : 's') . ' ahead'
                        . ($lease->rent_paid_through
                            ? ', through ' . Carbon::parse($lease->rent_paid_through)->format('F d, Y') . '.'
                            : '.');
                }

                if ($lease->advanceRentBalance() >= 0.01) {
                    $lines[] = 'PHP ' . number_format($lease->advanceRentBalance(), 2)
                        . ' is held against your future rent. It is not used for anything else.';
                }

                $this->createNotification($tenant->id, 'Advance Rent Confirmed', implode(' ', $lines));

                return;
            }

            $remaining = match ($payment->payment_for) {
                'Rent'      => (float) $lease->rent_balance + (float) ($lease->penalty_fee ?? 0),
                'Utilities' => $lease->openUtilityTotal(),
                'Deposit'   => (float) $lease->deposit_balance,
                default     => 0.0,
            };

            $lines[] = $remaining >= 0.01
                ? 'PHP ' . number_format($remaining, 2) . ' remains outstanding and carries forward.'
                : 'This balance is now fully settled.';

            if ($payment->payment_for === 'Rent' && $remaining < 0.01 && $lease->due_date) {
                $lines[] = 'Your next due date is ' . Carbon::parse($lease->due_date)->format('F d, Y') . '.';
            }
        }

        if ($result['credited'] > 0) {
            $lines[] = 'PHP ' . number_format($result['credited'], 2)
                . ' was more than you owed and is now held as credit against your next bill.';
        }

        if ($result['refundable'] > 0) {
            $lines[] = 'PHP ' . number_format($result['refundable'], 2)
                . ' was more than you owed and has been flagged for refund. The office will be in touch.';
        }

        $this->createNotification($tenant->id, 'Payment Confirmed', implode(' ', $lines));
    }

    protected function createNotification($userId, $title, $message)
    {
        Notification::create([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'is_read' => false,  // Matches your model
        ]);
    }

    /** The invoice for an accepted payment — same document the tenant gets (v19). */
    public function invoice(Payment $payment)
    {
        if (! $payment->hasInvoice()) {
            return redirect()->back()->with('error', 'Only an accepted payment has an invoice.');
        }

        return app(\App\Services\InvoiceService::class)->download($payment);
    }
}
