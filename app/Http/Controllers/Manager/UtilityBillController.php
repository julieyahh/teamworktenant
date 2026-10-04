<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\UtilityBillBatch;
use App\Models\UtilityBillingProof;
use App\Services\BillingService;
use App\Services\DuplicateBillException;
use App\Support\RentCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The utility bill ledger.
 *
 *   GET    /manager/bills                      index()     every bill, filterable
 *   POST   /manager/bills                      store()     issue to one lease
 *   POST   /manager/bills/shared               storeShared() split across leases
 *   GET    /manager/bills/{bill}               show()      one bill + its history
 *   POST   /manager/bills/{bill}/reconcile     reconcile() estimate -> actual (rewrites amount/kWh)
 *   POST   /manager/bills/{bill}/adjust        adjust()    manual correction
 *   POST   /manager/bills/{bill}/void          void()      cancel a wrong bill + everything tied to it
 *
 * This screen is separate from /manager/utilities (UtilityController), which is
 * the older per-tenant balance view. The distinction: that one shows a single
 * rolled-up number per room, this one shows the individual bills behind it —
 * which is where partial payments and arrears become visible.
 *
 * All arithmetic is in App\Services\BillingService.
 */
class UtilityBillController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    public function index(Request $request)
    {
        $query = UtilityBillingProof::with(['tenant', 'lease.unit', 'batch'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $status === 'arrears'
                ? $query->arrears()
                : $query->where('status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('billing_type', $type);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $bills = $query->paginate(25)->withQueryString();

        // Leases that can be billed, for the "issue a bill" form.
        $leases = Lease::with(['unit', 'tenant'])
            ->whereIn('lea_status', ['active', 'Active'])
            ->get();

        // How many of those active leases share each unit — used on the form
        // to divide a Bed-Spacer room's electricity reading across its
        // current occupants. Built from $leases itself (no extra query) so
        // it always agrees with the dropdown the manager is looking at.
        $occupantsByUnit = $leases->groupBy('unit_id')->map->count();

        $summary = [
            'open'      => UtilityBillingProof::open()->get()->sum(fn ($b) => $b->outstanding()),
            'arrears'   => UtilityBillingProof::arrears()->get()->sum(fn ($b) => $b->outstanding()),
            'estimates' => UtilityBillingProof::open()
                ->where('is_estimated', true)->whereNull('reconciled_at')->count(),
        ];

        return view('manager.bills.index', compact('bills', 'leases', 'summary', 'occupantsByUnit'));
    }

    public function show($id)
    {
        $bill = UtilityBillingProof::with([
            'tenant', 'lease.unit', 'batch.bills.lease',
            'adjustments.createdBy',
            'allocations.payment',
        ])->findOrFail($id);

        // Same split the server will use when the final reading is entered.
        $occupants = $this->billing->electricityDivisorFor($bill);

        return view('manager.bills.show', compact('bill', 'occupants'));
    }

    /** Issue a bill against one lease. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'lease_id'      => 'required|exists:leases,id',
            'billing_type'  => 'required|string|in:Electricity,Water,Repair,Other',
            // Electricity is billed by meter reading, not a typed peso amount —
            // 'amount' is required for every other type, 'kwh_reading' for this one.
            'amount'        => 'required_unless:billing_type,Electricity|nullable|numeric|min:0',
            'kwh_reading'   => 'required_if:billing_type,Electricity|nullable|numeric|min:0.01',
            'due_date'      => 'nullable|date',
            'is_estimated'  => 'nullable|boolean',
            'notes'         => 'nullable|string|max:1000',
            'file'          => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'billing_month' => [
                'required',
                'string',
                'max:50',
                'date_format:Y-m',
                function ($attr, $value, $fail) {
                    if (Carbon::createFromFormat('!Y-m', $value)->lt(now()->startOfMonth())) {
                        $fail('You cannot issue a bill for a month that has already passed.');
                    }
                },
            ],
        ]);

        $lease = Lease::with(['tenant', 'unit'])->findOrFail($data['lease_id']);

        $data['billing_month'] = RentCalendar::normaliseBillingMonth($data['billing_month']);

        // One bill per tenant, room, type and month (v19). Checked up front so
        // the manager gets a clear answer before a photo is uploaded; the
        // service checks again on write.
        if ($existing = $this->billing->findDuplicateBill($lease, $data['billing_type'], $data['billing_month'])) {
            return $this->duplicateResponse(new DuplicateBillException($existing));
        }

        // --- Electricity: kWh reading -> peso amount, computed here (never
        // trusted from the client), and split across a Bed-Spacer room's
        // current occupants. ---
        if ($data['billing_type'] === 'Electricity') {
            $rate       = (float) config('billing.electricity.rate_per_kwh', 18);
            $kwh        = (float) $data['kwh_reading'];
            $precision  = (int) config('billing.currency_precision', 2);
            $roomTotal  = round($kwh * $rate, $precision);

            $isBedSpacer = optional($lease->unit)->type === 'Bed-Spacer';

            if ($isBedSpacer) {
                $occupants = max(1, Lease::where('unit_id', $lease->unit_id)
                    ->whereIn('lea_status', ['active', 'Active'])
                    ->count());

                $data['amount'] = round($roomTotal / $occupants, $precision);

                $splitNote = "Electricity: {$kwh} kWh x PHP " . number_format($rate, 2)
                    . " = PHP " . number_format($roomTotal, 2)
                    . " for the room, split {$occupants} way(s) => PHP "
                    . number_format($data['amount'], 2) . " for this tenant.";
            } else {
                $data['amount'] = $roomTotal;

                $splitNote = "Electricity: {$kwh} kWh x PHP " . number_format($rate, 2)
                    . " = PHP " . number_format($roomTotal, 2) . ".";
            }

            $data['kwh_rate'] = $rate;
            $data['notes']    = trim(($data['notes'] ?? '') . "\n" . $splitNote);
        }

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('utility_proofs', 'public');
        }

        try {
            $bill = $this->billing->issueBill($lease, $data, Auth::user());
        } catch (DuplicateBillException $e) {
            return $this->duplicateResponse($e);
        }

        $this->billing->syncLeaseUtilityBalance($lease);

        $this->notifyTenant($lease, $bill);

        return redirect()->route('manager.bills.index')
            ->with('success', 'Bill issued: PHP ' . number_format((float) $bill->amount, 2)
                . ' to ' . ($lease->room_no ?? 'the lease')
                . ($bill->is_estimated ? ' (estimate — the tenant cannot pay it until you set the final amount).' : '.'));
    }

    /**
     * Split one provider bill across several leases (shared meter).
     *
     * The split rule and the remainder policy are recorded on the batch so the
     * answer to "why is my share a centavo more than my neighbour's" is on file
     * rather than being recalculated from memory months later.
     */
    public function storeShared(Request $request)
    {
        $data = $request->validate([
            'lease_ids'        => 'required|array|min:2',
            'lease_ids.*'      => 'exists:leases,id',
            'billing_type'     => 'required|string|in:Electricity,Water,Other',
            'billing_month'    => 'required|string|max:50',
            'total_amount'     => 'required|numeric|min:0.01',
            'due_date'         => 'nullable|date',
            'split_rule'       => 'required|in:equal,headcount,unit_size',
            'remainder_policy' => 'required|in:landlord,designated_tenant',
            'is_estimated'     => 'nullable|boolean',
            'notes'            => 'nullable|string|max:1000',
            'file'             => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $leases = Lease::with(['unit', 'tenant'])
            ->whereIn('id', $data['lease_ids'])
            ->whereIn('lea_status', ['active', 'Active'])
            ->get();

        if ($leases->count() < 2) {
            return redirect()->back()
                ->with('error', 'A shared bill needs at least two active leases.');
        }

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('utility_proofs', 'public');
        }

        // Splitting the same provider bill twice would double-charge everyone.
        $lock = Cache::lock('shared-bill:' . md5($data['billing_type'] . $data['billing_month'] . $data['total_amount']), 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'That bill is already being split. Give it a moment.');
        }

        try {
            $batch = $this->billing->issueSharedBill($leases, $data, Auth::user());

            foreach ($batch->bills as $bill) {
                if ($bill->lease) {
                    $this->billing->syncLeaseUtilityBalance($bill->lease);
                    $this->notifyTenant($bill->lease, $bill, $batch);
                }
            }
        } catch (DuplicateBillException $e) {
            return $this->duplicateResponse($e);
        } catch (\Throwable $e) {
            Log::error('Shared bill split failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not split that bill: ' . $e->getMessage());
        } finally {
            $lock->release();
        }

        return redirect()->route('manager.bills.index')
            ->with('success', $batch->splitExplanation());
    }

    /**
     * The provider's real bill arrived. The bill is rewritten to the actual
     * amount — and, for a metered electricity bill, the actual kWh reading.
     * The estimate stays on file (estimated_amount / estimated_kwh) and the
     * change is logged as a history row on the bill.
     */
    public function reconcile(Request $request, $id)
    {
        $bill = UtilityBillingProof::with('lease.unit', 'tenant', 'batch')->findOrFail($id);

        // A metered electricity bill is finalised by its reading; everything
        // else (water, other, repair, shared-meter shares) by a peso amount.
        $isKwhBill = $bill->billing_type === 'Electricity'
            && ! $bill->batch_id
            && $bill->kwh_reading !== null;

        $data = $request->validate([
            'actual_amount' => ($isKwhBill ? 'nullable' : 'required') . '|numeric|min:0',
            'actual_kwh'    => ($isKwhBill ? 'required' : 'nullable') . '|numeric|min:0.01',
            'reason'        => 'nullable|string|max:1000',
        ]);

        $actualKwh = $isKwhBill ? (float) $data['actual_kwh'] : null;

        // Never trust a peso figure from the browser for a metered bill:
        // reading x the bill's rate, split the way the estimate was split.
        $actualAmount = $isKwhBill
            ? $this->billing->electricityAmountFor($bill, $actualKwh)
            : (float) $data['actual_amount'];

        $estimate = (float) $bill->amount;

        try {
            $adjustment = $this->billing->reconcileEstimate(
                $bill,
                $actualAmount,
                $data['reason'] ?? null,
                Auth::user(),
                $actualKwh
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $bill->refresh();

        if ($bill->lease) {
            $this->billing->syncLeaseUtilityBalance($bill->lease);
        }

        $final = 'PHP ' . number_format((float) $bill->amount, 2)
            . ($actualKwh !== null ? ' (' . number_format($actualKwh, 2) . ' kWh)' : '');

        if ($bill->tenant) {
            Notification::create([
                'user_id' => $bill->tenant->id,
                'title'   => $adjustment ? 'Utility Bill Updated' : 'Utility Bill Finalised',
                'message' => 'Your ' . strtolower((string) $bill->billing_type) . ' bill for ' . $bill->billing_month
                    . ' is now final at ' . $final . '.'
                    . ($adjustment ? ' The estimate was PHP ' . number_format($estimate, 2) . '.' : '')
                    . ' Now outstanding: PHP ' . number_format($bill->outstanding(), 2) . '.'
                    . ($bill->outstanding() >= 0.01 ? ' You can now pay it.' : ''),
            ]);
        }

        if (! $adjustment) {
            return redirect()->back()
                ->with('success', 'Final amount set — same as the estimate (' . $final . '). The tenant can now pay it.');
        }

        return redirect()->back()->with('success',
            'Bill updated to the actual amount: ' . $final . ' (estimate was PHP ' . number_format($estimate, 2)
            . '). The tenant can now pay it.');
    }

    /** A manual correction, for anything that is not an estimate reconciliation. */
    public function adjust(Request $request, $id)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'type'   => 'required|in:correction,goodwill,rounding',
            'reason' => 'required|string|max:1000',
        ]);

        $bill = UtilityBillingProof::with('lease')->findOrFail($id);

        if ($bill->isVoid()) {
            return redirect()->back()->with('error', 'That bill has been voided.');
        }

        // A credit larger than the bill would leave a negative balance. Cap it
        // and say so rather than quietly creating money.
        $amount = round((float) $data['amount'], 2);

        if ($amount < 0 && abs($amount) > $bill->totalDue()) {
            return redirect()->back()->with('error',
                'That credit is larger than the bill (PHP ' . number_format($bill->totalDue(), 2)
                . '). Void the bill instead, or reduce the credit.');
        }

        $bill->adjustments()->create([
            'type'       => $data['type'],
            'amount'     => $amount,
            'reason'     => $data['reason'],
            'created_by' => Auth::id(),
        ]);

        $bill->refresh()->syncPaidTotals()->save();

        if ($bill->lease) {
            $this->billing->syncLeaseUtilityBalance($bill->lease);
        }

        return redirect()->back()->with('success', 'Adjustment recorded. The original bill amount is unchanged.');
    }

    /**
     * Void a bill that should never have been issued.
     *
     * Voiding rather than deleting: a bill the tenant has already seen has to
     * stay visible, marked void with a reason, so a tenant who queries a charge
     * they remember is not told it never existed.
     *
     * Everything tied to the bill is brought into line in one go — see
     * BillingService::voidBill(): lease balance, tenant notice, the repair it
     * charged, the rest of a shared bill (when ticked), open demand notices.
     */
    public function void(Request $request, $id)
    {
        $data = $request->validate([
            'void_reason' => 'required|string|max:500',
            'void_batch'  => 'nullable|boolean',
        ]);

        $bill = UtilityBillingProof::findOrFail($id);

        $lock = Cache::lock('bill-void:' . $bill->id, 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'That bill is already being voided.');
        }

        try {
            $result = $this->billing->voidBill(
                $bill,
                $data['void_reason'],
                Auth::user(),
                (bool) ($data['void_batch'] ?? false)
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } finally {
            $lock->release();
        }

        $parts = [count($result['voided']) > 1
            ? count($result['voided']) . ' bills voided (the whole shared bill).'
            : 'Bill voided.'];

        $parts[] = 'Lease balance updated and the tenant notified.';

        if ($result['maintenance_released']) {
            $parts[] = 'Maintenance request #' . $result['maintenance_released']
                . ' is back to "not yet billed" — its costs are unlocked and it can be charged again.';
        }

        if ($result['skipped_paid']) {
            $parts[] = 'Not voided because money is on them: bill(s) #' . implode(', #', $result['skipped_paid']) . '.';
        }

        if ($result['demands_flagged']) {
            $parts[] = $result['demands_flagged'] . ' open demand notice(s) on this lease were flagged — recheck before escalating.';
        }

        if ($result['pending_payments']) {
            $parts[] = 'Heads up: ' . $result['pending_payments'] . ' pending ' . strtolower((string) $bill->billing_type)
                . ' payment(s) from this tenant are waiting — accepting them now would go to credit or another bill.';
        }

        return redirect()->route('manager.bills.show', $bill->id)->with('success', implode(' ', $parts));
    }

    private function duplicateResponse(DuplicateBillException $e)
    {
        return redirect()->back()
            ->withInput()
            ->with('error', $e->getMessage())
            ->with('duplicate_bill_id', $e->existing->id);
    }

    private function notifyTenant(Lease $lease, UtilityBillingProof $bill, ?UtilityBillBatch $batch = null): void
    {
        if (! $lease->user_id) {
            return;
        }

        $message = 'A ' . strtolower((string) $bill->billing_type) . ' bill of PHP '
            . number_format((float) $bill->amount, 2) . ' for ' . $bill->billing_month
            . ' has been issued for room ' . ($lease->room_no ?? 'N/A') . '.';

        if ($bill->kwh_reading) {
            $message .= ' Based on a meter reading of ' . number_format((float) $bill->kwh_reading, 2)
                . ' kWh at PHP ' . number_format((float) $bill->kwh_rate, 2) . '/kWh'
                . (optional($lease->unit)->type === 'Bed-Spacer' ? ', split across your room.' : '.');
        }

        if ($bill->due_date) {
            $message .= ' Due ' . Carbon::parse($bill->due_date)->format('M d, Y') . '.';
        }

        if ($bill->is_estimated) {
            $message .= ' This is an estimate, shown so you know it is coming. You will be able to pay it '
                . 'once the office sets the final amount from the provider bill.';
        }

        if ($batch) {
            $message .= ' ' . $batch->splitExplanation();
        }

        Notification::create([
            'user_id' => $lease->user_id,
            'title'   => 'New Utility Bill',
            'message' => $message,
        ]);
    }
}
