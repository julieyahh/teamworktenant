<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v19 — two timestamps, both additive.
 *
 *   payments.accepted_at
 *       When the office confirmed the payment. The invoice date. Backfilled
 *       from updated_at for payments already Accepted, which is the closest
 *       record the system kept of that moment.
 *
 *   leases.renewal_requested_at
 *       When the tenant asked to renew. The flag (renewal_requested) has been
 *       in the schema since the first migration but nothing ever set it; now
 *       the tenant sets it, and the manager can only renew once it is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('pay_status');
            }
        });

        Schema::table('leases', function (Blueprint $table) {
            if (! Schema::hasColumn('leases', 'renewal_requested_at')) {
                $table->timestamp('renewal_requested_at')->nullable()->after('renewal_requested');
            }
        });

        DB::table('payments')
            ->where('pay_status', 'Accepted')
            ->whereNull('accepted_at')
            ->update(['accepted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }
        });

        Schema::table('leases', function (Blueprint $table) {
            if (Schema::hasColumn('leases', 'renewal_requested_at')) {
                $table->dropColumn('renewal_requested_at');
            }
        });
    }
};
