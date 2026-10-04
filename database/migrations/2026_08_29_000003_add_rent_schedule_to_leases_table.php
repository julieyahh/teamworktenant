<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly rent accrual.
 *
 * THE BUG THIS FIXES
 *   Rent was charged exactly twice in a lease's life: once at approval, and
 *   again at each yearly renewal. `leases.rent_balance` was set to one month
 *   at approval, and `due_date` rolled forward a month whenever the balance
 *   reached zero — but nothing ever put the NEXT month on the balance. A
 *   tenant who paid their first month owed nothing for the following eleven.
 *
 * HOW IT WORKS NOW
 *   `next_rent_charge_on` is the date the next month of rent falls due to be
 *   raised. The `rent:charge` command raises one charge per month per lease
 *   and steps the date forward. See App\Services\BillingService::chargeDueRent().
 *
 * WHY THE BACKFILL DOES NOT COLLECT BACK RENT
 *   Every existing lease has months that were never billed. Setting
 *   `next_rent_charge_on` to the lease start would make the first cron run
 *   raise a year of arrears against tenants who were never told they owed it,
 *   turning a system bug into a debt collection problem overnight.
 *
 *   So the backfill starts the clock at the LATER of the lease's current
 *   due_date and today. A lease that is paid up and current keeps its
 *   schedule; a lease that is behind starts accruing again from now, and the
 *   un-billed gap is written off. That is the only defensible default: you
 *   cannot retroactively invoice someone for months you never invoiced them
 *   for. Anything genuinely owed from that period is already sitting on
 *   `rent_balance` and is unaffected by this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            // When the next month of rent should be raised. Null means this
            // lease is not on the monthly schedule (terminated, or created
            // before the column existed and not eligible for backfill).
            $table->date('next_rent_charge_on')->nullable()->after('rent_paid_through');

            // The due_date a late penalty was last assessed against. Stops the
            // same overdue cycle being penalised every night the cron runs —
            // one missed month, one penalty.
            $table->date('last_penalty_due_date')->nullable()->after('penalty_fee');
        });

        // Start the clock at the later of due_date and today. See the class
        // docblock: this deliberately forgives the un-billed gap.
        $today = now()->toDateString();

        DB::table('leases')
            ->whereIn(DB::raw('LOWER(lea_status)'), ['active', 'pending'])
            ->update([
                'next_rent_charge_on' => DB::raw(
                    "CASE WHEN due_date IS NULL OR due_date < '{$today}'
                          THEN '{$today}'
                          ELSE due_date
                     END"
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['next_rent_charge_on', 'last_penalty_due_date']);
        });
    }
};
