<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an advance-rent payment actually bought.
 *
 * An advance payment does two different things at once: it clears whatever rent
 * and penalty are already outstanding, and it buys some number of future months.
 * Those two amounts have to be told apart, because reversing the payment has to
 * put the arrears back on `rent_balance` and take the advance back off
 * `advance_rent_balance` — and roll `due_date` back by exactly the months it
 * moved forward.
 *
 * Recomputing that at reversal time is not safe: the rent may have gone up at a
 * renewal since, so "amount ÷ current rent" would give a different month count
 * than the one that was granted. So the split is recorded at the moment it is
 * applied, and the reversal reads it back.
 *
 * Written only by App\Services\BillingService::applyToAdvanceRent().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Whole months of rent this payment bought. 0 for everything else.
            $table->unsignedSmallInteger('advance_months')->default(0)->after('pay_amount');

            // The part of pay_amount that landed on the lease's advance balance
            // rather than clearing arrears.
            $table->decimal('advance_amount', 12, 2)->default(0)->after('advance_months');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['advance_months', 'advance_amount']);
        });
    }
};
