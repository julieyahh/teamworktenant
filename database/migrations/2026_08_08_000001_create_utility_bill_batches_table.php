<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One provider bill that gets split across several tenants (shared meter).
 *
 * The batch is the audit record of the split: the total we were charged, the
 * rule we used, and where the rounding remainder went. Without it, "why is my
 * share 333.34 and my neighbour's 333.33?" has no answer.
 *
 * The individual tenant bills live in utility_billing_proofs.batch_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bill_batches', function (Blueprint $table) {
            $table->id();

            $table->string('billing_type');              // Electricity | Water | Other
            $table->string('billing_month');             // free text, matches the bill rows
            $table->decimal('total_amount', 12, 2);      // what the provider charged us
            $table->date('due_date')->nullable();

            // equal | headcount | unit_size  (config('billing.split_rules'))
            $table->string('split_rule', 20);

            // landlord | designated_tenant  (config('billing.rounding_remainder'))
            $table->string('remainder_policy', 30);

            // Sum of the centavos that rounding could not assign. Under the
            // 'landlord' policy the landlord eats this; under
            // 'designated_tenant' it was added to designated_lease_id's share.
            $table->decimal('rounding_absorbed', 8, 2)->default(0);
            $table->foreignId('designated_lease_id')->nullable()
                ->constrained('leases')->nullOnDelete();

            $table->boolean('is_estimated')->default(false);
            $table->string('file_path')->nullable();     // the provider's bill
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['billing_type', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bill_batches');
    }
};
