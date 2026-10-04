<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\PaymentDemand;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Works out where a tenant stands in the collections process, and records the
 * demands the landlord has made.
 *
 * WHAT THIS CLASS DOES NOT DO, ON PURPOSE
 *
 * It does not evict anyone, terminate a lease, disconnect a utility, disable a
 * tenant's login, or send anything on its own. It reads balances and demand
 * records and returns a stage plus a recommendation. Every actual step is taken
 * by a person clicking a button, and the last stage it can reach is
 * "refer to counsel" — the app has no next step after that, deliberately.
 *
 * Legal basis for any action lives outside this codebase. What lives here is
 * the evidence that due process was followed: what was demanded, when it was
 * served, how, and whether the notice period had run before anything else
 * happened. See config/collections.php, which needs a lawyer's sign-off before
 * launch.
 */
class CollectionsService
{
    public function __construct(private BillingService $billing)
    {
    }

    /**
     * Where this lease stands.
     *
     * @return array{
     *   stage:string, label:string, arrears:array, days_past_due:int,
     *   latest_demand:?PaymentDemand, next_step:string, blockers:array<int,string>,
     *   actionable:bool
     * }
     */
    public function assess(Lease $lease): array
    {
        $this->guardAgainstSelfHelp();

        $arrears  = $this->billing->arrearsFor($lease);
        $minimum  = (float) config('collections.minimum_actionable_arrears', 0);
        $total    = $arrears['total'];

        $daysPastDue = $arrears['oldest_due']
            ? (int) Carbon::parse($arrears['oldest_due'])->endOfDay()->diffInDays(Carbon::now(), false)
            : 0;
        $daysPastDue = max(0, $daysPastDue);

        $latestDemand = PaymentDemand::where('lease_id', $lease->id)
            ->orderByDesc('issued_at')
            ->first();

        // Below the floor, nothing is actionable — a centavo left over from a
        // rounding remainder must never put a tenant into collections.
        if ($total < max($minimum, 0.01)) {
            return $this->stage('current', $arrears, 0, $latestDemand,
                'Nothing overdue.', [], false);
        }

        $blockers = $this->blockers($lease, $latestDemand);

        // An open demand that has been served and whose period has run out is
        // the furthest the system goes.
        if ($latestDemand && $latestDemand->graceHasExpired()) {
            return $latestDemand->type === PaymentDemand::TYPE_NOTICE_TO_VACATE
                ? $this->stage('eligible_for_formal_action', $arrears, $daysPastDue, $latestDemand,
                    'The notice period has run out. Refer the file to counsel — the next step is a court filing, '
                    . 'which cannot be started from this system.', $blockers, true)
                : $this->stage('grace_expired', $arrears, $daysPastDue, $latestDemand,
                    'The ' . strtolower($latestDemand->typeLabel()) . ' period has run out with no payment. '
                    . 'Consider issuing the next notice in the sequence.', $blockers, true);
        }

        // A demand is out and still running. Wait.
        if ($latestDemand && $latestDemand->isOpen() && $latestDemand->isDelivered()) {
            $left = $latestDemand->daysRemaining();

            return $this->stage('demand_due', $arrears, $daysPastDue, $latestDemand,
                sprintf('%s served on %s. %d day(s) left to comply — no further action until then.',
                    $latestDemand->typeLabel(),
                    Carbon::parse($latestDemand->delivered_at)->format('M d, Y'),
                    max(0, (int) $left)
                ), $blockers, false);
        }

        // Issued but never recorded as served. This is the dangerous one.
        if ($latestDemand && $latestDemand->isUndelivered()) {
            return $this->stage('demand_due', $arrears, $daysPastDue, $latestDemand,
                'A ' . strtolower($latestDemand->typeLabel()) . ' was issued but has not been recorded as delivered. '
                . 'The notice period does not start until it is served — record the delivery details.',
                $blockers, false);
        }

        $demandAfter = (int) config('collections.stages.demand_due.after_days', 15);

        if ($daysPastDue >= $demandAfter) {
            return $this->stage('demand_due', $arrears, $daysPastDue, $latestDemand,
                'Overdue ' . $daysPastDue . ' day(s). A formal written demand is appropriate.',
                $blockers, true);
        }

        return $this->stage('overdue', $arrears, $daysPastDue, $latestDemand,
            'Overdue ' . $daysPastDue . ' day(s). A reminder is usually enough at this point.',
            $blockers, true);
    }

    /**
     * Reasons a manager should pause before escalating. Warnings, not blocks —
     * the manager still decides.
     */
    private function blockers(Lease $lease, ?PaymentDemand $latest): array
    {
        $blockers = [];

        // Charging a tenant for an estimate we have not checked, then demanding
        // payment on it, is the fastest way to lose an otherwise sound case.
        $estimates = $lease->utilityBills()
            ->open()
            ->where('is_estimated', true)
            ->whereNull('reconciled_at')
            ->count();

        if ($estimates > 0) {
            $blockers[] = $estimates . ' bill(s) on this lease are still estimates. They are not part '
                . 'of the amount claimed (an estimate cannot be paid until its final amount is set) — '
                . 'set their final amounts before demanding payment.';
        }

        if ($latest && $latest->isUndelivered()) {
            $blockers[] = 'The last demand has no delivery record. Without proof of service '
                . 'the notice period has not started.';
        }

        $sequence = config('collections.demand_sequence', []);
        if ($latest && $latest->type === PaymentDemand::TYPE_NOTICE_TO_VACATE) {
            $priorDemand = PaymentDemand::where('lease_id', $lease->id)
                ->where('type', PaymentDemand::TYPE_FORMAL_DEMAND)
                ->exists();

            if (!$priorDemand && in_array(PaymentDemand::TYPE_FORMAL_DEMAND, $sequence, true)) {
                $blockers[] = 'A notice to vacate was issued without a formal demand to pay on file.';
            }
        }

        return $blockers;
    }

    /**
     * Record a demand. Called only from a manager action — never scheduled,
     * never triggered by a balance changing.
     *
     * The amounts are frozen at issue: this record has to still read the same
     * in six months, after the balance has moved.
     */
    public function issueDemand(Lease $lease, string $type, array $data, User $issuedBy): PaymentDemand
    {
        $this->guardAgainstSelfHelp();

        if (!in_array($type, config('collections.demand_sequence', []), true)) {
            throw new RuntimeException("Unknown demand type [{$type}].");
        }

        $arrears = $this->billing->arrearsFor($lease);

        $grace = (int) ($data['grace_period_days']
            ?? config("collections.grace_periods.{$type}", 15));

        $issuedAt = Carbon::now();

        $demand = PaymentDemand::create([
            'tenant_id'          => $lease->user_id,
            'lease_id'           => $lease->id,
            'type'               => $type,
            'amount_claimed'     => $arrears['total'],
            'rent_component'     => $arrears['rent'],
            'utility_component'  => $arrears['utilities'],
            'other_component'    => $arrears['penalty'],
            'issued_at'          => $issuedAt,
            'grace_period_days'  => $grace,
            'comply_by'          => $issuedAt->copy()->addDays($grace)->toDateString(),
            'delivery_channel'   => $data['delivery_channel'] ?? null,
            'delivery_reference' => $data['delivery_reference'] ?? null,
            'delivered_at'       => !empty($data['delivered_at']) ? Carbon::parse($data['delivered_at']) : null,
            'body'               => $data['body'] ?? $this->defaultBody($lease, $type, $arrears, $grace),
            'notes'              => $data['notes'] ?? null,
            'document_path'      => $data['document_path'] ?? null,
            'outcome'            => PaymentDemand::OUTCOME_OPEN,
            'issued_by'          => $issuedBy->id,
        ]);

        // An earlier open demand of the same or lower rank is superseded, so
        // the file does not show three live notices claiming different amounts.
        PaymentDemand::where('lease_id', $lease->id)
            ->where('id', '!=', $demand->id)
            ->where('outcome', PaymentDemand::OUTCOME_OPEN)
            ->update([
                'outcome'     => PaymentDemand::OUTCOME_SUPERSEDED,
                'resolved_at' => Carbon::now(),
            ]);

        Log::info("Demand {$type} #{$demand->id} issued on lease {$lease->id} by user {$issuedBy->id}.");

        return $demand;
    }

    /** Record that a notice was actually served. This is what starts the clock. */
    public function recordDelivery(PaymentDemand $demand, array $data): PaymentDemand
    {
        $demand->update([
            'delivery_channel'   => $data['delivery_channel'] ?? $demand->delivery_channel,
            'delivery_reference' => $data['delivery_reference'] ?? $demand->delivery_reference,
            'delivered_at'       => Carbon::parse($data['delivered_at'] ?? now()),
            'document_path'      => $data['document_path'] ?? $demand->document_path,
        ]);

        // The compliance date runs from service, not from drafting.
        $demand->comply_by = Carbon::parse($demand->delivered_at)
            ->addDays((int) $demand->grace_period_days)
            ->toDateString();
        $demand->save();

        return $demand;
    }

    public function closeDemand(PaymentDemand $demand, string $outcome, ?string $notes = null): PaymentDemand
    {
        $demand->update([
            'outcome'     => $outcome,
            'resolved_at' => Carbon::now(),
            'notes'       => $notes ? trim(($demand->notes ? $demand->notes . "\n" : '') . $notes) : $demand->notes,
        ]);

        return $demand;
    }

    /**
     * A starting draft. The manager edits it before it goes out, and a formal
     * demand should be reviewed by counsel — the wording and the period are
     * exactly what gets challenged.
     */
    private function defaultBody(Lease $lease, string $type, array $arrears, int $grace): string
    {
        $room  = $lease->room_no ?? ($lease->unit->room_no ?? 'your unit');
        $total = number_format($arrears['total'], 2);
        $by    = Carbon::now()->addDays($grace)->format('F j, Y');

        $lines = [
            'Our records show an outstanding balance of PHP ' . $total . ' on ' . $room . '.',
            '',
            'Breakdown:',
            '  Rent            PHP ' . number_format($arrears['rent'], 2),
            '  Utilities       PHP ' . number_format($arrears['utilities'], 2),
            '  Other charges   PHP ' . number_format($arrears['penalty'], 2),
            '',
        ];

        $lines[] = match ($type) {
            PaymentDemand::TYPE_NOTICE_TO_VACATE =>
                'This is a formal notice to vacate. Please settle the balance in full or vacate the premises '
                . 'on or before ' . $by . '. If you believe this balance is incorrect, contact us immediately.',
            PaymentDemand::TYPE_FORMAL_DEMAND =>
                'This is a formal demand for payment. Please settle the balance in full on or before ' . $by . ', '
                . 'or contact us before that date to agree on a payment arrangement.',
            default =>
                'Please settle this balance on or before ' . $by . ', or get in touch if you need to arrange '
                . 'a payment plan. If you have already paid, please send us the reference number.',
        };

        return implode("\n", $lines);
    }

    private function stage(
        string $key, array $arrears, int $daysPastDue,
        ?PaymentDemand $demand, string $nextStep, array $blockers, bool $actionable
    ): array {
        return [
            'stage'         => $key,
            'label'         => config("collections.stages.{$key}.label", ucfirst(str_replace('_', ' ', $key))),
            'arrears'       => $arrears,
            'days_past_due' => $daysPastDue,
            'latest_demand' => $demand,
            'next_step'     => $nextStep,
            'blockers'      => $blockers,
            'actionable'    => $actionable,
        ];
    }

    /**
     * The one hard stop in this class.
     *
     * config('collections.allow_self_help_remedies') exists so that turning on
     * lockouts or utility cut-offs is not a thing anyone can do quietly. There
     * is no code path that reads it as true — this throws first.
     */
    private function guardAgainstSelfHelp(): void
    {
        if (config('collections.allow_self_help_remedies') === true) {
            throw new RuntimeException(
                'collections.allow_self_help_remedies is enabled. This system does not implement lockouts, '
                . 'utility disconnections or any other self-help remedy, and they are generally unlawful '
                . 'regardless of how far behind a tenant is. Set it back to false.'
            );
        }
    }
}
