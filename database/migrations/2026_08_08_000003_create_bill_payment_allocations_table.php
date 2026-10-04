<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which peso of which payment paid which bill.
 *
 * One payment can settle several bills (a tenant clears two months of
 * electricity in one GCash transfer) and one bill can be settled by several
 * payments (partial payments). Neither is expressible with a foreign key on
 * `payments`, so the relationship gets its own table.
 *
 * This is also the reversal record: if a manager un-accepts a payment, we walk
 * these rows to put the money back on the bills instead of guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_payment_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained('utility_billing_proofs')->cascadeOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete();

            $table->decimal('amount', 12, 2);

            // Set when the allocation is undone (payment un-accepted or rejected).
            // The row is kept so the history shows the money went in and came back.
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();

            $table->timestamps();

            $table->index(['bill_id', 'reversed_at']);
            $table->index(['payment_id', 'reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payment_allocations');
    }
};
