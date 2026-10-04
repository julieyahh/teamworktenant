<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;


class PaymentController extends Controller
{
    /**
     * Display tenant payments dashboard
     */
    public function index()
    {
        $tenant = Auth::user();
        // Load leases with utility_balance for the accessor to work
        $tenant->load(['leases' => function($q) {
            $q->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending']);
        }]);

        $lease = $tenant->leases()->latest()->first();  // Get the latest lease

        $payments = $tenant->payments()
            ->with('lease.unit')
            ->orderBy('created_at', 'desc')
            ->get();

       $leasesForSelection = $tenant->leases()
        ->whereIn('lea_status', ['active'])
        ->where('move_out_requested', 0)
        ->with('unit')
        ->get()
        ->map(function ($lease) {
            // What the tenant can actually pay per utility type: the sum of
            // what is left on every PAYABLE bill of that type (v19). This used
            // to be the full amount of the latest unpaid bill from this month
            // or last, which ignored partial payments and older arrears and —
            // worse — offered an estimate up for payment.
            $payable = \App\Models\UtilityBillingProof::forLease($lease->id)->payable()->get();
            $pending = \App\Models\UtilityBillingProof::forLease($lease->id)->awaitingFinalAmount()->get();

            $sumOf = fn ($bills, $type) => round(
                $bills->where('billing_type', $type)->sum(fn ($b) => $b->outstanding()), 2
            );

            $elecProof  = (object) ['amount' => $sumOf($payable, 'Electricity')];
            $waterProof = (object) ['amount' => $sumOf($payable, 'Water')];

            $lease->electricity_estimate = $sumOf($pending, 'Electricity');
            $lease->water_estimate       = $sumOf($pending, 'Water');

            // Attach the amounts directly onto the lease object
            $lease->electricity_amount = $elecProof?->amount ?? 0;
            $lease->water_amount       = $waterProof?->amount ?? 0;

            // Advance rent: what a month costs, how much is already on
            // account, and how many more months the tenant may buy. The cap
            // is applied here as well as in store() so the dropdown never
            // offers a month the server will refuse.
            $lease->monthly_rent_amount = $lease->currentRent();
            $lease->advance_on_account  = $lease->advanceRentBalance();
            $lease->advance_months_left = max(
                0,
                (int) config('billing.advance_rent.max_months', 12) - $lease->advanceMonthsCovered()
            );

            return $lease;
        });

        // Show Deposit option only if deposit is not fully paid
        $depositExists = ($tenant->deposit_amount ?? 0) > 0;

        $tenantLeases = Lease::where('user_id', $tenant->id)
            ->where('lea_status', 'active')
            ->where('move_out_requested', 0)
            ->get();

        $totalUnpaid = 0;

        // READ ONLY. This loop used to CREATE the 10% late penalty as a side
        // effect of rendering the page: a tenant who never opened their
        // payments screen was never penalised, and one who did was penalised
        // on page load. Whether a charge exists must not depend on whether
        // somebody looked at a screen, so assessment moved to the nightly
        // `rent:charge` command (BillingService::assessLatePenalty).
        foreach ($tenantLeases as $lease) {
            $totalUnpaid += (float) $lease->rent_balance + (float) ($lease->penalty_fee ?? 0);
        }


        // User balances
        $unpaidRent = $totalUnpaid;
        // Sum of what is still owed across open bills. Deliberately computed
        // from the bills rather than read off users.utility_balance, which is
        // only a cache and can lag behind a partial payment.
        $unpaidUtilities = round(
            $tenantLeases->sum(fn ($l) => $l->openUtilityTotal()),
            2
        );
        $depositBalance = Lease::where('user_id', $tenant->id)
            ->where('lea_status', 'active')
            ->where('move_out_requested', 0)
            ->sum('deposit_balance');

        $userCredit = $tenant-> user_credit ?? 0;

        // Advance rent held across every live lease, plus the earliest date any
        // of them stops being covered. The earliest is the useful one: that is
        // when the tenant next has to do something.
        $advanceTotal = round($tenantLeases->sum(fn ($l) => $l->advanceRentBalance()), 2);

        $advancePaidThrough = $tenantLeases
            ->filter(fn ($l) => $l->rent_paid_through !== null)
            ->sortBy('rent_paid_through')
            ->first()?->rent_paid_through;

        $advanceLeases = $tenantLeases->filter(fn ($l) => $l->hasAdvanceRent())->values();

        // The rent ledger: which months have been charged, which are settled,
        // and which are not. `rent_balance` can say "you owe PHP 15,000"; it
        // cannot say WHICH MONTHS, which is the first thing anyone asks.
        $rentLedger = [];

        foreach ($tenantLeases as $l) {
            foreach ($l->rentChargeLedger() as $line) {
                $rentLedger[] = $line + ['lease' => $l];
            }
        }

        // Newest first, and capped: a lease three years old has 36 of these
        // and the tenant cares about the recent ones.
        usort($rentLedger, fn ($a, $b) => $b['charge']->period_start <=> $a['charge']->period_start);
        $rentLedger = array_slice($rentLedger, 0, 12);

        // Load ALL utility proofs with lease and unit relationships (no limits, no filters)
        $utilityProofs = \App\Models\UtilityBillingProof::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'void')
            ->with(['lease.unit', 'lease.payments'])
            ->orderBy('created_at', 'desc')
            ->get(); // Get ALL records, no pagination or limit

        // ✅ Unpaid utilities grouped by billing month
        //
        // This used to sum every proof's full `amount`, paid or not, which meant
        // a tenant who had settled March still saw March in their unpaid list.
        // It now sums outstanding() — the bill less what has actually been
        // allocated to it — so a partially paid bill shows only the remainder.
        $unpaidUtilitiesByMonth = [];
        foreach ($utilityProofs as $proof) {
            if (!$proof->billing_month) {
                continue; // Skip if no billing month
            }

            // v19: an estimate is not owed until its final amount is set.
            if (! $proof->isPayable()) {
                continue;
            }

            $outstanding = $proof->outstanding();

            if ($outstanding > 0) {
                $billingMonth = $proof->billing_month;
                $unpaidUtilitiesByMonth[$billingMonth] =
                    round(($unpaidUtilitiesByMonth[$billingMonth] ?? 0) + $outstanding, 2);
            }
        }

        // Open bills, oldest first — the tenant's actual ledger. Anything past
        // its due date is arrears carried forward from an earlier cycle.
        $openBills = $utilityProofs->filter(fn ($bill) => $bill->outstanding() > 0)
            ->sortBy(fn ($bill) => $bill->due_date ?? $bill->created_at)
            ->values();

        // Estimates waiting for their final amount — shown, never payable.
        $estimatedUtilities = round(
            $openBills->filter(fn ($bill) => $bill->isUnreconciledEstimate())
                ->sum(fn ($bill) => $bill->outstanding()),
            2
        );

        $utilityArrears = round(
            $openBills->filter(fn ($bill) => $bill->isOverdue())
                ->sum(fn ($bill) => $bill->outstanding()),
            2
        );

        // Sort by month (most recent first) - convert to Carbon dates for proper sorting
        uksort($unpaidUtilitiesByMonth, function($a, $b) {
            try {
                $dateA = Carbon::parse($a);
                $dateB = Carbon::parse($b);
                return $dateB->gt($dateA) ? 1 : -1; // Most recent first
            } catch (\Exception $e) {
                // Fallback to string comparison if parsing fails
                return strcmp($b, $a);
            }
        });

        // ✅ Calculate unpaid month for Rent
        // $unpaidRentMonth = $this->getUnpaidMonth($tenant, $lease, 'Rent');
        $unpaidRentMonth = Carbon::parse($lease->due_date)->format('M, d, Y');

        // ✅ Calculate unpaid month for Utilities
        // Use the most recent billing month from utility proofs, but never show future months
        if (!empty($unpaidUtilitiesByMonth)) {
            // Get the first (most recent) month from the sorted array
            $mostRecentBillingMonth = array_key_first($unpaidUtilitiesByMonth);
            // Try to parse and format it
            try {
                $billingDate = Carbon::parse($mostRecentBillingMonth);
                $currentMonth = now()->startOfMonth();

                // If the billing month is in the future, use current month instead
                if ($billingDate->gt($currentMonth)) {
                    $unpaidUtilitiesMonth = now()->format('F Y');
                } else {
                    $unpaidUtilitiesMonth = $billingDate->format('F Y');
                }
            } catch (\Exception $e) {
                // If parsing fails, use current month
                $unpaidUtilitiesMonth = now()->format('F Y');
            }
        } else {
            // No utility proofs, use current month
            $unpaidUtilitiesMonth = now()->format('F Y');
        }


        return view('tenant.payments', compact(
            'payments',
            'leasesForSelection',
            'depositExists',
            'unpaidRent',
            'unpaidUtilities',
            'depositBalance',
            'userCredit',
            'unpaidRentMonth',      // Pass dynamic month for rent
            'unpaidUtilitiesMonth',  // Pass dynamic month for utilities
            'utilityProofs',
            'unpaidUtilitiesByMonth',  // Pass unpaid utilities grouped by month
            'openBills',               // Open bills, oldest first (the ledger)
            'utilityArrears',          // Of those, the part already past due
            'estimatedUtilities',      // v19: estimates shown but not payable
            'tenantLeases',
            'advanceTotal',            // Rent money held on account
            'advancePaidThrough',      // Earliest date cover runs out
            'advanceLeases',           // The leases that actually have some
            'rentLedger'               // Rent charged by month, newest first
        ));
    }

    public function createNotification($title, $message)
    {
        $userId = Auth::id(); // Gets the ID directly, which is all you need for 'user_id'

        if ($userId) {
            Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
            ]);

            return true;
        }

        return false;
    }

    public function store(Request $request)
    {
        $request->validate([
            'lease_id'    => 'required|exists:leases,id',
            'pay_method'  => 'required|string',
            'payment_for' => 'required|string|in:Deposit,Rent,Advance Rent,Utilities',
            'pay_amount'  => 'required|numeric|min:0.01',
            'proof'       => 'required|image|mimes:jpg,jpeg,png',
            'account_no'  => 'nullable|string|max:255',
            'billing_type' => 'nullable|string|in:Electricity,Water|required_if:payment_for,Utilities',
            // Display only — the number of months granted is derived from the
            // money by BillingService, never trusted from the form.
            'advance_months' => 'nullable|integer|min:1',
        ]);

        $tenant = Auth::user();
        $lease = Lease::where('id', $request->lease_id)
            ->where('user_id', $tenant->id)
            ->first();

        if (!$lease) {
            return redirect()->back()->with('error', 'Selected lease not found.');
        }

        // Check if lease is still active or pending
        if (!in_array($lease->lea_status, ['active', 'pending', 'Active', 'Pending'])) {
            return redirect()->back()->with('error', 'You can only make payments for active or pending leases.');
        }

        // --- What must still be paid in full, and what may be paid in parts ---
        //
        // Deposit: still all-or-nothing. It is security against damage, so a
        // half-funded deposit protects nobody, and the deposit is what gates
        // maintenance and renovation access.
        //
        // Rent and Utilities: partial payments are ACCEPTED. A tenant who can
        // put in PHP 2,000 of a PHP 3,500 bill this week should be able to,
        // and the remainder carries forward as arrears. Refusing the money
        // does not make it more likely to arrive — it just leaves the landlord
        // with a larger unpaid balance and a tenant who has spent it elsewhere.
        //
        // The balance owed is tracked per bill (see App\Services\BillingService),
        // so "rent paid, utilities partial" is a state the system can hold.

        $hasDeposit = $lease->deposit_balance > 0;

        if ($hasDeposit && $request->payment_for !== 'Deposit') {
            return redirect()->back()->with('error', 'You must pay the Deposit first.');
        }

        // --- Estimated bills cannot be paid (v19) --------------------------
        //
        // The tenant sees an estimate so they know it is coming, but money
        // only lands once the office has set the final amount. If the only
        // thing open for this utility is an estimate, refuse — otherwise the
        // payment would just sit as credit and the tenant would think they
        // had paid the bill.
        if ($request->payment_for === 'Utilities') {
            $type = $request->billing_type;

            $payableNow = \App\Models\UtilityBillingProof::forLease($lease->id)
                ->payable()
                ->where('billing_type', $type)
                ->get()
                ->sum(fn ($b) => $b->outstanding());

            if ($payableNow < 0.01) {
                $estimate = \App\Models\UtilityBillingProof::forLease($lease->id)
                    ->awaitingFinalAmount()
                    ->where('billing_type', $type)
                    ->oldestFirst()
                    ->first();

                return redirect()->back()->with('error', $estimate
                    ? 'Your ' . strtolower($type) . ' bill for ' . $estimate->billing_month
                        . ' is still an estimate (PHP ' . number_format($estimate->outstanding(), 2) . '). '
                        . 'It can be paid once the office sets the final amount from the provider bill — '
                        . 'you will get a notification when it is ready.'
                    : 'There is no ' . strtolower((string) $type) . ' bill to pay on this room right now.');
            }

        }

        // --- Advance rent -------------------------------------------------
        //
        // Deliberately stricter than Rent. A part payment of rent is a tenant
        // doing what they can this week; a part payment of ADVANCE rent is
        // just money sitting on account buying nothing, which is a worse deal
        // for them than keeping it. So an advance has to be at least one whole
        // month, and it is capped so the landlord is not sitting on a year and
        // a half of someone else's money.
        if ($request->payment_for === 'Advance Rent') {
            if (!config('billing.advance_rent.enabled', true)) {
                return redirect()->back()->with('error', 'Advance rent payments are not currently accepted.');
            }

            $monthlyRent = $lease->currentRent();

            if ($monthlyRent <= 0) {
                return redirect()->back()->with(
                    'error',
                    'This unit has no monthly rent set, so rent cannot be paid in advance. Please contact the office.'
                );
            }

            $arrears  = round((float) $lease->rent_balance + (float) ($lease->penalty_fee ?? 0), 2);
            $minimum  = round($arrears + $monthlyRent, 2);
            $maxMonths = (int) config('billing.advance_rent.max_months', 12);
            $alreadyCovered = $lease->advanceMonthsCovered();
            $roomLeft = max(0, $maxMonths - $alreadyCovered);

            if ($roomLeft < 1) {
                return redirect()->back()->with(
                    'error',
                    'Your rent is already paid ' . $alreadyCovered . ' month(s) ahead, which is the maximum. '
                    . 'Please wait until some of that is used up.'
                );
            }

            if (round((float) $request->pay_amount, 2) < $minimum) {
                return redirect()->back()->with(
                    'error',
                    'An advance payment has to cover at least one full month of rent'
                    . ($arrears > 0 ? ' on top of the PHP ' . number_format($arrears, 2) . ' already outstanding' : '')
                    . ' — PHP ' . number_format($minimum, 2) . ' minimum. '
                    . 'To pay part of what is currently due, choose Rent instead.'
                );
            }

            $ceiling = round($arrears + ($monthlyRent * $roomLeft), 2);

            if (round((float) $request->pay_amount, 2) > $ceiling) {
                return redirect()->back()->with(
                    'error',
                    'That is more than ' . $roomLeft . ' month(s) ahead, which is as far forward as we can take. '
                    . 'The most you can pay right now is PHP ' . number_format($ceiling, 2) . '.'
                );
            }
        }

        // A pending payment does not block the next one — a tenant who has
        // submitted rent should not have to wait for the office to confirm it
        // before they can pay their electricity. The deposit is the exception:
        // it is all-or-nothing and gates everything else, so a second
        // submission while the first is unreviewed can only be a duplicate.
        if ($request->payment_for === 'Deposit') {
            $pendingDeposit = $lease->payments()
                ->where('payment_for', 'Deposit')
                ->whereIn('pay_status', ['Pending', 'pending'])
                ->exists();

            if ($pendingDeposit) {
                return redirect()->back()->with(
                    'info',
                    'Your deposit payment is already submitted and waiting for the office to confirm it. '
                    . 'You do not need to send it again.'
                );
            }
        }

        if ($hasDeposit && $request->payment_for === 'Deposit') {
            if (round((float) $request->pay_amount, 2) < round((float) $lease->deposit_balance, 2)) {
                return redirect()->back()->with(
                    'error',
                    'The deposit has to be paid in full. Outstanding: PHP ' .
                    number_format((float) $lease->deposit_balance, 2) . '.'
                );
            }
        }

        $pathToProof = $request->hasFile('proof')
            ? $request->file('proof')->store('proofs', 'public')
            : null;

        $referenceNumber = 'PAY-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        // Always set to 'Pending' initially - no automatic status changes
        $paymentStatus = 'Pending';

        $finalAmount = $request->pay_amount;

        // ❌ REMOVE: Do NOT apply credits or deduct balances here
        // (This will be handled when the payment is accepted)

        // Create the payment record
        $payment = Payment::create([
            'tenant_id'        => $tenant->id,
            'lease_id'         => $lease->id,
            'pay_date'         => now(),
            'pay_amount'       => $finalAmount,
            'pay_method'       => $request->pay_method,
            'pay_status'       => $paymentStatus,  // Always 'Pending'
            'proof'            => $pathToProof,
            'payment_for'      => $request->payment_for,
            'utility_type'     => $request->payment_for === 'Utilities' ? $request->billing_type : null,
            'account_no'       => $request->account_no,
            'reference_number' => $referenceNumber,
        ]);

        // Create notification
        $managers = \App\Models\User::where('role', 'manager')->get();
        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title' => 'Payment',
                'message' => auth()->user()->name.' has submitted a payment for your review.',
            ]);
        }

        // ❌ REMOVE: All switch-case deduction logic (move to acceptance handler)

        // Tell the tenant what will still be owed once this is accepted, so a
        // deliberate partial payment does not look like it cleared the bill.
        $message = "Payment submitted. Reference Number: {$referenceNumber}. Status: {$paymentStatus}.";

        if ($request->payment_for === 'Advance Rent') {
            // Say what it buys, not what is left owing — an advance by
            // definition leaves nothing outstanding.
            $arrears     = round((float) $lease->rent_balance + (float) ($lease->penalty_fee ?? 0), 2);
            $forward     = max(0.0, round((float) $finalAmount - $arrears, 2));
            $monthsAhead = $lease->currentRent() > 0 ? (int) floor($forward / $lease->currentRent()) : 0;

            if ($arrears > 0) {
                $message .= ' PHP ' . number_format($arrears, 2)
                    . ' of this clears what is currently due first.';
            }

            if ($monthsAhead > 0) {
                $message .= ' Once confirmed, your rent will be covered '
                    . $monthsAhead . ' month' . ($monthsAhead === 1 ? '' : 's') . ' ahead.';
            }

            return redirect()->route('tenant.payments')->with('success', $message);
        }

        $owed = match ($request->payment_for) {
            'Rent'      => (float) $lease->rent_balance + (float) ($lease->penalty_fee ?? 0),
            'Utilities' => $lease->openUtilityTotal(),
            'Deposit'   => (float) $lease->deposit_balance,
            default     => 0.0,
        };

        $shortfall = round($owed - (float) $finalAmount, 2);

        if ($shortfall >= 0.01) {
            $message .= ' Once this is confirmed, PHP ' . number_format($shortfall, 2) .
                        ' will still be outstanding and will carry forward.';
        }

        return redirect()->route('tenant.payments')->with('success', $message);
    }

    /**
     * Download the invoice for an accepted payment (v19).
     *
     * Only the tenant's own payments, and only once the office has accepted
     * them — a pending or rejected payment has no invoice because nothing was
     * received yet.
     */
    public function invoice(Payment $payment)
    {
        if ((int) $payment->tenant_id !== (int) Auth::id()) {
            abort(404);
        }

        if (! $payment->hasInvoice()) {
            return redirect()->route('tenant.payments')->with('error',
                'An invoice is available once the office has accepted this payment.');
        }

        return app(\App\Services\InvoiceService::class)->download($payment);
    }

    private function getUnpaidMonth($tenant, $lease, $paymentType)
    {
        if (!$lease) {
            return now()->format('F Y');  // Fallback to current month
        }
        // Get the latest accepted payment for this type
        $latestPayment = $tenant->payments()
            ->where('payment_for', $paymentType)
            ->where('pay_status', 'Accepted')
            ->orderBy('pay_date', 'desc')
            ->first();
        $leaseStart = \Carbon\Carbon::parse($lease->lea_start_date);
        $currentMonth = now()->startOfMonth();
        if ($latestPayment) {
            // Use the payment date to determine the last paid month
            $lastPaidMonth = \Carbon\Carbon::parse($latestPayment->pay_date)->startOfMonth();
            $nextUnpaidMonth = $lastPaidMonth->copy()->addMonth();
        } else {
            // No payments: Start from lease start or current month
            $nextUnpaidMonth = $leaseStart->startOfMonth();
            if ($nextUnpaidMonth->lt($currentMonth)) {
                $nextUnpaidMonth = $currentMonth;
            }
        }
        return $nextUnpaidMonth->format('F Y');
    }

}
