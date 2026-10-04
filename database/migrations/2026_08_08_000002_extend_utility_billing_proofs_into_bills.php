<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns `utility_billing_proofs` into a real bill.
 *
 * The table was only ever "here is a photo of the bill and how much it was".
 * A bill now has to answer: how much was billed, how much has been paid, what
 * is still open, when was it due, was it an estimate, and which shared batch
 * did it come from.
 *
 * The table keeps its name. Renaming it would break the `migrations` table key,
 * every `UtilityBillingProof::` call and the three copies of the report filters.
 * The model is App\Models\UtilityBillingProof and it is documented as "the bill".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            // What has actually been collected against this bill.
            $table->decimal('amount_paid', 12, 2)->default(0)->after('amount');

            // When it has to be paid. Null on legacy rows; backfilled below.
            $table->date('due_date')->nullable()->after('billing_month');

            // Estimated bills: the provider bill had not arrived yet.
            $table->boolean('is_estimated')->default(false)->after('due_date');
            $table->timestamp('reconciled_at')->nullable()->after('is_estimated');

            // Shared/prorated bills point back at the batch they were split from.
            $table->foreignId('batch_id')->nullable()->after('lease_id')
                ->constrained('utility_bill_batches')->nullOnDelete();

            // The tenant's share as computed, before the rounding remainder was
            // applied. Kept so a tenant can be shown exactly how we got here.
            $table->decimal('share_basis_value', 12, 4)->nullable()->after('batch_id');
            $table->decimal('rounding_adjustment', 8, 2)->default(0)->after('share_basis_value');

            // Voiding replaces deleting. History stays.
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->string('void_reason')->nullable()->after('voided_at');

            $table->foreignId('created_by')->nullable()->after('void_reason')
                ->constrained('users')->nullOnDelete();

            $table->index(['lease_id', 'status']);
            $table->index('due_date');
        });

        // status was enum('unpaid','paid'). A bill can now also be 'partial'
        // (some money in, more owed) or 'void'.
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            $table->string('status', 20)->default('unpaid')->change();
        });

        // Backfill: existing bills already marked paid have been paid in full,
        // and everything gets a due date so the arrears query has something to
        // sort on. `default_bill_due_days` is not read here on purpose — this
        // is a one-off historical fix, not the ongoing rule.
        DB::table('utility_billing_proofs')
            ->where('status', 'paid')
            ->update(['amount_paid' => DB::raw('COALESCE(amount, 0)')]);

        DB::table('utility_billing_proofs')
            ->whereNull('due_date')
            ->update(['due_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['lease_id', 'status']);
            $table->dropIndex(['due_date']);
            $table->dropColumn([
                'amount_paid', 'due_date', 'is_estimated', 'reconciled_at',
                'share_basis_value', 'rounding_adjustment', 'voided_at', 'void_reason',
            ]);
        });

        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid')->change();
        });
    }
};
