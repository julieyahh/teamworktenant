<?php

namespace App\Services;

use App\Models\BillAdjustment;
use App\Models\BillPaymentAllocation;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Notification;
use App\Models\PaymentDemand;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\User;
use App\Models\UtilityBillBatch;
use App\Models\UtilityBillingProof;
use App\Support\RentCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Every peso decision in one class.
 *
 * The rules it implements (all configured in config/billing.php, all covered by
 * tests/Feature/BillingTest.php):
 *
 *   1. A utility charge is its own bill, separate from rent. A tenant can be
 *      "rent paid, utilities partial" — never all-or-nothing.
 *   2. Payments are allocated across open bills oldest-first, so arrears clear
 *      before the current month.
 *   3. Anything left over after every bill is settled becomes tenant credit
 *      (or is flagged for refund). It is never silently dropped.
 *   4. A shared bill is split by a stated rule; the rounding remainder goes
 *      somewhere specific and is recorded on the batch.
 *   5. An estimated bill is set to the actual amount (and kWh) directly; the
 *      estimate is kept in estimated_amount / estimated_kwh and the change is
 *      logged as a reconciliation row (history only, not summed).
 *   6. Voiding a bill cascades: lease balance, tenant notice, the repair it
 *      charged, shared-bill siblings, open demand notices (voidBill()).
 *
 * Nothing outside this class should write utility_billing_proofs.amount_paid,
 * utility_billing_proofs.status, or bill_payment_allocations.
 */
class BillingService
{
    /* ==================================================================
     |  1. ISSUING BILLS
     * ================================================================== */

    /**
     * Issue a bill to a single lease.
     *
     * @param  array{billing_type:string,billing_month:string,amount:float,due_date?:mixed,
     *               is_estimated?:bool,file_path?:?string,notes?:?string}  $data
     */
    public function issueBill(Lease $lease, array $data, ?User $issuedBy = null): UtilityBillingProof
    {
        $amount = round((float) ($data['amount'] ?? 0), $this->precision());

        if ($amount < 0) {
            throw new InvalidArgumentException('A bill cannot be for a negative amount.');
        }

        $type  = $data['billing_type'] ?? 'Other';
        $month = RentCalendar::normaliseBillingMonth($data['billing_month'] ?? Carbon::now()->format('F Y'));

        // One bill per tenant, room, type and month (v19). Enforced here as
        // well as in the controller so no other caller can slip past it.
        if ($existing = $this->findDuplicateBill($lease, $type, $month)) {
            throw new DuplicateBillException($existing);
        }

        return UtilityBillingProof::create([
            'tenant_id'     => $lease->user_id,
            'lease_id'      => $lease->id,
            'billing_type'  => $type,
            'billing_month' => $month,
            'amount'        => $amount,
            'kwh_reading'   => $data['kwh_reading'] ?? null,
            'kwh_rate'      => $data['kwh_rate'] ?? null,
            'amount_paid'   => 0,
            'due_date'      => $this->resolveDueDate($data['due_date'] ?? null, $month),
            'is_estimated'  => (bool) ($data['is_estimated'] ?? false),
            'file_path'     => $data['file_path'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'maintenance_request_id' => $data['maintenance_request_id'] ?? null,
            // A zero-amount bill has nothing to collect. Marking it paid on
            // creation keeps it off the arrears list instead of sitting there
            // as a permanently "unpaid" 0.00 that nobody can ever clear.
            'status'        => $amount > 0
                ? UtilityBillingProof::STATUS_UNPAID
                : UtilityBillingProof::STATUS_PAID,
            'created_by'    => $issuedBy?->id,
        ]);
    }

    /**
     * The bill that already exists for this tenant, this room, this type and
     * this month — or null if there is none.
     *
     * "Same room" is matched on the room number, not only the lease id, so a
     * tenant whose lease on a room was replaced still cannot be billed twice
     * for that room's month. A tenant renting two rooms CAN get one bill per
     * room — they are two meters.
     *
     * Voided bills do not count: voiding a wrong bill and issuing the right
     * one is the correct way to fix a mistake. Types outside
     * billing.one_bill_per_month_types (Repair) are never blocked.
     */
    public function findDuplicateBill(Lease $lease, string $type, string $billingMonth): ?UtilityBillingProof
    {
        $types = (array) config('billing.one_bill_per_month_types', ['Electricity', 'Water', 'Other']);

        if (! in_array($type, $types, true)) {
            return null;
        }

        $target = RentCalendar::normaliseBillingMonth($billingMonth);
        $room   = $lease->room_no;

        return UtilityBillingProof::query()
            ->where('tenant_id', $lease->user_id)
            ->where('billing_type', $type)
            ->where('status', '!=', UtilityBillingProof::STATUS_VOID)
            ->whereNull('voided_at')
            ->where(function ($q) use ($lease, $room) {
                $q->where('lease_id', $lease->id);

                if ($room !== null && $room !== '') {
                    $q->orWhereHas('lease', fn ($l) => $l->where('room_no', $room));
                }
            })
            ->orderBy('id')
            ->get()
            // billing_month is free text on rows written before v19, so the
            // comparison is done on the normalised spelling, not in SQL.
            ->first(fn ($bill) => RentCalendar::normaliseBillingMonth($bill->billing_month) === $target);
    }

    /* ==================================================================
     |  2. SPLITTING A SHARED BILL
     * ================================================================== */

    /**
     * Divide an amount across participants and hand back exact shares that add
     * up to the total. Pure arithmetic — no database, so it is trivial to test.
     *
     * @param  array<int,float>  $weights  keyed by lease id; the basis for each
     *                                     share (occupants, room_price, or 1.0)
     * @return array{shares:array<int,float>, remainder:float, designated_lease_id:?int}
     *
     * `remainder` is what rounding could not assign. Under the 'landlord'
     * policy it stays unassigned and the landlord absorbs it. Under
     * 'designated_tenant' it has already been folded into one share, and that
     * lease id comes back so it can be recorded.
     */
    public function splitAmount(float $total, array $weights, ?string $remainderPolicy = null): array
    {
        if (empty($weights)) {
            throw new InvalidArgumentException('A shared bill needs at least one participating lease.');
        }

        $policy    = $remainderPolicy ?? config('billing.rounding_remainder', 'landlord');
        $precision = $this->precision();
        $total     = round($total, $precision);

        $weightSum = array_sum($weights);

        // Every weight zero (e.g. headcount split where no lease records
        // occupants) would divide by zero. Fall back to an equal split rather
        // than failing — a bill that cannot be issued is worse than one split
        // evenly, and the batch notes record what happened.
        if ($weightSum <= 0) {
            $weights   = array_fill_keys(array_keys($weights), 1.0);
            $weightSum = (float) count($weights);
        }

        $shares = [];
        foreach ($weights as $leaseId => $weight) {
            // Round DOWN so the shares can never sum to more than the total.
            // A tenant being asked for a centavo they do not owe is a worse
            // failure than the landlord absorbing one.
            $exact  = $total * ((float) $weight / $weightSum);
            $factor = 10 ** $precision;
            $shares[$leaseId] = floor($exact * $factor) / $factor;
        }

        $remainder = round($total - array_sum($shares), $precision);

        $designated = null;

        if ($remainder > 0 && $policy === UtilityBillBatch::REMAINDER_DESIGNATED) {
            // Deterministic: largest share takes it; ties broken by lowest
            // lease id. The same input always produces the same bills, which
            // is what makes this testable and defensible to a tenant.
            $designated = $this->pickDesignatedLease($shares);
            $shares[$designated] = round($shares[$designated] + $remainder, $precision);
        }

        return [
            'shares'              => $shares,
            'remainder'           => $remainder,
            'designated_lease_id' => $designated,
        ];
    }

    /** Largest share wins; ties go to the lowest lease id. Never random. */
    private function pickDesignatedLease(array $shares): int
    {
        $best = null;

        foreach ($shares as $leaseId => $share) {
            if ($best === null
                || $share > $shares[$best]
                || ($share === $shares[$best] && $leaseId < $best)) {
                $best = $leaseId;
            }
        }

        return (int) $best;
    }

    /**
     * The weight each lease carries under a given split rule.
     *
     *   equal      1.0 each
     *   headcount  occupants on the lease (falls back to 1 when unrecorded)
     *   unit_size  the unit's room_price, our available stand-in for size
     *
     * @param  Collection<int,Lease>  $leases
     * @return array<int,float>
     */
    public function weightsFor(Collection $leases, string $rule): array
    {
        $weights = [];

        foreach ($leases as $lease) {
            $weights[$lease->id] = match ($rule) {
                UtilityBillBatch::RULE_HEADCOUNT => (float) max(1, (int) ($lease->unit->no_of_occupants ?? 1)),
                UtilityBillBatch::RULE_UNIT_SIZE => (float) max(0.01, (float) ($lease->unit->room_price ?? 0)),
                default                          => 1.0,
            };
        }

        return $weights;
    }

    /**
     * Split one provider bill across several leases and write the batch plus
     * one bill per lease. All-or-nothing.
     *
     * @param  Collection<int,Lease>  $leases
     */
    public function issueSharedBill(Collection $leases, array $data, ?User $issuedBy = null): UtilityBillBatch
    {
        $rule = $data['split_rule'] ?? config('billing.default_split_rule', 'equal');

        if (!in_array($rule, config('billing.split_rules', ['equal']), true)) {
            throw new InvalidArgumentException("Unknown split rule [{$rule}].");
        }

        $policy = $data['remainder_policy'] ?? config('billing.rounding_remainder', 'landlord');
        $total  = round((float) ($data['total_amount'] ?? 0), $this->precision());
        $month  = RentCalendar::normaliseBillingMonth($data['billing_month'] ?? Carbon::now()->format('F Y'));
        $due    = $this->resolveDueDate($data['due_date'] ?? null, $month);

        // All-or-nothing: if any participant already has this month's bill,
        // nobody gets one. Checked before anything is written.
        foreach ($leases as $lease) {
            if ($existing = $this->findDuplicateBill($lease, $data['billing_type'] ?? 'Other', $month)) {
                throw new DuplicateBillException($existing);
            }
        }

        $weights = $this->weightsFor($leases, $rule);
        $split   = $this->splitAmount($total, $weights, $policy);

        return DB::transaction(function () use ($leases, $data, $rule, $policy, $total, $due, $split, $weights, $issuedBy, $month) {

            $batch = UtilityBillBatch::create([
                'billing_type'        => $data['billing_type'] ?? 'Other',
                'billing_month'       => $month,
                'total_amount'        => $total,
                'due_date'            => $due,
                'split_rule'          => $rule,
                'remainder_policy'    => $policy,
                'rounding_absorbed'   => $split['remainder'],
                'designated_lease_id' => $split['designated_lease_id'],
                'is_estimated'        => (bool) ($data['is_estimated'] ?? false),
                'file_path'           => $data['file_path'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'created_by'          => $issuedBy?->id,
            ]);

            foreach ($leases as $lease) {
                $share = $split['shares'][$lease->id] ?? 0.0;

                $bill = $this->issueBill($lease, [
                    'billing_type'  => $batch->billing_type,
                    'billing_month' => $batch->billing_month,
                    'amount'        => $share,
                    'due_date'      => $due,
                    'is_estimated'  => $batch->is_estimated,
                    'file_path'     => $batch->file_path,
                    'notes'         => $data['notes'] ?? null,
                ], $issuedBy);

                $bill->batch_id          = $batch->id;
                $bill->share_basis_value = $weights[$lease->id] ?? null;
                $bill->rounding_adjustment = ($split['designated_lease_id'] === $lease->id)
                    ? $split['remainder']
                    : 0;
                $bill->save();
            }

            return $batch->fresh('bills');
        });
    }

    /* ==================================================================
     |  3. RECONCILING AN ESTIMATE
     * ================================================================== */

    /**
     * The provider's real bill arrived. Set the bill to the ACTUAL figure.
     *
     * v20: `amount` (and `kwh_reading` for electricity) are rewritten to the
     * actual values, so the bill, the ledger, the tenant's screen and the
     * invoice all show the real number. Nothing is lost:
     *   - estimated_amount / estimated_kwh keep what the tenant was first shown
     *     (set on the first reconciliation only);
     *   - a reconciliation row in bill_adjustments records the difference, who
     *     set it and why. That row is history only — totalDue() skips it, so
     *     the difference is not counted twice.
     *
     * Manual corrections (goodwill etc.) already on the bill stay on top.
     *
     * Returns the history row, or null when the estimate was already right.
     */
    public function reconcileEstimate(
        UtilityBillingProof $bill,
        float $actualAmount,
        ?string $reason = null,
        ?User $by = null,
        ?float $actualKwh = null          // final meter reading (electricity only)
    ): ?BillAdjustment {

        if ($bill->isVoid()) {
            throw new InvalidArgumentException('A voided bill cannot be reconciled.');
        }

        $actual = round($actualAmount, $this->precision());

        if ($actual < 0) {
            throw new InvalidArgumentException('The actual bill cannot be negative.');
        }

        if ($actualKwh !== null) {
            if ($bill->billing_type !== 'Electricity') {
                throw new InvalidArgumentException('A meter reading only applies to electricity bills.');
            }
            if ($actualKwh <= 0) {
                throw new InvalidArgumentException('The meter reading must be greater than zero.');
            }
            $actualKwh = round($actualKwh, 2);
        }

        return DB::transaction(function () use ($bill, $actual, $actualKwh, $reason, $by) {

            // Lock the row so two managers reconciling at once cannot both
            // work from the same stale amount.
            $bill = UtilityBillingProof::whereKey($bill->getKey())->lockForUpdate()->firstOrFail();

            $originalAmount = round((float) $bill->amount, $this->precision());
            $originalKwh    = $bill->kwh_reading !== null ? (float) $bill->kwh_reading : null;
            $difference     = round($actual - $originalAmount, $this->precision());

            $adjustment = null;

            if (abs($difference) >= 0.01) {
                $reasonText = $reason ?: $this->defaultReconcileReason($bill, $actualKwh, $originalKwh);

                $reasonText .= sprintf(' Estimate PHP %s -> actual PHP %s.',
                    number_format($originalAmount, 2), number_format($actual, 2));

                if ($reason && $actualKwh !== null) {
                    $reasonText .= sprintf(
                        ' (Meter reading: %s kWh, estimated %s kWh.)',
                        number_format($actualKwh, 2),
                        $originalKwh !== null ? number_format($originalKwh, 2) : 'n/a'
                    );
                }

                // History only — see UtilityBillingProof::adjustmentTotal().
                $adjustment = BillAdjustment::create([
                    'bill_id'       => $bill->id,
                    'type'          => BillAdjustment::TYPE_RECONCILIATION,
                    'amount'        => $difference,
                    'actual_amount' => $actual,
                    'reason'        => $reasonText,
                    'created_by'    => $by?->id,
                ]);
            }

            // Keep the FIRST estimate on file; a second correction must not
            // overwrite what the tenant was originally shown.
            if ($bill->estimated_amount === null) {
                $bill->estimated_amount = $originalAmount;
            }
            if ($actualKwh !== null && $bill->estimated_kwh === null) {
                $bill->estimated_kwh = $originalKwh;
            }

            // Rewrite the bill itself to the actual figures.
            $bill->amount = $actual;

            if ($actualKwh !== null) {
                $bill->kwh_reading = $actualKwh;
                $bill->notes = $this->rewriteElectricityNote($bill, $actualKwh, $actual, $originalKwh, $originalAmount);
            }

            $bill->is_estimated  = false;
            $bill->reconciled_at = Carbon::now();

            // Persist BEFORE refreshing. refresh() reloads every attribute
            // from the database, so unsaved changes would be thrown away.
            $bill->save();

            $bill->refresh()->syncPaidTotals()->save();

            // Reconciling downward can leave the bill overpaid. Push the excess
            // out as credit rather than leaving money stranded on a settled bill.
            $this->releaseOverpaymentOn($bill);

            return $adjustment;
        });
    }

    /* ------------------------------------------------------------------
     |  Electricity helpers (shared by issue, reconcile and the bill page)
     * ------------------------------------------------------------------ */

    /** PHP per kWh for this bill — the rate it was issued at, else config. */
    public function electricityRateFor(UtilityBillingProof $bill): float
    {
        return (float) ($bill->kwh_rate ?: config('billing.electricity.rate_per_kwh', 18));
    }

    /**
     * How many ways a Bed-Spacer room's electricity is split for this bill.
     *
     * Taken from the bill itself (room total / this share) so a final reading
     * is split the SAME way the estimate was, even if someone moved in or out
     * since. Falls back to the room's current active leases.
     */
    public function electricityDivisorFor(UtilityBillingProof $bill): int
    {
        $bill->loadMissing('lease.unit');

        if (optional(optional($bill->lease)->unit)->type !== 'Bed-Spacer') {
            return 1;
        }

        $kwh    = (float) ($bill->estimated_kwh ?? $bill->kwh_reading ?? 0);
        $amount = (float) ($bill->estimated_amount ?? $bill->amount ?? 0);
        $rate   = $this->electricityRateFor($bill);

        if ($kwh > 0 && $amount > 0 && $rate > 0) {
            return max(1, (int) round(($kwh * $rate) / $amount));
        }

        return max(1, Lease::where('unit_id', $bill->lease->unit_id)
            ->whereIn('lea_status', ['active', 'Active'])
            ->count());
    }

    /** Peso amount for a meter reading on this bill (rate x kWh, split for Bed-Spacers). */
    public function electricityAmountFor(UtilityBillingProof $bill, float $kwh): float
    {
        $roomTotal = round($kwh * $this->electricityRateFor($bill), $this->precision());

        return round($roomTotal / $this->electricityDivisorFor($bill), $this->precision());
    }

    /** Swap the "Electricity: ..." line on the bill for the final reading. */
    private function rewriteElectricityNote(
        UtilityBillingProof $bill,
        float $kwh,
        float $amount,
        ?float $estimatedKwh,
        float $estimatedAmount
    ): string {
        $rate      = $this->electricityRateFor($bill);
        $divisor   = $this->electricityDivisorFor($bill);
        $roomTotal = round($kwh * $rate, $this->precision());

        $line = 'Electricity (final): ' . rtrim(rtrim(number_format($kwh, 2, '.', ''), '0'), '.')
            . ' kWh x PHP ' . number_format($rate, 2)
            . ' = PHP ' . number_format($roomTotal, 2)
            . ($divisor > 1
                ? ' for the room, split ' . $divisor . ' way(s) => PHP ' . number_format($amount, 2) . ' for this tenant.'
                : '.')
            . ' Estimate was '
            . ($estimatedKwh !== null ? number_format($estimatedKwh, 2) . ' kWh / ' : '')
            . 'PHP ' . number_format($estimatedAmount, 2) . '.';

        $notes = (string) $bill->notes;
        $pattern = '/^Electricity(?: \(final\))?: .*$/m';

        if (preg_match($pattern, $notes)) {
            return trim(preg_replace($pattern, $line, $notes, 1));
        }

        return trim($notes . "\n" . $line);
    }

    private function defaultReconcileReason(UtilityBillingProof $bill, ?float $actualKwh, $originalKwh): string
    {
        if ($actualKwh !== null) {
            return sprintf(
                'Reconciled against the actual meter reading for %s: %s kWh (estimated %s kWh).',
                $bill->billing_month,
                number_format($actualKwh, 2),
                $originalKwh !== null ? number_format((float) $originalKwh, 2) : 'n/a'
            );
        }

        return sprintf(
            'Reconciled against the actual %s bill for %s.',
            strtolower((string) $bill->billing_type),
            $bill->billing_month
        );
    }

    /**
     * A bill whose live allocations now exceed what is owed (usually after a
     * downward reconciliation). Reverse the surplus and hand it back as credit.
     */
    private function releaseOverpaymentOn(UtilityBillingProof $bill): void
    {
        $bill->refresh();

        $surplus = round((float) $bill->amount_paid - $bill->totalDue(), $this->precision());

        if ($surplus < 0.01) {
            return;
        }

        $remaining = $surplus;

        // Give back the most recent money first — the oldest allocation is the
        // one most likely to have already been reported as settled.
        $allocations = $bill->allocations()
            ->whereNull('reversed_at')
            ->orderByDesc('id')
            ->get();

        foreach ($allocations as $allocation) {
            if ($remaining < 0.01) {
                break;
            }

            $take = min((float) $allocation->amount, $remaining);

            if (round($take, 2) >= round((float) $allocation->amount, 2)) {
                $allocation->update([
                    'reversed_at'     => Carbon::now(),
                    'reversal_reason' => 'Released after bill reconciliation',
                ]);
            } else {
                $allocation->update(['amount' => round((float) $allocation->amount - $take, 2)]);
            }

            $remaining = round($remaining - $take, 2);
        }

        $bill->refresh()->syncPaidTotals()->save();

        if ($tenant = $bill->tenant) {
            $this->grantCredit($tenant, $surplus, 'Refund of overpaid ' . strtolower((string) $bill->billing_type) . ' bill');
        }
    }

    /* ==================================================================
     |  3b. VOIDING A BILL — and everything that hangs off it
     * ================================================================== */

    /**
     * Void a bill and bring everything tied to it into line.
     *
     *   - the bill:        status void, voided_at, reason
     *   - the lease:       utility_balance re-synced from the remaining bills
     *   - the tenant:      notified the charge is cancelled
     *   - a Repair bill:   the maintenance request is un-charged, so its costs
     *                      unlock and it can be corrected and re-billed
     *   - a shared bill:   with $wholeBatch, every unpaid share is voided too
     *   - demand notices:  any open notice on the lease that was issued after
     *                      this bill gets an internal note — the amount it
     *                      claimed included a charge that no longer exists
     *   - pending payments the tenant already sent for this type are counted
     *     so the manager is warned before accepting them
     *
     * Nothing paid can be voided — money on a bill has to be reversed first,
     * or the bill credited down instead.
     *
     * @return array{voided:int[], skipped_paid:int[], maintenance_released:?int,
     *               demands_flagged:int, pending_payments:int}
     */
    public function voidBill(
        UtilityBillingProof $bill,
        string $reason,
        ?User $by = null,
        bool $wholeBatch = false
    ): array {
        $result = [
            'voided'               => [],
            'skipped_paid'         => [],
            'maintenance_released' => null,
            'demands_flagged'      => 0,
            'pending_payments'     => 0,
        ];

        $leasesToSync = [];

        DB::transaction(function () use ($bill, $reason, $by, $wholeBatch, &$result, &$leasesToSync) {

            $bill = UtilityBillingProof::with('lease')->whereKey($bill->getKey())->lockForUpdate()->firstOrFail();

            if ($bill->isVoid()) {
                throw new InvalidArgumentException('That bill is already void.');
            }

            if ($this->livePaidOn($bill) > 0) {
                throw new InvalidArgumentException(
                    'That bill has PHP ' . number_format($this->livePaidOn($bill), 2)
                    . ' paid against it. Reverse the payment first, or post a credit correction instead.'
                );
            }

            $targets = collect([$bill]);

            if ($wholeBatch && $bill->batch_id) {
                $siblings = UtilityBillingProof::with('lease')
                    ->where('batch_id', $bill->batch_id)
                    ->where('id', '!=', $bill->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($siblings as $sibling) {
                    if ($sibling->isVoid()) {
                        continue;
                    }
                    if ($this->livePaidOn($sibling) > 0) {
                        $result['skipped_paid'][] = $sibling->id;
                        continue;
                    }
                    $targets->push($sibling);
                }
            }

            foreach ($targets as $target) {
                $isBatchSibling = $target->id !== $bill->id;

                $target->update([
                    'status'      => UtilityBillingProof::STATUS_VOID,
                    'voided_at'   => Carbon::now(),
                    'void_reason' => $isBatchSibling
                        ? $reason . ' (shared bill voided from bill #' . $bill->id . ')'
                        : $reason,
                ]);

                $result['voided'][] = $target->id;

                if ($target->lease) {
                    $leasesToSync[$target->lease->id] = $target->lease;
                }

                // Repair bill -> un-charge the request it came from.
                if ($request = $this->maintenanceRequestFor($target)) {
                    app(MaintenanceCostService::class)->releaseCharge($request, $target, $reason, $by);
                    $result['maintenance_released'] = $request->id;
                }

                $result['demands_flagged'] += $this->flagDemandsAfterVoid($target, $reason);

                $this->notifyTenantOfVoid($target, $reason);
            }

            if ($bill->lease) {
                $result['pending_payments'] = Payment::where('lease_id', $bill->lease->id)
                    ->where('payment_for', 'Utilities')
                    ->where('utility_type', $bill->billing_type)
                    ->whereIn('pay_status', ['Pending', 'pending'])
                    ->count();
            }
        });

        foreach ($leasesToSync as $lease) {
            $this->syncLeaseUtilityBalance($lease);
        }

        return $result;
    }

    /** Money actually on a bill, from live allocations (not the cached column). */
    private function livePaidOn(UtilityBillingProof $bill): float
    {
        return round(max(
            (float) $bill->amount_paid,
            (float) $bill->allocations()->whereNull('reversed_at')->sum('amount')
        ), 2);
    }

    /** The maintenance request a Repair bill charges for, if any. */
    private function maintenanceRequestFor(UtilityBillingProof $bill): ?MaintenanceRequest
    {
        if ($bill->billing_type !== 'Repair') {
            return null;
        }

        if ($bill->maintenance_request_id) {
            return MaintenanceRequest::find($bill->maintenance_request_id);
        }

        // Bills charged before v20 only carry the link in their note.
        if (preg_match('/Maintenance request #(\d+)/', (string) $bill->notes, $m)) {
            $request = MaintenanceRequest::find((int) $m[1]);

            if ($request && (int) $request->lease_id === (int) $bill->lease_id) {
                return $request;
            }
        }

        return null;
    }

    /**
     * Open demand notices that were issued while this bill was on the books
     * claimed it as part of the arrears. Nothing is rewritten — the notice is
     * what was served — but an internal note makes sure nobody escalates on a
     * figure that now includes a cancelled charge.
     */
    private function flagDemandsAfterVoid(UtilityBillingProof $bill, string $reason): int
    {
        if (! $bill->lease_id) {
            return 0;
        }

        $demands = PaymentDemand::where('lease_id', $bill->lease_id)
            ->where('outcome', PaymentDemand::OUTCOME_OPEN)
            ->where('utility_component', '>', 0)
            ->where('issued_at', '>=', $bill->created_at)
            ->get();

        foreach ($demands as $demand) {
            $line = '[' . Carbon::now()->format('M d, Y') . '] Bill #' . $bill->id . ' ('
                . $bill->billing_type . ' ' . $bill->billing_month . ', PHP '
                . number_format((float) $bill->amount, 2) . ') was VOIDED: ' . $reason
                . '. The utilities claimed on this notice may include it — recheck the arrears before escalating.';

            $demand->update(['notes' => trim(($demand->notes ? $demand->notes . "\n" : '') . $line)]);
        }

        return $demands->count();
    }

    private function notifyTenantOfVoid(UtilityBillingProof $bill, string $reason): void
    {
        if (! $bill->tenant_id) {
            return;
        }

        Notification::create([
            'user_id' => $bill->tenant_id,
            'title'   => 'Bill Cancelled',
            'message' => 'Your ' . strtolower((string) $bill->billing_type) . ' bill for ' . $bill->billing_month
                . ' (PHP ' . number_format((float) $bill->amount, 2) . ') has been cancelled. Reason: ' . $reason
                . '. You do not need to pay it.',
        ]);
    }

    /* ==================================================================
     |  4. APPLYING A PAYMENT
     * ================================================================== */

    /**
     * Apply an accepted payment to whatever it was for.
     *
     * Utilities are allocated across open bills; rent, deposit and penalties
     * come off the lease balances. Anything left after everything is settled
     * becomes credit (or is flagged for refund) per config('billing.overpayment').
     *
     * Safe to call twice — a payment that already has live allocations, or one
     * that is not Accepted, is skipped. Manager screens can double-submit.
     *
     * @return array{allocated:float, credited:float, refundable:float, bills:array<int,float>}
     */
    public function applyAcceptedPayment(Payment $payment): array
    {
        if ($payment->pay_status !== 'Accepted') {
            return $this->emptyResult();
        }

        return DB::transaction(function () use ($payment) {

            $payment->refresh();

            $alreadyApplied = BillPaymentAllocation::where('payment_id', $payment->id)
                ->whereNull('reversed_at')
                ->exists();

            if ($alreadyApplied) {
                return $this->emptyResult();
            }

            $lease  = $payment->lease;
            $tenant = $payment->tenant;
            $amount = round((float) $payment->pay_amount, $this->precision());

            if ($amount <= 0 || !$tenant) {
                return $this->emptyResult();
            }

            $result = match ($payment->payment_for) {
                'Utilities'    => $this->applyToUtilityBills($payment, $lease, $amount),
                'Rent'         => $this->applyToRent($lease, $amount),
                'Advance Rent' => $this->applyToAdvanceRent($payment, $lease, $amount),
                'Deposit'      => $this->applyToDeposit($lease, $amount),
                default        => ['allocated' => 0.0, 'leftover' => $amount, 'bills' => []],
            };

            $leftover = round($result['leftover'], $this->precision());
            $credited = 0.0;
            $refund   = 0.0;

            if ($leftover >= 0.01) {
                if (config('billing.overpayment', 'credit') === 'credit') {
                    $this->grantCredit($tenant, $leftover, 'Overpayment on ' . $payment->reference_number);
                    $credited = $leftover;
                } else {
                    $refund = $leftover;
                    Log::info("Payment {$payment->reference_number} overpaid by {$leftover}; flagged for refund.");
                }
            }

            if ($lease) {
                $lease->save();
            }

            return [
                'allocated'  => $result['allocated'],
                'credited'   => $credited,
                'refundable' => $refund,
                'bills'      => $result['bills'],
            ];
        });
    }

    /**
     * Spread a utility payment across open bills, oldest due date first.
     *
     * When the payment names a utility type (Electricity/Water) only bills of
     * that type are touched — a tenant paying their water bill should not have
     * it swallowed by an older electricity arrear they were not told about.
     */
    private function applyToUtilityBills(Payment $payment, ?Lease $lease, float $amount): array
    {
        if (!$lease) {
            return ['allocated' => 0.0, 'leftover' => $amount, 'bills' => []];
        }

        // payable(), not open(): an estimate the manager has not yet set the
        // final amount on cannot take money (v19). Anything that would have
        // landed on it falls through to the overpayment rule instead.
        $query = UtilityBillingProof::forLease($lease->id)->payable();

        if ($payment->utility_type) {
            $query->where('billing_type', $payment->utility_type);
        }

        $bills = $query->oldestFirst()->lockForUpdate()->get();

        $remaining = $amount;
        $allocated = 0.0;
        $touched   = [];

        foreach ($bills as $bill) {
            if ($remaining < 0.01) {
                break;
            }

            $owed = $bill->outstanding();

            if ($owed < 0.01) {
                continue;
            }

            $take = round(min($owed, $remaining), $this->precision());

            BillPaymentAllocation::create([
                'payment_id' => $payment->id,
                'bill_id'    => $bill->id,
                'lease_id'   => $lease->id,
                'amount'     => $take,
            ]);

            $bill->refresh()->syncPaidTotals()->save();

            $touched[$bill->id] = $take;
            $remaining = round($remaining - $take, $this->precision());
            $allocated = round($allocated + $take, $this->precision());
        }

        // Keep the lease's rolled-up figure in step with the bills.
        $this->syncLeaseUtilityBalance($lease);

        return ['allocated' => $allocated, 'leftover' => $remaining, 'bills' => $touched];
    }

    /**
     * Rent, then any penalty. The due date only rolls forward once the rent
     * balance actually reaches zero — a partial payment buys time, not a month.
     */
    private function applyToRent(?Lease $lease, float $amount): array
    {
        if (!$lease) {
            return ['allocated' => 0.0, 'leftover' => $amount, 'bills' => []];
        }

        $remaining = $amount;
        $allocated = 0.0;

        $penalty = round((float) ($lease->penalty_fee ?? 0), $this->precision());
        if ($penalty > 0) {
            $take = round(min($penalty, $remaining), $this->precision());
            $lease->penalty_fee = round($penalty - $take, $this->precision());
            $remaining = round($remaining - $take, $this->precision());
            $allocated = round($allocated + $take, $this->precision());
        }

        $rent = round((float) ($lease->rent_balance ?? 0), $this->precision());
        if ($rent > 0 && $remaining >= 0.01) {
            $take = round(min($rent, $remaining), $this->precision());
            $lease->rent_balance = round($rent - $take, $this->precision());
            $remaining = round($remaining - $take, $this->precision());
            $allocated = round($allocated + $take, $this->precision());
        }

        $lease->paid_date = Carbon::today();

        // The due date is recomputed on EVERY rent payment, not only when the
        // balance reaches zero.
        //
        // Only-at-zero was wrong in both directions. A tenant two months
        // behind who cleared the older month kept a due date pointing at a
        // month they had just paid, so they still looked overdue; and a tenant
        // three months behind who cleared the lot had their date moved forward
        // by exactly ONE month, so they looked overdue too. Both come from
        // treating the due date as a counter to bump rather than as a fact
        // about which month is currently owed.
        //
        // refreshRentDueDate() is a no-op on a legacy lease with no charge
        // rows behind its balance, so the old behaviour is kept as the
        // fallback there.
        if ($lease->rentCharges()->count() > 0) {
            $this->refreshRentDueDate($lease);
        } elseif ((float) $lease->rent_balance <= 0 && (float) $lease->penalty_fee <= 0 && $lease->due_date) {
            $lease->due_date = Carbon::parse($lease->due_date)->addMonth();
        }

        return ['allocated' => $allocated, 'leftover' => $remaining, 'bills' => []];
    }

    /**
     * Rent paid before the month it covers.
     *
     * Order of operations, and why:
     *
     *   1. Arrears and penalties first. Buying April while March is unpaid is
     *      not a thing a tenant would knowingly do — they would still be
     *      behind, still accruing the 10% penalty, having just handed over the
     *      money. Config: billing.advance_rent.settle_arrears_first.
     *   2. Whatever survives that goes onto lease.advance_rent_balance, which
     *      is ring-fenced to rent. It does NOT become user_credit, because
     *      credit gets spent on whichever bill comes next — including
     *      electricity — and prepaid rent that pays a water bill leaves the
     *      tenant in rent arrears through no fault of their own.
     *   3. due_date and rent_paid_through move forward by the number of WHOLE
     *      months the balance now covers. A part month buys no time; it sits
     *      on the balance until it is topped up or drawn down.
     *
     * The split between (1) and (2) is written back onto the payment row so
     * the reversal can undo exactly what was done, even if the rent has
     * changed at a renewal since.
     *
     * `leftover` is always 0: nothing is ever left over on an advance, because
     * everything not spent on arrears is held. That is what stops the
     * overpayment path in applyAcceptedPayment() from turning prepaid rent
     * into general credit.
     */
    private function applyToAdvanceRent(Payment $payment, ?Lease $lease, float $amount): array
    {
        if (!$lease) {
            return ['allocated' => 0.0, 'leftover' => $amount, 'bills' => []];
        }

        $precision = $this->precision();
        $remaining = round($amount, $precision);
        $allocated = 0.0;

        // --- 1. Clear what is already owed ------------------------------
        if (config('billing.advance_rent.settle_arrears_first', true)) {
            $penalty = round((float) ($lease->penalty_fee ?? 0), $precision);

            if ($penalty > 0) {
                $take = round(min($penalty, $remaining), $precision);
                $lease->penalty_fee = round($penalty - $take, $precision);
                $remaining = round($remaining - $take, $precision);
                $allocated = round($allocated + $take, $precision);
            }

            $rent = round((float) ($lease->rent_balance ?? 0), $precision);

            if ($rent > 0 && $remaining >= 0.01) {
                $take = round(min($rent, $remaining), $precision);
                $lease->rent_balance = round($rent - $take, $precision);
                $remaining = round($remaining - $take, $precision);
                $allocated = round($allocated + $take, $precision);
            }

            $lease->paid_date = Carbon::now();
        }

        // --- 2. Hold the rest against future rent ------------------------
        $heldOnAccount = $remaining;

        $lease->advance_rent_balance = round(
            (float) ($lease->advance_rent_balance ?? 0) + $heldOnAccount,
            $precision
        );

        // --- 3. Convert the balance into time ----------------------------
        $monthlyRent = $lease->currentRent();
        $monthsBought = 0;

        if ($monthlyRent > 0) {
            $balance      = round((float) $lease->advance_rent_balance, $precision);
            $monthsBefore = $this->monthsCoveredBefore($lease, $heldOnAccount, $monthlyRent, $precision);
            $monthsAfter  = (int) floor($balance / $monthlyRent);
            $monthsBought = max(0, $monthsAfter - $monthsBefore);
        }

        if ($monthsBought > 0) {
            // The clock starts from whichever is later: the date rent is next
            // due, or the date already paid through. Starting from today would
            // give away time the tenant has already bought.
            $anchor = $lease->rent_paid_through
                ? Carbon::parse($lease->rent_paid_through)
                : ($lease->due_date ? Carbon::parse($lease->due_date) : Carbon::today());

            if ($lease->due_date && Carbon::parse($lease->due_date)->gt($anchor)) {
                $anchor = Carbon::parse($lease->due_date);
            }

            $lease->rent_paid_through = $anchor->copy()->addMonths($monthsBought);

            // Nothing is owed until the advance runs out, so the due date moves
            // with it. Arrears cleared in step 1 are already gone by here, so
            // this cannot push a genuinely overdue lease into the future.
            if ($lease->due_date
                && (float) ($lease->rent_balance ?? 0) <= 0
                && (float) ($lease->penalty_fee ?? 0) <= 0) {
                $lease->due_date = Carbon::parse($lease->due_date)->addMonths($monthsBought);
            }
        }

        // Record what this payment actually bought, for the reversal.
        $payment->forceFill([
            'advance_months' => $monthsBought,
            'advance_amount' => round($heldOnAccount, $precision),
        ])->save();

        return [
            'allocated' => round($allocated + $heldOnAccount, $precision),
            'leftover'  => 0.0,
            'bills'     => [],
        ];
    }

    /**
     * Whole months the lease covered BEFORE this payment landed.
     *
     * Needed because months are granted on the rounded-down total, not on each
     * payment separately: two half-month advances should buy one month between
     * them, not zero.
     */
    private function monthsCoveredBefore(Lease $lease, float $justAdded, float $monthlyRent, int $precision): int
    {
        if ($monthlyRent <= 0) {
            return 0;
        }

        $before = round((float) ($lease->advance_rent_balance ?? 0) - $justAdded, $precision);

        return (int) floor(max(0.0, $before) / $monthlyRent);
    }

    /**
     * Draw a lease's advance balance down against rent that has since fallen
     * due. Thin wrapper over Lease::consumeAdvanceRent() for callers that
     * already hold the service.
     */
    public function consumeAdvanceRent(Lease $lease): float
    {
        $consumed = $lease->consumeAdvanceRent();

        if ($consumed > 0) {
            $lease->save();
        }

        return $consumed;
    }

    /* ==================================================================
     |  4b. RAISING RENT  (added v16)
     |
     |  Rent used to be charged twice in a lease's whole life: once at
     |  approval, and again at each yearly renewal. `rent_balance` was set to
     |  one month at approval and `due_date` rolled forward whenever the
     |  balance hit zero, but nothing ever put the NEXT month on the balance —
     |  so a tenant who paid their first month owed nothing for the following
     |  eleven, and the landlord's own system agreed with them.
     * ================================================================== */

    /**
     * Raise every month of rent that has fallen due on this lease.
     *
     * Safe to call as often as you like. The unique index on
     * (lease_id, period_start) is what actually guarantees that — not the
     * date arithmetic — so an overlapping cron run, a crash between writing
     * the charge and stepping the date, or a double-clicked manual charge
     * cannot bill the same month twice.
     *
     * Advance rent on the lease is consumed as each month is raised, which is
     * the entire point of having paid ahead: the tenant should not have to do
     * anything when the month comes round.
     *
     * @return array{charged:int, total:float, advance_used:float, capped:bool, charges:array<int,RentCharge>}
     */
    public function chargeDueRent(
        Lease $lease,
        ?Carbon $asOf = null,
        string $source = RentCharge::SOURCE_SCHEDULED,
        ?User $by = null
    ): array {

        $asOf   = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $result = ['charged' => 0, 'total' => 0.0, 'advance_used' => 0.0, 'capped' => false, 'charges' => []];

        if (! $lease->isOnRentSchedule()) {
            return $result;
        }

        $rent = round($lease->currentRent(), $this->precision());

        if ($rent <= 0) {
            // A lease with no rent set would otherwise raise an endless run of
            // zero-peso charges every night. Louder than silently doing
            // nothing, because it is a data problem someone has to fix.
            Log::warning("Lease {$lease->id} is on the rent schedule but has no monthly rent. Skipped.");

            return $result;
        }

        $cap  = max(1, (int) config('billing.rent.max_catchup_months', 3));
        $left = $lease->rentChargesOutstanding($asOf);

        if ($left > $cap) {
            // A server whose cron has been dead for months must not quietly
            // bill all of it in one night. Raise the cap's worth, log the
            // rest, and let a person decide.
            Log::warning(
                "Lease {$lease->id} is {$left} rent charges behind; raising {$cap} this run. " .
                'Check that the scheduler is running.'
            );
            $result['capped'] = true;
            $left = $cap;
        }

        for ($i = 0; $i < $left; $i++) {
            $charge = DB::transaction(function () use ($lease, $rent, $source, $by) {

                $lease->refresh();

                if (! $lease->isOnRentSchedule()) {
                    return null;
                }

                // Calendar periods (v19): every period ends on the 1st, and a
                // period that starts mid-month is a prorated part-month.
                $periodStart = Carbon::parse($lease->next_rent_charge_on)->startOfDay();
                $periodEnd   = RentCalendar::periodEnd($periodStart);
                $amount      = RentCalendar::amountFor($periodStart, $rent, $this->precision());

                // Belt and braces alongside the unique index: skip cleanly
                // rather than letting an integrity violation escape.
                $exists = RentCharge::forLease($lease->id)
                    ->whereDate('period_start', $periodStart->toDateString())
                    ->exists();

                if ($exists) {
                    $lease->next_rent_charge_on = $periodEnd;
                    $lease->save();

                    return null;
                }

                $charge = RentCharge::create([
                    'lease_id'     => $lease->id,
                    'tenant_id'    => $lease->user_id,
                    'room_no'      => $lease->room_no,
                    'period_start' => $periodStart,
                    'period_end'   => $periodEnd,
                    'due_date'     => RentCalendar::rentDueDate($periodStart),
                    'amount'       => $amount,
                    'source'       => $source,
                    'charged_by'   => $by?->id,
                    'notes'        => $this->partialPeriodNote($periodStart, $periodEnd, $rent, $amount),
                ]);

                $lease->rent_balance = round((float) ($lease->rent_balance ?? 0) + $amount, $this->precision());

                // Money already paid ahead meets the month automatically.
                $used = $lease->consumeAdvanceRent();

                if ($used > 0) {
                    $charge->advance_applied = $used;
                    $charge->save();
                }

                $lease->next_rent_charge_on = $periodEnd;

                $this->refreshRentDueDate($lease);

                $lease->save();

                return $charge;
            });

            if (! $charge) {
                continue;
            }

            $result['charged']++;
            $result['total']        = round($result['total'] + (float) $charge->amount, $this->precision());
            $result['advance_used'] = round($result['advance_used'] + (float) $charge->advance_applied, $this->precision());
            $result['charges'][]    = $charge;
        }

        return $result;
    }

    /**
     * Put a newly activated lease on the monthly schedule and raise its first
     * month.
     *
     * Called from lease approval so that the first month is a charge with a
     * row behind it, exactly like every month after it, rather than a bare
     * number assigned to `rent_balance` with nothing to explain it.
     *
     * THIS adds the first month to `rent_balance` — the caller must not also
     * set it, or the tenant is billed twice on day one. The charge row is what
     * puts money on the balance, here and in chargeDueRent(), so there is one
     * rule rather than an implicit contract between two files.
     */
    public function openRentSchedule(Lease $lease, Carbon $start, ?User $by = null): ?RentCharge
    {
        $rent = round($lease->currentRent(), $this->precision());

        if ($rent <= 0) {
            return null;
        }

        // Calendar periods (v19). Moving in on the 1st is a whole month; moving
        // in on the 17th is the 17th to the 1st, prorated, and due on the
        // next payment day that gives proper notice.
        $periodStart = $start->copy()->startOfDay();
        $periodEnd   = RentCalendar::periodEnd($periodStart);
        $amount      = RentCalendar::amountFor($periodStart, $rent, $this->precision());

        $charge = RentCharge::firstOrCreate(
            ['lease_id' => $lease->id, 'period_start' => $periodStart->toDateString()],
            [
                'tenant_id'  => $lease->user_id,
                'room_no'    => $lease->room_no,
                'period_end' => $periodEnd,
                'due_date'   => RentCalendar::rentDueDate($periodStart),
                'amount'     => $amount,
                'source'     => RentCharge::SOURCE_APPROVAL,
                'charged_by' => $by?->id,
                'notes'      => $this->partialPeriodNote($periodStart, $periodEnd, $rent, $amount),
            ]
        );

        // firstOrCreate: re-running approval must not bill a second month.
        if ($charge->wasRecentlyCreated) {
            $lease->rent_balance = round((float) ($lease->rent_balance ?? 0) + $amount, $this->precision());

            $used = $lease->consumeAdvanceRent();

            if ($used > 0) {
                $charge->advance_applied = $used;
                $charge->save();
            }
        }

        $lease->next_rent_charge_on = $periodEnd;
        $lease->due_date            = $charge->due_date;
        $lease->save();

        return $charge;
    }

    /**
     * Point `due_date` at the oldest month that is not yet paid for, or — when
     * everything is settled — at the upcoming charge's due date.
     *
     * Does NOT save; the caller does, so this can be folded into a larger
     * write without a second round trip.
     *
     * `due_date` predates rent_charges and is read all over the app, the
     * reports and the collections screens, so it is kept as a derived
     * convenience rather than ripped out.
     */
    public function refreshRentDueDate(Lease $lease): void
    {
        // A lease backfilled by the migration has a balance but no charge
        // rows behind it. Deriving a due date from an empty ledger would push
        // the date forward and quietly hide real arrears, so leave it be until
        // this lease has a charge history of its own.
        if ($lease->rentCharges()->count() === 0) {
            return;
        }

        $oldest = $lease->oldestUnsettledRentCharge();

        if ($oldest) {
            $lease->due_date = $oldest->due_date;

            return;
        }

        if ($lease->next_rent_charge_on) {
            $lease->due_date = RentCalendar::rentDueDate(Carbon::parse($lease->next_rent_charge_on));
        }
    }

    /**
     * Apply the late-payment penalty to a lease whose rent is overdue.
     *
     * This used to live inside Tenant\PaymentController@index — that is, a GET
     * request. A tenant who never opened their payments page was never
     * penalised; one who did was, on page load. Whether a charge exists should
     * not depend on whether somebody looked at a screen.
     *
     * One missed due date, one penalty: `last_penalty_due_date` records which
     * cycle was charged, so the nightly run cannot penalise the same overdue
     * month again tomorrow.
     *
     * @return float the penalty applied, 0.0 if none
     */
    public function assessLatePenalty(Lease $lease, ?Carbon $asOf = null): float
    {
        if (! config('billing.rent.penalty.enabled', true)) {
            return 0.0;
        }

        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();

        $balance = round((float) ($lease->rent_balance ?? 0), $this->precision());

        if ($balance < 0.01 || ! $lease->due_date) {
            return 0.0;
        }

        $grace    = max(0, (int) config('billing.rent.penalty.grace_days', 0));
        $overdueOn = Carbon::parse($lease->due_date)->startOfDay()->addDays($grace);

        if ($asOf->lte($overdueOn)) {
            return 0.0;
        }

        $cycle = Carbon::parse($lease->due_date)->toDateString();

        if (config('billing.rent.penalty.once_per_cycle', true)
            && $lease->last_penalty_due_date
            && Carbon::parse($lease->last_penalty_due_date)->toDateString() === $cycle) {
            return 0.0;
        }

        $rate    = (float) config('billing.rent.penalty.rate', 0.10);
        $penalty = round($balance * $rate, $this->precision());

        if ($penalty < 0.01) {
            return 0.0;
        }

        $lease->penalty_fee           = round((float) ($lease->penalty_fee ?? 0) + $penalty, $this->precision());
        $lease->last_penalty_due_date = $lease->due_date;
        $lease->save();

        return $penalty;
    }

    private function applyToDeposit(?Lease $lease, float $amount): array
    {
        if (!$lease) {
            return ['allocated' => 0.0, 'leftover' => $amount, 'bills' => []];
        }

        $balance = round((float) ($lease->deposit_balance ?? 0), $this->precision());
        $take    = round(min($balance, $amount), $this->precision());

        $lease->deposit_balance = round($balance - $take, $this->precision());

        return [
            'allocated' => $take,
            'leftover'  => round($amount - $take, $this->precision()),
            'bills'     => [],
        ];
    }

    /**
     * Undo an applied payment — the manager un-accepted it or corrected the
     * amount. Allocation rows are reversed, not deleted, so the history still
     * shows the money going in and coming back out.
     */
    public function reverseAcceptedPayment(Payment $payment, string $reason = 'Payment status changed'): void
    {
        DB::transaction(function () use ($payment, $reason) {

            $allocations = BillPaymentAllocation::where('payment_id', $payment->id)
                ->whereNull('reversed_at')
                ->get();

            foreach ($allocations as $allocation) {
                $allocation->update([
                    'reversed_at'     => Carbon::now(),
                    'reversal_reason' => $reason,
                ]);

                if ($bill = $allocation->bill) {
                    $bill->refresh()->syncPaidTotals()->save();
                }
            }

            $lease = $payment->lease;

            if ($lease) {
                // Non-utility payments were applied to lease balances rather
                // than to bills, so put those back directly.
                $amount = round((float) $payment->pay_amount, $this->precision());

                if ($payment->payment_for === 'Rent') {
                    $lease->rent_balance = round((float) ($lease->rent_balance ?? 0) + $amount, $this->precision());

                    // The money is owed again, so the due date has to point
                    // back at the month it belongs to.
                    if ($lease->rentCharges()->count() > 0) {
                        $this->refreshRentDueDate($lease);
                    }
                } elseif ($payment->payment_for === 'Deposit') {
                    $lease->deposit_balance = round((float) ($lease->deposit_balance ?? 0) + $amount, $this->precision());
                } elseif ($payment->payment_for === 'Advance Rent') {
                    // An advance did two things, so undoing it has to undo
                    // both — and in the proportions recorded at the time, not
                    // recomputed now. The rent may have gone up at a renewal
                    // since, so "amount / current rent" would give back a
                    // different number of months than were granted.
                    $held    = round((float) ($payment->advance_amount ?? 0), $this->precision());
                    $arrears = round($amount - $held, $this->precision());
                    $months  = (int) ($payment->advance_months ?? 0);

                    if ($arrears >= 0.01) {
                        $lease->rent_balance = round(
                            (float) ($lease->rent_balance ?? 0) + $arrears,
                            $this->precision()
                        );
                    }

                    // Floored at zero: if the advance has since been eaten by a
                    // renewal, the money is gone and the balance cannot go
                    // negative. What the tenant is owed in that case is a
                    // refund conversation, not a negative column.
                    $lease->advance_rent_balance = max(0.0, round(
                        (float) ($lease->advance_rent_balance ?? 0) - $held,
                        $this->precision()
                    ));

                    if ($months > 0) {
                        if ($lease->rent_paid_through) {
                            $rolledBack = Carbon::parse($lease->rent_paid_through)->subMonths($months);
                            $lease->rent_paid_through = $rolledBack->isPast() && !$lease->hasAdvanceRent()
                                ? null
                                : $rolledBack;
                        }

                        if ($lease->due_date) {
                            $lease->due_date = Carbon::parse($lease->due_date)->subMonths($months);
                        }
                    }

                    $payment->forceFill(['advance_months' => 0, 'advance_amount' => 0])->save();
                }

                $lease->save();

                $this->syncLeaseUtilityBalance($lease);
            }
        });
    }

    /* ==================================================================
     |  5. BALANCES & ARREARS
     * ================================================================== */

    /**
     * Recompute lease.utility_balance from the open bills.
     *
     * lease.utility_balance predates bills and is still read all over the app
     * and the reports. Rather than rip it out, it is kept as a cache of the
     * bills, refreshed whenever they move.
     */
    public function syncLeaseUtilityBalance(Lease $lease): float
    {
        // What can actually be collected. An unfinalised estimate is shown to
        // the tenant but is not owed yet, so it stays out of the balance.
        $open = UtilityBillingProof::forLease($lease->id)->payable()->get();

        $total = 0.0;
        foreach ($open as $bill) {
            $total = round($total + $bill->outstanding(), $this->precision());
        }

        // A targeted update rather than $lease->save().
        //
        // The Lease instance handed in can be stale: a different instance may
        // have written utility_balance since this one was loaded (that is
        // exactly what happens between applyAcceptedPayment() and a later
        // settlement). Eloquent only persists attributes it considers DIRTY,
        // so assigning the same value the stale copy already holds saves
        // nothing at all and leaves the real row untouched — a move-out could
        // close every bill and still leave the lease showing a balance.
        //
        // Refreshing the instance first is not an option: callers hold unsaved
        // changes on this same object (applyToRent writes rent_balance and
        // relies on applyAcceptedPayment saving it afterwards), and refresh()
        // would discard them.
        Lease::whereKey($lease->getKey())->update(['utility_balance' => $total]);

        $lease->setAttribute('utility_balance', $total);
        $lease->syncOriginalAttribute('utility_balance');

        return $total;
    }

    /**
     * Everything past its due date and still open — the balance that carries
     * forward into the next cycle.
     *
     * @return array{utilities:float, rent:float, penalty:float, total:float, oldest_due:?Carbon}
     */
    public function arrearsFor(Lease $lease): array
    {
        $bills = UtilityBillingProof::forLease($lease->id)->arrears()->oldestFirst()->get();

        $utilities = 0.0;
        foreach ($bills as $bill) {
            $utilities = round($utilities + $bill->outstanding(), $this->precision());
        }

        $rent    = 0.0;
        $penalty = round((float) ($lease->penalty_fee ?? 0), $this->precision());
        $oldest  = $bills->first()?->due_date ? Carbon::parse($bills->first()->due_date) : null;

        // Rent counts as arrears only once its due date has passed.
        if ($lease->due_date && Carbon::parse($lease->due_date)->endOfDay()->isPast()) {
            $rent = round((float) ($lease->rent_balance ?? 0), $this->precision());

            $rentDue = Carbon::parse($lease->due_date);
            if (!$oldest || $rentDue->lt($oldest)) {
                $oldest = $rentDue;
            }
        }

        return [
            'utilities'  => $utilities,
            'rent'       => $rent,
            'penalty'    => $penalty,
            'total'      => round($utilities + $rent + $penalty, $this->precision()),
            'oldest_due' => $oldest,
        ];
    }

    /* ==================================================================
     |  6. MOVE-OUT SETTLEMENT
     * ================================================================== */

    /**
     * Close out every open bill on a lease that is being moved out.
     *
     * Force move-out and move-out approval both deduct the lease's unpaid
     * rent, penalty and utility total from the deposit refund (or record
     * it as a shortfall still owed) — see ManagerController@forceMoveOut
     * and @moveOut. That deduction is a monetary decision made against the
     * lease's totals; on its own it does nothing to the individual bill
     * rows, which would otherwise sit at 'unpaid'/'partial' forever even
     * though the money was just collected out of the deposit.
     *
     * This closes them the same way a real payment would: a Payment row
     * records what happened, a BillPaymentAllocation is written per bill,
     * and each bill is synced to 'paid' from its live allocations — so the
     * "amount_paid is a cache of the allocations" invariant this file
     * exists to protect never breaks, even for a deposit-deduction
     * settlement instead of a cash payment.
     *
     * lease.rent_balance and lease.penalty_fee are NOT bills — the caller
     * is responsible for zeroing those directly on the lease itself.
     *
     * Safe to call on a lease with nothing open — it simply closes zero
     * bills.
     *
     * @return array{settled_total: float, bills_closed: int}
     */
    public function settleOpenBillsAtMoveOut(Lease $lease): array
    {
        return DB::transaction(function () use ($lease) {

            // Payable bills only — the same set settlementPreview() priced.
            // Move-out refuses to run while an estimate is unfinalised
            // (ManagerController), so nothing is left behind by this.
            $bills = UtilityBillingProof::forLease($lease->id)->payable()->lockForUpdate()->get();

            $settledTotal = 0.0;
            $closed       = 0;

            foreach ($bills as $bill) {
                $owed = $bill->outstanding();

                if ($owed < 0.01) {
                    // Nothing actually owed but not marked paid/void for some
                    // other reason — sync its status without a payment row.
                    $bill->refresh()->syncPaidTotals()->save();
                    continue;
                }

                $payment = Payment::create([
                    'tenant_id'        => $lease->user_id,
                    'lease_id'         => $lease->id,
                    'pay_date'         => Carbon::now(),
                    'payment_for'      => 'Utilities',
                    'utility_type'     => $bill->billing_type,
                    'pay_amount'       => $owed,
                    'pay_method'       => 'Move-out Settlement',
                    'reference_number' => 'MOVEOUT-' . $lease->id . '-' . $bill->id,
                    'pay_status'       => 'Accepted',
                    'accepted_at'      => Carbon::now(),
                ]);

                BillPaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'bill_id'    => $bill->id,
                    'lease_id'   => $lease->id,
                    'amount'     => $owed,
                ]);

                $bill->refresh()->syncPaidTotals()->save();

                $settledTotal = round($settledTotal + $owed, $this->precision());
                $closed++;
            }

            // Keep the lease's rolled-up figure in step with the bills.
            $this->syncLeaseUtilityBalance($lease);

            return ['settled_total' => $settledTotal, 'bills_closed' => $closed];
        });
    }

    /* ==================================================================
     |  HELPERS
     * ================================================================== */

    public function grantCredit(User $tenant, float $amount, string $why = ''): void
    {
        if ($amount < 0.01) {
            return;
        }

        $tenant->user_credit = round((float) ($tenant->user_credit ?? 0) + $amount, $this->precision());
        $tenant->save();

        Log::info("Credited {$amount} to tenant {$tenant->id}. {$why}");
    }

    /**
     * A line on a short first period saying how it was worked out, so a
     * tenant who moved in on the 17th can see why the first rent is not a
     * round number. Null for a whole month.
     */
    private function partialPeriodNote(Carbon $start, Carbon $end, float $monthlyRent, float $amount): ?string
    {
        if (RentCalendar::isWholeMonth($start)) {
            return null;
        }

        $days = (int) $start->diffInDays($end);

        return sprintf(
            'Part month %s to %s (%d day%s) — moves this lease onto the %s payment schedule. PHP %s of PHP %s monthly rent.',
            $start->format('M j'),
            $end->copy()->subDay()->format('M j, Y'),
            $days,
            $days === 1 ? '' : 's',
            RentCalendar::label(),
            number_format($amount, 2),
            number_format($monthlyRent, 2)
        );
    }

    private function resolveDueDate($given, ?string $billingMonth = null): Carbon
    {
        if ($given) {
            return Carbon::parse($given)->startOfDay();
        }

        // The payment day (the 10th) of the month after the billing month.
        return RentCalendar::billDueDate($billingMonth);
    }

    private function precision(): int
    {
        return (int) config('billing.currency_precision', 2);
    }

    private function emptyResult(): array
    {
        return ['allocated' => 0.0, 'credited' => 0.0, 'refundable' => 0.0, 'bills' => []];
    }
}