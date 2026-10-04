<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advance rent — money paid ahead of the month it covers.
 *
 * WHY THIS IS NOT `users.user_credit`
 *   user_credit is a general overpayment pot that BillingService spends on
 *   whatever bill comes next, including electricity. Advance rent is not that:
 *   a tenant who pays three months of rent up front has bought THREE MONTHS OF
 *   RENT, and if that money quietly went on a water bill they would be back in
 *   arrears on rent without having done anything wrong. So it is held on the
 *   lease, ring-fenced to rent, and drawn down only against rent and penalties.
 *
 * WHY A `rent_paid_through` DATE AS WELL AS A BALANCE
 *   The balance answers "how much is left". The date answers "am I covered?",
 *   which is the question a tenant actually asks and the one a manager needs at
 *   the counter. Keeping both means neither has to be reverse-engineered from
 *   the other, and `due_date` can move independently without losing the record
 *   of what was bought.
 *
 * Both are written only by App\Services\BillingService — see applyToAdvanceRent()
 * and Lease::consumeAdvanceRent().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            // Rent money held on account, not yet consumed by a due month.
            $table->decimal('advance_rent_balance', 12, 2)->default(0)->after('rent_balance');

            // The last day the tenant's rent is paid up to. Null = never paid
            // in advance, which is not the same as "paid through today".
            $table->date('rent_paid_through')->nullable()->after('advance_rent_balance');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['advance_rent_balance', 'rent_paid_through']);
        });
    }
};
