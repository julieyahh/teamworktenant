<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\UtilityBillingProof;
use App\Support\RentCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    /**
     * Tenant dashboard — v19, compact like the manager's.
     *
     * One screen: what I owe and when (the payment window is the 1st–10th),
     * my lease, and my recent payments and requests. READ-ONLY: nothing here
     * writes a balance, a charge or a penalty.
     */
    public function dashboard()
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $leases = Lease::with('unit')
            ->where('user_id', $user->id)
            ->whereIn('lea_status', ['active', 'Active', 'pending', 'Pending'])
            ->orderBy('lea_start_date')
            ->get();

        $active = $leases->filter(fn ($l) => strtolower((string) $l->lea_status) === 'active')->values();

        // Kept for anything that still reads them.
        $lease       = $user->leases()->with(['tenant', 'unit'])->latest()->first();
        $application = $user->tenantApplication()->latest()->first();

        $payments = Payment::with('lease')
            ->where('tenant_id', $user->id)
            ->orderByDesc('pay_date')
            ->orderByDesc('id')
            ->get();

        $requests = MaintenanceRequest::where('tenant_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $dueItems = $this->dueItems($active, $today);

        $rentOwed   = round($active->sum(fn ($l) => (float) $l->rent_balance + (float) ($l->penalty_fee ?? 0)), 2);
        $utilOwed   = round($active->sum(fn ($l) => $l->openUtilityTotal()), 2);
        $estimated  = round($active->sum(fn ($l) => $l->estimatedUtilityTotal()), 2);
        $advance    = round($active->sum(fn ($l) => $l->advanceRentBalance()), 2);
        $depositDue = round($leases->sum(fn ($l) => (float) ($l->deposit_balance ?? 0)), 2);

        $payable  = $dueItems->where('payable', true);
        $overdue  = $payable->where('overdue', true);
        $nextDue  = $payable->sortBy(fn ($i) => $i['date']->timestamp)->first();

        $openStatuses = ['pending', 'in progress'];

        $summary = [
            'rent_owed'        => $rentOwed,
            'util_owed'        => $utilOwed,
            'estimated'        => $estimated,
            'advance'          => $advance,
            'deposit_due'      => $depositDue,
            'overdue_amount'   => round($overdue->sum('amount'), 2),
            'overdue_count'    => $overdue->count(),
            'next_due'         => $nextDue,
            'pending_payments' => $payments->where('pay_status', 'Pending')->count(),
            'open_requests'    => $requests->filter(fn ($r) => in_array(strtolower((string) $r->status), $openStatuses, true))->count(),
        ];

        $window = RentCalendar::window($today);

        return view('tenant.home', compact(
            'lease', 'application', 'payments', 'requests',
            'leases', 'active', 'dueItems', 'summary', 'window'
        ));
    }

    /**
     * Everything the tenant owes or will owe soon, most urgent first.
     *
     * Each item: kind (rent|penalty|utility|estimate|deposit|upcoming), label,
     * room, date, amount, overdue, payable, note.
     */
    private function dueItems(Collection $active, Carbon $today): Collection
    {
        $items = collect();

        foreach ($active as $lease) {
            $room = $lease->room_no ?? 'N/A';

            if ((float) ($lease->deposit_balance ?? 0) >= 0.01) {
                $items->push($this->item('deposit', 'Security deposit', $room, $lease->lea_start_date ?? $today,
                    (float) $lease->deposit_balance, $today, true, 'Pay this first — it unlocks rent and utility payments.'));
            }

            $rentOutstanding = 0.0;

            foreach ($lease->rentChargeLedger() as $line) {
                if ($line['outstanding'] < 0.01) {
                    continue;
                }

                $charge = $line['charge'];
                $rentOutstanding = round($rentOutstanding + $line['outstanding'], 2);

                $items->push($this->item('rent', 'Rent · ' . $charge->periodLabel(), $room,
                    $charge->due_date ?? $today, $line['outstanding'], $today, true,
                    $charge->isPartMonth() ? 'Part month — moves you onto the ' . RentCalendar::label() . ' schedule' : null));
            }

            // A legacy balance with no charge rows behind it.
            $legacy = round((float) ($lease->rent_balance ?? 0) - $rentOutstanding, 2);
            if ($legacy >= 0.01) {
                $items->push($this->item('rent', 'Rent', $room, $lease->due_date ?? $today, $legacy, $today, true));
            }

            if ((float) ($lease->penalty_fee ?? 0) >= 0.01) {
                $items->push($this->item('penalty', 'Late payment penalty', $room, $lease->last_penalty_due_date ?? $lease->due_date ?? $today,
                    (float) $lease->penalty_fee, $today, true, '10% of the rent that was unpaid after the due date'));
            }

            // Nothing owed on rent: show what is coming next.
            if ((float) ($lease->rent_balance ?? 0) < 0.01 && $lease->next_rent_charge_on) {
                $start = Carbon::parse($lease->next_rent_charge_on);
                $amount = RentCalendar::amountFor($start, $lease->currentRent());
                $covered = $lease->advanceRentBalance() >= $amount && $amount > 0;

                $items->push($this->item('upcoming', 'Next rent · ' . ($start->day === RentCalendar::opensOnDay() ? $start->format('F Y') : $start->format('M j') . ' onward'),
                    $room, RentCalendar::rentDueDate($start), $amount, $today, false,
                    $covered ? 'Covered by rent you paid in advance' : 'Raised on ' . $start->format('M j') . ', pay by ' . RentCalendar::rentDueDate($start)->format('M j')));
            }

            foreach ($lease->utilityBills()->open()->get() as $bill) {
                $owed = $bill->outstanding();

                if ($owed < 0.01) {
                    continue;
                }

                if ($bill->isUnreconciledEstimate()) {
                    $items->push($this->item('estimate', $bill->billing_type . ' · ' . $bill->billing_month, $room,
                        $bill->due_date ?? $today, $owed, $today, false,
                        'Estimate — payable once the office sets the final amount'));
                    continue;
                }

                $items->push($this->item('utility', $bill->billing_type . ' · ' . $bill->billing_month, $room,
                    $bill->due_date ?? $today, $owed, $today, true,
                    (float) $bill->amount_paid > 0 ? 'Part paid — ₱' . number_format((float) $bill->amount_paid, 2) . ' received' : null));
            }
        }

        // Overdue first, then by date; things that cannot be paid yet last.
        return $items->sort(function ($a, $b) {
            return [$a['overdue'] ? 0 : 1, $a['payable'] ? 0 : 1, $a['date']->timestamp]
               <=> [$b['overdue'] ? 0 : 1, $b['payable'] ? 0 : 1, $b['date']->timestamp];
        })->values();
    }

    private function item(string $kind, string $label, string $room, $date, float $amount, Carbon $today, bool $payable, ?string $note = null): array
    {
        $date = Carbon::parse($date)->startOfDay();

        return [
            'kind'    => $kind,
            'label'   => $label,
            'room'    => $room,
            'date'    => $date,
            'amount'  => round($amount, 2),
            'payable' => $payable,
            'overdue' => $payable && $date->lt($today),
            'days'    => (int) $today->diffInDays($date, false),
            'note'    => $note,
        ];
    }

    public function payments()
    {
        $tenant = Auth::user();

        $payments = Payment::where('tenant_id', $tenant->id)
                            ->orderBy('pay_date', 'desc')
                            ->get();

        // Exclude deposit records since they’re one-time
        $payments = $payments->filter(function ($payment) {
            return strtolower($payment->type) !== 'deposit';
        });

        return view('tenant.payments', compact('payments'));
    }

    public function requests()
    {
        $tenant = Auth::user();

        $requests = MaintenanceRequest::where('tenant_id', $tenant->id)
                                      ->orderBy('created_at', 'desc')
                                      ->get();

        return view('tenant.request', compact('requests'));
    }
}
