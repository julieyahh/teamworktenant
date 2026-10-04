<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v20
 *
 * 1. Setting the actual amount now REWRITES the bill.
 *    `amount` (and `kwh_reading` for electricity) become the final figures.
 *    What the tenant was first shown is kept in `estimated_amount` /
 *    `estimated_kwh`, and the reconciliation entry stays in bill_adjustments
 *    as a history line only — it no longer adds to totalDue().
 *
 *    Existing reconciled bills are converted here so nothing is counted twice:
 *      estimated_amount = amount
 *      amount           = amount + sum(reconciliation adjustments)
 *
 * 2. A Repair bill is linked to the maintenance request it came from
 *    (`maintenance_request_id`), so voiding the bill can un-charge the
 *    request. Backfilled from the "Maintenance request #N" note the charge
 *    has always written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            if (! Schema::hasColumn('utility_billing_proofs', 'estimated_amount')) {
                $table->decimal('estimated_amount', 12, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('utility_billing_proofs', 'estimated_kwh')) {
                $table->decimal('estimated_kwh', 10, 2)->nullable()->after('kwh_reading');
            }
            if (! Schema::hasColumn('utility_billing_proofs', 'maintenance_request_id')) {
                $table->foreignId('maintenance_request_id')->nullable()->after('batch_id')
                    ->constrained('maintenance_requests')->nullOnDelete();
            }
        });

        // --- 1. Fold existing reconciliation entries into the bill amount ---
        $sums = DB::table('bill_adjustments')
            ->where('type', 'reconciliation')
            ->groupBy('bill_id')
            ->select('bill_id', DB::raw('SUM(amount) as total'), DB::raw('MIN(id) as first_id'))
            ->get();

        foreach ($sums as $row) {
            $bill = DB::table('utility_billing_proofs')->where('id', $row->bill_id)->first();

            if (! $bill || $bill->estimated_amount !== null) {
                continue;   // already converted
            }

            // The first reconciliation reason carries the estimated reading.
            $estimatedKwh = null;
            $reason = DB::table('bill_adjustments')->where('id', $row->first_id)->value('reason');
            if ($reason && preg_match('/estimated\s+([\d,]+(?:\.\d+)?)\s*kWh/i', $reason, $m)) {
                $estimatedKwh = (float) str_replace(',', '', $m[1]);
            }

            DB::table('utility_billing_proofs')->where('id', $bill->id)->update([
                'estimated_amount' => $bill->amount,
                'estimated_kwh'    => $estimatedKwh,
                'amount'           => round((float) $bill->amount + (float) $row->total, 2),
            ]);
        }

        // Bills reconciled with no change still had an estimate — record it.
        DB::table('utility_billing_proofs')
            ->whereNotNull('reconciled_at')
            ->whereNull('estimated_amount')
            ->update(['estimated_amount' => DB::raw('amount')]);

        // --- 2. Link Repair bills to their maintenance request ---
        DB::table('utility_billing_proofs')
            ->where('billing_type', 'Repair')
            ->whereNull('maintenance_request_id')
            ->whereNotNull('notes')
            ->orderBy('id')
            ->get(['id', 'notes'])
            ->each(function ($bill) {
                if (preg_match('/Maintenance request #(\d+)/', (string) $bill->notes, $m)
                    && DB::table('maintenance_requests')->where('id', (int) $m[1])->exists()) {
                    DB::table('utility_billing_proofs')->where('id', $bill->id)
                        ->update(['maintenance_request_id' => (int) $m[1]]);
                }
            });
    }

    public function down(): void
    {
        // Put the reconciliation back into the adjustment, out of the amount.
        $sums = DB::table('bill_adjustments')
            ->where('type', 'reconciliation')
            ->groupBy('bill_id')
            ->select('bill_id', DB::raw('SUM(amount) as total'))
            ->get();

        foreach ($sums as $row) {
            $bill = DB::table('utility_billing_proofs')->where('id', $row->bill_id)->first();

            if ($bill && $bill->estimated_amount !== null) {
                DB::table('utility_billing_proofs')->where('id', $bill->id)->update([
                    'amount' => $bill->estimated_amount,
                ]);
            }
        }

        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            if (Schema::hasColumn('utility_billing_proofs', 'maintenance_request_id')) {
                $table->dropConstrainedForeignId('maintenance_request_id');
            }
            foreach (['estimated_amount', 'estimated_kwh'] as $col) {
                if (Schema::hasColumn('utility_billing_proofs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
