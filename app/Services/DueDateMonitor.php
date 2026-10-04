<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\UtilityBillingProof;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything with a due date the office should be watching, in one list.
 *
 * Added in v18 for the manager dashboard. Read-only: it never writes a
 * balance, a date or a status — it only reads what BillingService already
 * keeps up to date.
 *
 * Three kinds of item:
 *
 *   rent      One per live lease, off `leases.due_date`. The amount is what is
 *             actually owed (rent_balance + penalty_fee). A lease that owes
 *             nothing still shows its NEXT due date, flagged as paid up, so the
 *             manager can see what is coming — but a paid-up lease is never
 *             counted as overdue, even if its due_date has gone stale.
 *
 *   utility   One per open bill (unpaid/partial) that has a due date. Amount is
 *             UtilityBillingProof::outstanding(), never the raw `amount` — a
 *             half-paid bill shows the half that is left.
 *
 *   lease_end A live lease whose contract ends inside the window. Not money;
 *             it is here because it is a date someone has to act on.
 *
 * Buckets (what the dashboard filters on):
 *   overdue   due before today AND money still owed
 *   today     due today
 *   week      due in the next 1–7 days
 *   month     due in the next 8–WINDOW days
 *
 * Anything further out than WINDOW days is left off — the dashboard is for
 * what needs attention, the Reports page is for everything.
 */
class DueDateMonitor
{
    /** How far ahead to look, in days. */
    public const WINDOW_DAYS = 30;

    /** Lease statuses that count as a tenant actually living there. */
    private const LIVE = ['active', 'Active', 'approved'];

    /**
     * @return array{
     *   items: Collection<int,array<string,mixed>>,
     *   summary: array<string,float|int>
     * }
     */
    public function build(?Carbon $today = null): array
    {
        $today   = ($today ?? Carbon::today())->copy()->startOfDay();
        $horizon = $today->copy()->addDays(self::WINDOW_DAYS);

        $leases = Lease::with(['tenant', 'unit'])
            ->whereIn('lea_status', self::LIVE)
            ->get();

        $items = collect();

        foreach ($leases as $lease) {
            if ($rent = $this->rentItem($lease, $today, $horizon)) {
                $items->push($rent);
            }

            if ($end = $this->leaseEndItem($lease, $today, $horizon)) {
                $items->push($end);
            }
        }

        // payable(): an estimate with no final amount is not due yet (v19).
        $bills = UtilityBillingProof::with(['lease.tenant', 'tenant'])
            ->payable()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $horizon->toDateString())
            ->whereHas('lease', fn ($q) => $q->whereIn('lea_status', self::LIVE))
            ->get();

        foreach ($bills as $bill) {
            if ($util = $this->utilityItem($bill, $today)) {
                $items->push($util);
            }
        }

        // Most urgent first: overdue (oldest first), then by date.
        $order = ['overdue' => 0, 'today' => 1, 'week' => 2, 'month' => 3];
        $items = $items->sort(function ($a, $b) use ($order) {
            return [$order[$a['bucket']], $a['date']->timestamp, $a['tenant']]
               <=> [$order[$b['bucket']], $b['date']->timestamp, $b['tenant']];
        })->values();

        return ['items' => $items, 'summary' => $this->summarise($items)];
    }

    /* ==================================================================
     |  ITEM BUILDERS
     * ================================================================== */

    private function rentItem(Lease $lease, Carbon $today, Carbon $horizon): ?array
    {
        if (! $lease->due_date) {
            return null;
        }

        $date  = Carbon::parse($lease->due_date)->startOfDay();
        $owed  = round((float) ($lease->rent_balance ?? 0) + (float) ($lease->penalty_fee ?? 0), 2);
        $paid  = $owed < 0.01;

        // A paid-up lease with a date in the past has nothing due — the date is
        // just stale until the next charge moves it. Leave it off.
        if ($paid && $date->lt($today)) {
            return null;
        }

        if ($date->gt($horizon)) {
            return null;
        }

        $bucket = $this->bucket($date, $today, $paid);

        return $this->item($lease, [
            'kind'     => 'rent',
            'label'    => 'Rent',
            'date'     => $date,
            'bucket'   => $bucket,
            'amount'   => $paid ? round($lease->currentRent(), 2) : $owed,
            'paid_up'  => $paid,
            'penalty'  => round((float) ($lease->penalty_fee ?? 0), 2),
            'days'     => $this->days($date, $today),
            'link'     => route('manager.collections.show', $lease->id),
        ]);
    }

    private function utilityItem(UtilityBillingProof $bill, Carbon $today): ?array
    {
        $owed = $bill->outstanding();

        if ($owed < 0.01 || ! $bill->lease) {
            return null;
        }

        $date = Carbon::parse($bill->due_date)->startOfDay();

        return $this->item($bill->lease, [
            'kind'     => 'utility',
            'label'    => trim(($bill->billing_type ?: 'Utility') . ' ' . ($bill->billing_month ? '· ' . $bill->billing_month : '')),
            'date'     => $date,
            'bucket'   => $this->bucket($date, $today, false),
            'amount'   => $owed,
            'paid_up'  => false,
            'penalty'  => 0.0,
            'days'     => $this->days($date, $today),
            'link'     => route('manager.bills.show', $bill->id),
        ]);
    }

    private function leaseEndItem(Lease $lease, Carbon $today, Carbon $horizon): ?array
    {
        if (! $lease->lea_end_date) {
            return null;
        }

        $date = Carbon::parse($lease->lea_end_date)->startOfDay();

        if ($date->gt($horizon)) {
            return null;
        }

        // A lease past its end date that is still live has simply not been
        // renewed yet — show it under "today" rather than "overdue", because
        // overdue means money on this screen.
        $bucket = $date->lt($today) ? 'today' : $this->bucket($date, $today, true);

        return $this->item($lease, [
            'kind'     => 'lease_end',
            'label'    => 'Lease ends',
            'date'     => $date,
            'bucket'   => $bucket,
            'amount'   => null,
            'paid_up'  => false,
            'penalty'  => 0.0,
            'days'     => $this->days($date, $today),
            'link'     => route('manager.tenants'),
        ]);
    }

    /** Common fields every item carries. */
    private function item(Lease $lease, array $fields): array
    {
        $tenant = $lease->tenant;
        $name   = $tenant ? trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) : '';

        return array_merge([
            'lease_id' => $lease->id,
            'tenant'   => $name !== '' ? $name : 'Unknown tenant',
            'initial'  => strtoupper(substr($name !== '' ? $name : '?', 0, 1)),
            'room'     => $lease->room_no ?? ($lease->unit->room_no ?? '—'),
            'bed'      => $lease->bed_number,
        ], $fields);
    }

    /* ==================================================================
     |  HELPERS
     * ================================================================== */

    private function bucket(Carbon $date, Carbon $today, bool $paidUp): string
    {
        if ($date->lt($today)) {
            return $paidUp ? 'today' : 'overdue';
        }

        if ($date->equalTo($today)) {
            return 'today';
        }

        return $date->lte($today->copy()->addDays(7)) ? 'week' : 'month';
    }

    /** Signed whole days from today: negative = past due. */
    private function days(Carbon $date, Carbon $today): int
    {
        return (int) round($today->diffInDays($date, false));
    }

    private function summarise(Collection $items): array
    {
        $money = $items->where('kind', '!=', 'lease_end')->where('paid_up', false);

        $overdue = $money->where('bucket', 'overdue');
        $today   = $money->where('bucket', 'today');
        $week    = $money->where('bucket', 'week');

        return [
            'overdue_count'   => $overdue->count(),
            'overdue_amount'  => round((float) $overdue->sum('amount'), 2),
            'overdue_tenants' => $overdue->pluck('lease_id')->unique()->count(),
            'today_count'     => $today->count(),
            'today_amount'    => round((float) $today->sum('amount'), 2),
            'week_count'      => $week->count(),
            'week_amount'     => round((float) $week->sum('amount'), 2),
            'lease_end_count' => $items->where('kind', 'lease_end')->count(),
            'total'           => $items->count(),
        ];
    }
}
