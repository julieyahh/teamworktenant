<?php

namespace App\Services;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceStatusLog;
use App\Models\MaintenanceWorkItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Repair costs and who pays them.
 *
 * The rule this encodes is deliberately narrow: the system does the arithmetic
 * and records the decision, but it never decides responsibility itself. Whether
 * a broken faucet is fair wear and tear or tenant damage is a judgement call
 * with a lease contract behind it, and getting it wrong in software means
 * silently billing a tenant for something that was never theirs.
 *
 * So `cost_responsibility` starts as 'undetermined' and a person has to set it,
 * with a reason, before anything can be charged.
 */
class MaintenanceCostService
{
    public function __construct(private BillingService $billing)
    {
    }

    public const RESPONSIBILITY = ['landlord', 'tenant', 'shared', 'undetermined'];

    /* ==================================================================
     |  WORK ITEMS
     * ================================================================== */

    public function addWorkItem(MaintenanceRequest $request, array $data, ?User $by = null): MaintenanceWorkItem
    {
        if (!in_array($data['type'] ?? '', [MaintenanceWorkItem::TYPE_LABOR, MaintenanceWorkItem::TYPE_MATERIAL], true)) {
            throw new InvalidArgumentException('A work item is either labor or material.');
        }

        $item = MaintenanceWorkItem::create([
            'maintenance_request_id' => $request->id,
            'type'         => $data['type'],
            'description'  => $data['description'],
            'quantity'     => (float) ($data['quantity'] ?? 1),
            'unit'         => $data['unit'] ?? null,
            'unit_cost'    => (float) ($data['unit_cost'] ?? 0),
            'performed_by' => $data['performed_by'] ?? null,
            'performed_on' => $data['performed_on'] ?? null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'created_by'   => $by?->id,
        ]);

        $this->recalculate($request);

        return $item;
    }

    public function removeWorkItem(MaintenanceWorkItem $item): void
    {
        $request = $item->request;
        $item->delete();

        if ($request) {
            $this->recalculate($request);
        }
    }

    /**
     * Roll the line items up onto the request.
     *
     * The only thing that writes labor_cost / material_cost / total_cost /
     * tenant_charge_amount. If those ever look wrong, the fix is here.
     *
     * A repair already charged to a tenant is NOT recalculated — the tenant has
     * been billed and the amount is settled. Changing the cost after that would
     * move a figure the tenant has already been shown.
     */
    public function recalculate(MaintenanceRequest $request): MaintenanceRequest
    {
        $labor    = (float) $request->workItems()->labor()->sum('line_total');
        $material = (float) $request->workItems()->material()->sum('line_total');
        $total    = round($labor + $material, 2);

        $request->labor_cost    = round($labor, 2);
        $request->material_cost = round($material, 2);
        $request->total_cost    = $total;

        if (!$request->isCharged()) {
            $request->tenant_charge_amount = $this->tenantShareOf($request, $total);
        }

        $request->save();

        return $request;
    }

    /** What the tenant owes under the current responsibility setting. */
    public function tenantShareOf(MaintenanceRequest $request, ?float $total = null): float
    {
        $total ??= (float) $request->total_cost;

        return match ($request->cost_responsibility) {
            'tenant' => round($total, 2),
            'shared' => round($total * ((float) ($request->tenant_share_percent ?? 0) / 100), 2),
            default  => 0.0,   // landlord, or not yet decided
        };
    }

    /* ==================================================================
     |  RESPONSIBILITY
     * ================================================================== */

    /**
     * Record who pays, and why.
     *
     * The reason is required for anything that costs a tenant money. If a
     * charge is ever disputed, "the manager ticked tenant" is not an answer —
     * "tenant reported the damage was caused by a guest, per the note on the
     * request" is.
     */
    public function setResponsibility(
        MaintenanceRequest $request,
        string $responsibility,
        ?float $tenantPercent = null,
        ?string $reason = null,
        ?User $by = null
    ): MaintenanceRequest {

        if (!in_array($responsibility, self::RESPONSIBILITY, true)) {
            throw new InvalidArgumentException("Unknown cost responsibility [{$responsibility}].");
        }

        if ($request->isCharged()) {
            throw new InvalidArgumentException(
                'This repair has already been charged to the tenant. Void its Repair bill (Bills > the bill > Void) to reverse the charge, then change who pays.'
            );
        }

        if ($responsibility === 'shared') {
            $tenantPercent = (float) $tenantPercent;

            if ($tenantPercent <= 0 || $tenantPercent >= 100) {
                throw new InvalidArgumentException(
                    'A shared cost needs a tenant share between 1 and 99 percent. '
                    . 'Use "tenant" or "landlord" for the whole amount.'
                );
            }
        } else {
            $tenantPercent = null;
        }

        if (in_array($responsibility, ['tenant', 'shared'], true) && !trim((string) $reason)) {
            throw new InvalidArgumentException(
                'Charging a tenant for a repair needs a written reason.'
            );
        }

        $request->cost_responsibility   = $responsibility;
        $request->tenant_share_percent  = $tenantPercent;
        $request->responsibility_reason = $reason;
        $request->save();

        return $this->recalculate($request);
    }

    /* ==================================================================
     |  CHARGING THE TENANT
     * ================================================================== */

    /**
     * Post the tenant's share as a bill they can actually pay.
     *
     * It goes through BillingService as a normal bill rather than being bolted
     * onto rent, so it gets the same treatment as everything else: partial
     * payments, its own due date, its own status, and it shows on the ledger
     * as a repair rather than as mystery rent.
     */
    public function chargeTenant(MaintenanceRequest $request, ?User $by = null, ?string $dueDate = null): ?object
    {
        if ($request->isCharged()) {
            throw new InvalidArgumentException('This repair has already been charged.');
        }

        if ($request->cost_responsibility === 'undetermined') {
            throw new InvalidArgumentException(
                'Decide who is responsible for this cost before charging anyone.'
            );
        }

        $amount = $this->tenantShareOf($request);

        if ($amount < 0.01) {
            throw new InvalidArgumentException('There is nothing to charge the tenant for this repair.');
        }

        $lease = $request->lease;

        if (!$lease) {
            throw new InvalidArgumentException('This request is not linked to a lease, so it cannot be billed.');
        }

        return DB::transaction(function () use ($request, $lease, $amount, $by, $dueDate) {

            $bill = $this->billing->issueBill($lease, [
                'billing_type'  => 'Repair',
                'billing_month' => Carbon::now()->format('F Y'),
                'amount'        => $amount,
                'due_date'      => $dueDate,
                'maintenance_request_id' => $request->id,
                'notes'         => sprintf(
                    'Maintenance request #%d — %s. %s',
                    $request->id,
                    \Illuminate\Support\Str::limit((string) $request->description, 80),
                    $request->responsibility_reason ?? ''
                ),
            ], $by);

            $request->tenant_charge_amount = $amount;
            $request->charged_at = Carbon::now();
            $request->charged_by = $by?->id;
            $request->save();

            $this->billing->syncLeaseUtilityBalance($lease);

            return $bill;
        });
    }

    /**
     * Undo a charge because its bill was voided (BillingService::voidBill()).
     *
     * The request goes back to "not yet billed": costs unlock, responsibility
     * can be changed, and the tenant's share can be charged again once it is
     * right. The reversal is written to the request's history so the trail
     * reads "charged -> bill voided -> re-charged" instead of a silent gap.
     */
    public function releaseCharge(MaintenanceRequest $request, ?object $bill = null, ?string $reason = null, ?User $by = null): MaintenanceRequest
    {
        if (! $request->isCharged()) {
            return $request;
        }

        $amount = (float) $request->tenant_charge_amount;

        $request->charged_at = null;
        $request->charged_by = null;
        $request->save();

        $this->recalculate($request);

        MaintenanceStatusLog::create([
            'maintenance_request_id' => $request->id,
            'from_status' => $request->status,
            'to_status'   => $request->status,
            'notes'       => sprintf(
                'Charge of PHP %s reversed%s%s. Costs unlocked; the tenant\'s share can be charged again.',
                number_format($amount, 2),
                $bill ? ' — bill #' . $bill->id . ' voided' : '',
                $reason ? ': ' . $reason : ''
            ),
            'changed_by'  => $by?->id,
        ]);

        return $request->refresh();
    }

    /* ==================================================================
     |  STATUS HISTORY
     * ================================================================== */

    /**
     * Change status and write the history entry in one place, so no route can
     * move a request without leaving a trace.
     */
    public function changeStatus(
        MaintenanceRequest $request,
        string $newStatus,
        ?string $notes = null,
        ?User $by = null
    ): MaintenanceRequest {

        $old = $request->status;

        if ($old === $newStatus && $notes === null) {
            return $request;
        }

        $request->status = $newStatus;

        if ($newStatus === 'In Progress' && !$request->started_at) {
            $request->started_at = Carbon::now();
        }

        if ($newStatus === 'Completed' && !$request->completed_at) {
            $request->completed_at = Carbon::now();
        }

        $request->save();

        MaintenanceStatusLog::create([
            'maintenance_request_id' => $request->id,
            'from_status' => $old,
            'to_status'   => $newStatus,
            'notes'       => $notes,
            'changed_by'  => $by?->id,
        ]);

        return $request;
    }
}
