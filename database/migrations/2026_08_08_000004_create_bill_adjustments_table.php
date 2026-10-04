<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A correction to a bill, recorded as its own entry.
 *
 * The rule this table exists to enforce: once a bill has been shown to a
 * tenant, its `amount` is never edited. If the estimate was 1,200 and the
 * provider's bill came in at 1,340, that is a +140 adjustment row, not a
 * rewrite. The tenant can see the estimate, the correction and the reason.
 *
 * Positive amount  = extra charge  (estimate was too low)
 * Negative amount  = credit        (estimate was too high)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_adjustments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')->constrained('utility_billing_proofs')->cascadeOnDelete();

            // reconciliation | correction | goodwill | rounding
            $table->string('type', 30)->default('reconciliation');

            $table->decimal('amount', 12, 2);      // signed: + charge, - credit
            $table->text('reason');                 // required; shown to the tenant

            // For reconciliation: what the provider actually billed, so the
            // arithmetic is auditable without recomputing it.
            $table->decimal('actual_amount', 12, 2)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_adjustments');
    }
};
