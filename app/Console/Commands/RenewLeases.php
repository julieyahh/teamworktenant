<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewLeases extends Command
{
    protected $signature = 'leases:renew {--dry-run : Show what would happen without saving anything}';

    protected $description = 'Renew active leases that have reached their end date, applying the 5% yearly rent increase.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today  = Carbon::today();

        $leases = Lease::with('tenant', 'unit')
            ->where('lea_status', 'active')
            ->where('move_out_requested', false)
            // v19: only leases the tenant has asked to renew. A lease that
            // reaches its end date without a request is left as it is —
            // still active, still charged monthly at its current rate — and
            // shows on the manager dashboard under "Lease ends".
            ->where('renewal_requested', true)
            ->whereNotNull('lea_end_date')
            ->whereDate('lea_end_date', '<=', $today)
            ->get();

        if ($leases->isEmpty()) {
            $this->info('No requested renewals are due today.');
            return self::SUCCESS;
        }

        $renewed = 0;

        foreach ($leases as $lease) {
            // Never renew the same lease twice in one run/day.
            if ($lease->last_renewed_at && Carbon::parse($lease->last_renewed_at)->isToday()) {
                continue;
            }

            $oldRent = $lease->currentRent();
            $newRent = round($oldRent * (1 + Lease::RENT_INCREASE_RATE), 2);

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] Lease #%d (room %s): %s -> %s',
                    $lease->id,
                    $lease->room_no ?? 'N/A',
                    number_format($oldRent, 2),
                    number_format($newRent, 2)
                ));
                continue;
            }

            try {
                DB::transaction(function () use ($lease, &$renewed) {

                    // Source is recorded so the renewal report can tell a
                    // manager-clicked renewal from one the cron rolled over.
                    $summary = $lease->renewForAnotherYear(
                        null,
                        \App\Models\LeaseRenewal::SOURCE_SCHEDULED,
                        null
                    );

                    if (! $summary) {
                        return;
                    }

                    if ($lease->tenant) {
                        $tenant = $lease->tenant;
                        // The rate changes; the BALANCE does not. Renewal no
                        // longer bills a month — `rent:charge` does, at the
                        // new rate, when the month actually comes round.
                        // Adding it here as well would bill it twice.
                        $tenant->rent_amount = ($tenant->rent_amount ?? 0) - $summary['old_rent'] + $summary['new_rent'];
                        $tenant->save();

                        Notification::create([
                            'user_id' => $tenant->id,
                            'title'   => 'Lease Renewed',
                            'message' => sprintf(
                                'Your lease for room %s has automatically renewed until %s. As per your contract, rent increased by 5%%: PHP %s to PHP %s per month.',
                                $lease->room_no ?? 'N/A',
                                $summary['end_date']->format('M d, Y'),
                                number_format($summary['old_rent'], 2),
                                number_format($summary['new_rent'], 2)
                            ),
                        ]);
                    }

                    foreach (User::where('role', 'manager')->get() as $manager) {
                        Notification::create([
                            'user_id' => $manager->id,
                            'title'   => 'Lease Auto-Renewed',
                            'message' => sprintf(
                                'Lease for room %s (%s) renewed until %s. Rent: PHP %s to PHP %s.',
                                $lease->room_no ?? 'N/A',
                                $lease->tenant->name ?? 'Unknown tenant',
                                $summary['end_date']->format('M d, Y'),
                                number_format($summary['old_rent'], 2),
                                number_format($summary['new_rent'], 2)
                            ),
                        ]);
                    }

                    $renewed++;
                });
            } catch (\Throwable $e) {
                Log::error('Auto-renewal failed for lease ' . $lease->id . ': ' . $e->getMessage());
                $this->error('Lease #' . $lease->id . ' failed: ' . $e->getMessage());
            }
        }

        $this->info($dryRun
            ? 'Dry run complete. ' . $leases->count() . ' lease(s) would be considered.'
            : $renewed . ' lease(s) renewed with the 5% increase applied.');

        return self::SUCCESS;
    }
}
