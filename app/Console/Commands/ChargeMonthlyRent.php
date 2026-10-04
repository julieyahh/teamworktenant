<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Notification;
use App\Services\BillingService;
use App\Support\RentCalendar;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Raise each month's rent, and penalise what is overdue.
 *
 * Runs nightly (see routes/schedule.php). Before v16 nothing did this: rent
 * was charged at approval and at each yearly renewal and never in between, so
 * a tenant who paid their first month owed nothing for the following eleven.
 *
 *   php artisan rent:charge
 *   php artisan rent:charge --dry-run          # show what would happen
 *   php artisan rent:charge --lease=42         # one lease
 *   php artisan rent:charge --date=2026-09-01  # pretend it is another day
 *
 * SAFE TO RUN TWICE. Charges are guarded by a unique index on
 * (lease_id, period_start), and penalties by leases.last_penalty_due_date, so
 * neither an overlapping run nor a nervous manager running it by hand can
 * double-bill anybody.
 *
 * RUN IT WITH --dry-run FIRST after deploying. The migration deliberately
 * starts every existing lease's clock at today rather than at its start date
 * (see 2026_08_29_000003), so nothing should be more than one month behind on
 * the first real run. If the dry run says otherwise, stop and find out why
 * before charging anyone.
 */
class ChargeMonthlyRent extends Command
{
    protected $signature = 'rent:charge
                            {--dry-run : Report what would be charged without writing anything}
                            {--lease= : Only this lease id}
                            {--date= : Treat this date as today (YYYY-MM-DD)}';

    protected $description = 'Raise rent for every month that has fallen due, and assess late penalties.';

    public function __construct(private BillingService $billing)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('billing.rent.auto_charge', true)) {
            $this->warn('billing.rent.auto_charge is off — nothing to do.');

            return self::SUCCESS;
        }

        $asOf   = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Rent run for ' . $asOf->toDateString());

        $query = Lease::with('tenant', 'unit')
            ->whereNotNull('next_rent_charge_on')
            ->whereIn('lea_status', ['active', 'Active', 'pending', 'Pending']);

        if ($this->option('lease')) {
            $query->whereKey($this->option('lease'));
        }

        $charged = 0;
        $penalised = 0;
        $totalRent = 0.0;
        $totalPenalty = 0.0;
        $behind = [];

        // Chunked: a few hundred leases is fine in memory, a few thousand is
        // not, and this runs unattended.
        $query->chunkById(100, function ($leases) use (
            $asOf, $dryRun, &$charged, &$penalised, &$totalRent, &$totalPenalty, &$behind
        ) {
            foreach ($leases as $lease) {
                $this->processLease($lease, $asOf, $dryRun, $charged, $penalised, $totalRent, $totalPenalty, $behind);
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '%s%d rent charge(s) raised, PHP %s. %d penalty/ies, PHP %s.',
            $dryRun ? '[DRY RUN] would have raised ' : '',
            $charged,
            number_format($totalRent, 2),
            $penalised,
            number_format($totalPenalty, 2)
        ));

        if ($behind) {
            // The loudest thing this command can tell you: the scheduler has
            // not been running. Silence here is what let the original bug sit
            // undetected.
            $this->error(
                'These leases were more than the catch-up cap behind: ' . implode(', ', $behind) . '. '
                . 'That means the scheduler has not been running. Check the server cron entry '
                . '(php artisan schedule:list) before running this again.'
            );

            Log::error('rent:charge hit the catch-up cap on lease(s) ' . implode(', ', $behind));
        }

        return self::SUCCESS;
    }

    private function processLease(
        Lease $lease,
        Carbon $asOf,
        bool $dryRun,
        int &$charged,
        int &$penalised,
        float &$totalRent,
        float &$totalPenalty,
        array &$behind
    ): void {

        $due = $lease->rentChargesOutstanding($asOf);
        $label = 'Lease ' . $lease->id . ' (' . ($lease->tenant->name ?? 'unknown') . ', room ' . ($lease->room_no ?? '?') . ')';

        if ($due > 0) {
            if ($dryRun) {
                $cap = max(1, (int) config('billing.rent.max_catchup_months', 3));
                $take = min($due, $cap);

                // Walk the same calendar periods chargeDueRent() would, so a
                // prorated part-month shows its real amount (v19).
                $start  = Carbon::parse($lease->next_rent_charge_on)->startOfDay();
                $amount = 0.0;
                $labels = [];

                for ($i = 0; $i < $take; $i++) {
                    $amount   = round($amount + RentCalendar::amountFor($start, $lease->currentRent()), 2);
                    $labels[] = RentCalendar::isWholeMonth($start)
                        ? $start->format('M Y')
                        : $start->format('M j') . '–' . RentCalendar::periodEnd($start)->subDay()->format('j') . ' (part)';
                    $start    = RentCalendar::periodEnd($start);
                }

                $this->line(sprintf(
                    '  %s — %d period(s) due, would raise PHP %s for %s',
                    $label, $due, number_format($amount, 2), implode(', ', $labels)
                ));

                $charged   += $take;
                $totalRent  = round($totalRent + $amount, 2);

                if ($due > $cap) {
                    $behind[] = $lease->id;
                }
            } else {
                $result = $this->billing->chargeDueRent($lease, $asOf);

                if ($result['charged'] > 0) {
                    $charged  += $result['charged'];
                    $totalRent = round($totalRent + $result['total'], 2);

                    $this->line(sprintf(
                        '  %s — raised %d charge(s), PHP %s%s',
                        $label,
                        $result['charged'],
                        number_format($result['total'], 2),
                        $result['advance_used'] > 0
                            ? ' (PHP ' . number_format($result['advance_used'], 2) . ' met from advance rent)'
                            : ''
                    ));

                    $this->notifyOfCharge($lease->fresh(), $result);
                }

                if ($result['capped']) {
                    $behind[] = $lease->id;
                }
            }
        }

        // Penalties are assessed after the charge, so a month that fell due
        // today is not penalised on the same night it was raised.
        $lease->refresh();

        if ($dryRun) {
            return;
        }

        $penalty = $this->billing->assessLatePenalty($lease, $asOf);

        if ($penalty > 0) {
            $penalised++;
            $totalPenalty = round($totalPenalty + $penalty, 2);

            $this->line(sprintf('  %s — late penalty PHP %s', $label, number_format($penalty, 2)));

            if ($lease->tenant) {
                Notification::create([
                    'user_id' => $lease->tenant->id,
                    'title'   => 'Late Payment Penalty',
                    'message' => 'A late payment charge of PHP ' . number_format($penalty, 2)
                        . ' has been added to room ' . ($lease->room_no ?? 'your unit')
                        . ' because the rent due on ' . Carbon::parse($lease->due_date)->format('F d, Y')
                        . ' was not settled. Please contact the office if you have already paid '
                        . 'or need to arrange something.',
                ]);
            }
        }
    }

    /**
     * Tell the tenant their rent has been raised — including, importantly,
     * when it was met out of money they had already paid ahead. Silence there
     * would look like the advance had not been used.
     */
    private function notifyOfCharge(Lease $lease, array $result): void
    {
        if (! $lease->tenant || empty($result['charges'])) {
            return;
        }

        $months = collect($result['charges'])->map(fn ($c) => $c->periodLabel())->implode(', ');

        $lines = [
            'Rent for ' . $months . ' has been added to room ' . ($lease->room_no ?? 'your unit')
            . ': PHP ' . number_format($result['total'], 2) . '.',
        ];

        if ($result['advance_used'] > 0) {
            $lines[] = 'PHP ' . number_format($result['advance_used'], 2)
                . ' of this was covered by rent you had already paid in advance.';
        }

        $outstanding = round((float) ($lease->rent_balance ?? 0), 2);

        $lines[] = $outstanding >= 0.01
            ? 'PHP ' . number_format($outstanding, 2) . ' is now outstanding, due '
              . Carbon::parse($lease->due_date)->format('F d, Y') . '.'
            : 'Nothing is outstanding — this was fully covered.';

        Notification::create([
            'user_id' => $lease->tenant->id,
            'title'   => 'Rent Charged',
            'message' => implode(' ', $lines),
        ]);
    }
}
